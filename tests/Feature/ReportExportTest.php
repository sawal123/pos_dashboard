<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dashboard\DashboardReportsData;
use App\Services\Dashboard\Reporting\ReportExportService;
use App\Services\Dashboard\Reporting\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * DASH-13 — report export regression suite (CSV / XLSX / PDF).
 *
 * Covers authorization + tenant isolation, filter parity with the on-screen
 * report, accounting rules, spreadsheet formula-injection safety and the export
 * volume cap.
 */
class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // Authorization
    // ============================================================

    public function test_01_guest_is_redirected_to_login(): void
    {
        $this->get(route('reports.export.csv'))->assertRedirect(route('login'));
    }

    public function test_02_unverified_user_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.csv'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_03_cashier_cannot_export_any_format(): void
    {
        [, $business] = $this->makeOwnerWithBusiness();
        $cashier = $this->attachRole($business, 'cashier');

        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $this->actingAs($cashier)
                ->withSession(['dashboard.current_business_id' => $business->id])
                ->get(route('reports.export.'.$format))
                ->assertForbidden();
        }
    }

    public function test_04_member_with_reports_view_can_export(): void
    {
        [, $business] = $this->makeOwnerWithBusiness();
        $this->createSale(['business_id' => $business->id, 'total_amount' => 120000]);
        $member = $this->attachRole($business, 'member');

        $response = $this->actingAs($member)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.csv'));

        $response->assertOk();
        $this->assertSame('120000', $this->csvValue($this->downloadContent($response), 'Total Penjualan'));
    }

    public function test_05_unknown_role_is_denied(): void
    {
        [, $business] = $this->makeOwnerWithBusiness();
        $supervisor = $this->attachRole($business, 'supervisor');

        $this->actingAs($supervisor)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.csv'))
            ->assertForbidden();
    }

    public function test_06_user_without_business_is_handled_safely(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('reports.export.csv'))
            ->assertForbidden();
    }

    // ============================================================
    // Tenant isolation
    // ============================================================

    public function test_07_business_a_never_receives_business_b_data(): void
    {
        [$ownerA, $businessA] = $this->makeOwnerWithBusiness();
        [$ownerB, $businessB] = $this->makeOwnerWithBusiness();

        $this->createSale(['business_id' => $businessA->id, 'total_amount' => 111000]);
        $this->createExpense(['business_id' => $businessB->id, 'category' => 'Rahasia B', 'amount' => 777000]);

        $response = $this->actingAs($ownerA)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->get(route('reports.export.csv'));

        $content = $this->downloadContent($response);

        $this->assertSame('111000', $this->csvValue($content, 'Total Penjualan'));
        $this->assertStringNotContainsString('777000', $content);
        $this->assertStringNotContainsString('Rahasia B', $content);
        $this->assertStringNotContainsString($businessB->name, $content);
    }

    public function test_08_forged_business_id_does_not_switch_tenant(): void
    {
        [$ownerA, $businessA] = $this->makeOwnerWithBusiness();
        [, $businessB] = $this->makeOwnerWithBusiness();

        $this->createSale(['business_id' => $businessA->id, 'total_amount' => 222000]);
        $this->createSale(['business_id' => $businessB->id, 'total_amount' => 999000]);

        $response = $this->actingAs($ownerA)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->get(route('reports.export.csv', ['business_id' => $businessB->id]));

        $content = $this->downloadContent($response);

        $this->assertSame('222000', $this->csvValue($content, 'Total Penjualan'));
        $this->assertStringNotContainsString('999000', $content);
    }

    public function test_09_cross_tenant_outlet_is_rejected(): void
    {
        [$ownerA, $businessA] = $this->makeOwnerWithBusiness();
        [, $businessB] = $this->makeOwnerWithBusiness();
        $foreignOutlet = Outlet::factory()->create(['business_id' => $businessB->id]);

        $this->actingAs($ownerA)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->from(route('reports.index'))
            ->get(route('reports.export.csv', ['outlet_id' => $foreignOutlet->id]))
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasErrors('outlet_id');
    }

    // ============================================================
    // CSV
    // ============================================================

    public function test_10_csv_response_has_download_headers(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->createSale(['business_id' => $business->id]);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.csv'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('.csv', $disposition);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $this->downloadContent($response));
    }

    public function test_25_csv_formula_injection_is_neutralised_and_negatives_are_kept(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->createExpense([
            'business_id' => $business->id,
            'category' => '=SUM(A1:A2)',
            'amount' => 10000,
        ]);
        $this->createSale([
            'business_id' => $business->id,
            'payment_method' => '@evil',
            'gross_profit' => -12345.67,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.csv'));

        $content = $this->downloadContent($response);

        // Dangerous text is prefixed so spreadsheets treat it as text.
        $this->assertTrue($this->csvHasCell($content, "'=SUM(A1:A2)"), 'Formula text must be neutralised.');
        $this->assertTrue($this->csvHasCell($content, "'@evil"), 'At-prefixed text must be neutralised.');

        // A genuine negative number must stay numeric (no leading quote).
        $this->assertSame('-12345.67', $this->csvValue($content, 'Estimasi Laba Kotor'));
    }

    // ============================================================
    // XLSX
    // ============================================================

    public function test_11_xlsx_can_be_opened_and_contains_report_content(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->createSale(['business_id' => $business->id, 'total_amount' => 250000]);
        $this->createExpense(['business_id' => $business->id, 'category' => 'Operasional', 'amount' => 40000]);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.xlsx'));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );

        $sheets = $this->xlsxSheets($this->downloadPath($response));

        $this->assertArrayHasKey('Ringkasan', $sheets);
        $this->assertArrayHasKey('Tren Penjualan', $sheets);
        $this->assertArrayHasKey('Metode Pembayaran', $sheets);
        $this->assertArrayHasKey('Pengeluaran', $sheets);

        $values = $this->flatten($sheets['Ringkasan']);
        $this->assertTrue(in_array($business->name, $values, false), 'Business name missing from Ringkasan sheet.');
        $this->assertTrue(in_array(250000, $values, false), 'Total sales missing from Ringkasan sheet.');
        $this->assertTrue(in_array(40000, $values, false), 'Total expenses missing from Ringkasan sheet.');
    }

    public function test_26_xlsx_writes_user_text_as_string_not_formula(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->createExpense(['business_id' => $business->id, 'category' => '=SUM(A1:A2)', 'amount' => 5000]);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.xlsx'));

        $path = $this->downloadPath($response);

        $this->assertTrue(
            in_array('=SUM(A1:A2)', $this->flatten($this->xlsxSheets($path)['Pengeluaran']), false),
            'User text must be preserved verbatim in a string cell.',
        );

        // No formula cells are emitted anywhere in the workbook.
        $this->assertStringNotContainsString('<f>', $this->xlsxSheetXml($path));
    }

    // ============================================================
    // PDF
    // ============================================================

    public function test_12_pdf_has_report_header_and_content(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->createSale(['business_id' => $business->id, 'total_amount' => 310000]);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', $this->downloadContent($response));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));

        // The rendered template carries the identity block and the sections.
        $document = app(ReportExportService::class)
            ->buildDocument($business, ReportFilters::fromArray([]), 100);
        $html = view('reports.pdf', ['document' => $document])->render();

        $this->assertStringContainsString('Laporan Penjualan', $html);
        $this->assertStringContainsString($business->name, $html);
        $this->assertStringContainsString('Ringkasan', $html);
        $this->assertStringContainsString('Tren Penjualan', $html);
        $this->assertStringContainsString('Breakdown Metode Pembayaran', $html);
        $this->assertStringContainsString('Breakdown Kategori Pengeluaran', $html);
        $this->assertStringContainsString('Rp 310.000', $html);
    }

    // ============================================================
    // Filters
    // ============================================================

    public function test_13_to_16_filters_match_the_on_screen_report(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->createSale(['business_id' => $business->id, 'total_amount' => 100000, 'sold_at' => now()]);
        $this->createSale(['business_id' => $business->id, 'total_amount' => 200000, 'sold_at' => now()->subDays(10)]);
        $this->createSale(['business_id' => $business->id, 'total_amount' => 400000, 'sold_at' => now()->subDays(40)]);

        // today
        $this->assertExportSummary($owner, $business, ['date' => 'today'], ['total_sales' => 100000, 'total_transactions' => 1]);

        // 7d
        $this->assertExportSummary($owner, $business, ['date' => '7d'], ['total_sales' => 100000, 'total_transactions' => 1]);

        // 30d
        $this->assertExportSummary($owner, $business, ['date' => '30d'], ['total_sales' => 300000, 'total_transactions' => 2]);

        // custom
        $this->assertExportSummary($owner, $business, [
            'date' => 'custom',
            'start_date' => now()->subDays(45)->format('Y-m-d'),
            'end_date' => now()->subDays(35)->format('Y-m-d'),
        ], ['total_sales' => 400000, 'total_transactions' => 1]);
    }

    public function test_17_outlet_filter_is_applied(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $outletA = Outlet::factory()->create(['business_id' => $business->id]);
        $outletB = Outlet::factory()->create(['business_id' => $business->id]);

        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outletA->id, 'total_amount' => 51000]);
        $this->createSale(['business_id' => $business->id, 'outlet_id' => $outletB->id, 'total_amount' => 62000]);

        $this->assertExportSummary($owner, $business, ['outlet_id' => $outletA->id], [
            'total_sales' => 51000,
            'total_transactions' => 1,
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.csv', ['outlet_id' => $outletA->id]));

        $content = $this->downloadContent($response);
        $this->assertStringContainsString($outletA->name, $content);
        $this->assertStringNotContainsString('62000', $content);
    }

    public function test_27_invalid_date_input_returns_a_validation_error_without_a_file(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->createSale(['business_id' => $business->id]);

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->from(route('reports.index'))
            ->get(route('reports.export.csv', ['date' => 'banana']))
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasErrors('date');

        $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->from(route('reports.index'))
            ->get(route('reports.export.csv', ['date' => 'custom', 'start_date' => '2026-13-45']))
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasErrors('start_date');
    }

    public function test_28_export_volume_limit_is_enforced_with_a_notice(): void
    {
        config(['reports.export_max_rows' => 2]);
        [$owner, $business] = $this->makeOwnerWithBusiness();

        foreach (['Kategori A', 'Kategori B', 'Kategori C'] as $index => $category) {
            $this->createExpense([
                'business_id' => $business->id,
                'category' => $category,
                'amount' => 1000 * ($index + 1),
            ]);
        }

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.csv'));

        $content = $this->downloadContent($response);

        $this->assertStringContainsString('Data dipotong', $content);
        $this->assertStringContainsString('batas 2 baris', $content);
        // Sections are ordered by amount desc, so the two largest are kept.
        $this->assertStringContainsString('Kategori C', $content);
        $this->assertStringContainsString('Kategori B', $content);
        $this->assertStringNotContainsString('Kategori A', $content);
    }

    // ============================================================
    // Accounting rules & dataset parity
    // ============================================================

    public function test_18_empty_dataset_produces_safe_output_in_every_format(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        $csv = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.csv'));
        $csv->assertOk();
        $content = $this->downloadContent($csv);
        $this->assertSame('0', $this->csvValue($content, 'Total Penjualan'));
        $this->assertStringContainsString('Tidak ada data', $content);

        foreach (['xlsx', 'pdf'] as $format) {
            $this->actingAs($owner)
                ->withSession(['dashboard.current_business_id' => $business->id])
                ->get(route('reports.export.'.$format))
                ->assertOk();
        }
    }

    public function test_19_to_21_accounting_rules_match_the_screen(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();

        // Counted: completed + paid, and recorded expenses.
        $this->createSale(['business_id' => $business->id, 'total_amount' => 100000, 'gross_profit' => 25000.00]);
        // Excluded: unpaid, canceled, void.
        $this->createSale(['business_id' => $business->id, 'total_amount' => 900000, 'payment_status' => 'unpaid']);
        $this->createSale(['business_id' => $business->id, 'total_amount' => 800000, 'status' => 'cancelled']);
        $this->createSale(['business_id' => $business->id, 'total_amount' => 700000, 'status' => 'void']);
        // Expenses: only `recorded` counts.
        $this->createExpense(['business_id' => $business->id, 'category' => 'Operasional', 'amount' => 30000]);
        $this->createExpense(['business_id' => $business->id, 'category' => 'Void', 'amount' => 500000, 'status' => 'void']);

        $screen = app(DashboardReportsData::class)->get($business, []);

        $this->assertSame(100000, $screen['summary']['total_sales']);
        $this->assertSame(1, $screen['summary']['total_transactions']);
        $this->assertSame(30000, $screen['summary']['total_expenses']);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.csv'));

        $content = $this->downloadContent($response);

        $this->assertSame('100000', $this->csvValue($content, 'Total Penjualan'));
        $this->assertSame('1', $this->csvValue($content, 'Total Transaksi'));
        $this->assertSame('30000', $this->csvValue($content, 'Total Pengeluaran'));
        $this->assertStringNotContainsString('900000', $content);
        $this->assertStringNotContainsString('800000', $content);
        $this->assertStringNotContainsString('700000', $content);
        $this->assertStringNotContainsString('500000', $content);
    }

    public function test_22_and_23_summary_and_breakdown_match_the_screen(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->createSale(['business_id' => $business->id, 'total_amount' => 200000, 'payment_method' => 'cash', 'gross_profit' => 50000.00]);
        $this->createSale(['business_id' => $business->id, 'total_amount' => 100000, 'payment_method' => 'qris', 'gross_profit' => 10000.00]);
        $this->createExpense(['business_id' => $business->id, 'category' => 'Operasional', 'amount' => 30000]);

        $screen = app(DashboardReportsData::class)->get($business, []);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.csv'));

        $content = $this->downloadContent($response);

        // Summary parity.
        $this->assertSame((string) $screen['summary']['total_sales'], $this->csvValue($content, 'Total Penjualan'));
        $this->assertSame((string) $screen['summary']['total_transactions'], $this->csvValue($content, 'Total Transaksi'));
        $this->assertSame((string) $screen['summary']['total_expenses'], $this->csvValue($content, 'Total Pengeluaran'));

        // Breakdown parity (payment + expense).
        $cash = collect($screen['paymentBreakdown'])->firstWhere('payment_method', 'Tunai');
        $this->assertNotNull($cash);
        $this->assertSame(
            ['Tunai', (string) $cash['transaction_count'], (string) $cash['total_amount'], (string) $cash['percentage']],
            $this->csvRow($content, 'Tunai'),
        );

        $expense = collect($screen['expenseBreakdown'])->firstWhere('category', 'Operasional');
        $this->assertNotNull($expense);
        $this->assertSame(
            ['Operasional', (string) $expense['expense_count'], (string) $expense['total_amount'], (string) $expense['percentage']],
            $this->csvRow($content, 'Operasional'),
        );

        // The trend section reflects the same dates the screen shows.
        foreach ($screen['salesTrend'] as $trend) {
            $this->assertSame(
                [(string) $trend['date_raw'], (string) $trend['total_sales'], (string) $trend['transaction_count']],
                $this->csvRow($content, (string) $trend['date_raw']),
            );
        }
    }

    public function test_24_gross_profit_decimals_are_preserved(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->createSale(['business_id' => $business->id, 'gross_profit' => 30000.50]);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.csv'));

        $this->assertStringContainsString('30000.50', $this->downloadContent($response));
    }

    public function test_decimal_gross_profit_is_a_numeric_xlsx_cell(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $this->createSale(['business_id' => $business->id, 'gross_profit' => 30000.50]);

        $response = $this->actingAs($owner)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.xlsx'));

        $this->assertTrue(
            in_array(30000.5, $this->flatten($this->xlsxSheets($this->downloadPath($response))['Ringkasan']), false),
            'Decimal gross profit must be a numeric cell.',
        );
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: User, 1: Business}
     */
    private function makeOwnerWithBusiness(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Subscription::factory()->create(['business_id' => $business->id]);
        $business->users()->attach($user->id, ['role' => 'owner']);

        $this->withSession(['dashboard.current_business_id' => $business->id]);

        return [$user, $business];
    }

    private function attachRole(Business $business, string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business->users()->attach($user->id, ['role' => $role]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, int>  $expected
     */
    private function assertExportSummary(User $user, Business $business, array $filters, array $expected): void
    {
        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.csv', $filters));

        $response->assertOk();
        $content = $this->downloadContent($response);

        foreach ($expected as $key => $value) {
            $label = $key === 'total_sales' ? 'Total Penjualan' : 'Total Transaksi';
            $this->assertSame(
                (string) $value,
                $this->csvValue($content, $label),
                "Filter [{$key}] mismatch.",
            );
        }
    }

    private function downloadPath(TestResponse $response): string
    {
        $base = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $base);

        return $base->getFile()->getPathname();
    }

    private function downloadContent(TestResponse $response): string
    {
        $content = file_get_contents($this->downloadPath($response));

        return $content === false ? '' : $content;
    }

    /**
     * Parse CSV content into rows (tolerating the quotes PHP adds around any
     * field containing a space).
     *
     * @return list<list<string>>
     */
    private function csvRows(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = array_map(static fn ($cell): string => $cell === null ? '' : (string) $cell, $row);
        }

        fclose($stream);

        return $rows;
    }

    /**
     * Value in column B of the first row whose label (column A) matches.
     */
    private function csvValue(string $content, string $label): ?string
    {
        foreach ($this->csvRows($content) as $row) {
            if (($row[0] ?? null) === $label) {
                return $row[1] ?? null;
            }
        }

        return null;
    }

    /**
     * @return list<string>|null
     */
    private function csvRow(string $content, string $firstCell): ?array
    {
        foreach ($this->csvRows($content) as $row) {
            if (($row[0] ?? null) === $firstCell) {
                return $row;
            }
        }

        return null;
    }

    private function csvHasCell(string $content, string $value): bool
    {
        foreach ($this->csvRows($content) as $row) {
            if (in_array($value, $row, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read every sheet of an XLSX file into `[sheet => rows]`.
     *
     * @return array<string, list<list<mixed>>>
     */
    private function xlsxSheets(string $path): array
    {
        $reader = new XlsxReader;
        $reader->open($path);

        $sheets = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
                $sheets[$sheet->getName()] = $rows;
            }
        } finally {
            $reader->close();
        }

        return $sheets;
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return list<mixed>
     */
    private function flatten(array $rows): array
    {
        $flat = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                if (is_scalar($value) || $value === null) {
                    $flat[] = $value;
                }
            }
        }

        return $flat;
    }

    private function xlsxSheetXml(string $path): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $xml = '';
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                $xml .= (string) $zip->getFromIndex($index);
            }
        }

        $zip->close();

        return $xml;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSale(array $attributes = []): Sale
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;

        $subtotal = $attributes['subtotal'] ?? 100000;
        $discount = $attributes['discount_amount'] ?? 0;
        $tax = $attributes['tax_amount'] ?? 0;
        $total = $attributes['total_amount'] ?? ($subtotal - $discount + $tax);

        return Sale::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'customer_id' => null,
            'shift_id' => null,
            'transaction_number' => 'TRX-'.strtoupper(uniqid()),
            'status' => 'completed',
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'cash_received' => $total,
            'change_amount' => 0,
            'gross_profit' => 30000.00,
            'order_status' => null,
            'estimated_completed_at' => null,
            'note' => null,
            'customer_snapshot' => null,
            'business_snapshot' => null,
            'sold_at' => now(),
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createExpense(array $attributes = []): Expense
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;

        return Expense::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'shift_id' => null,
            'description' => 'Biaya Operasional Sample',
            'category' => 'Operasional',
            'amount' => 25000,
            'status' => 'recorded',
            'occurred_at' => now(),
            'notes' => null,
            'sync_id' => (string) Str::uuid(),
            'sync_version' => 1,
            'sync_sequence' => 0,
        ], $attributes));
    }
}
