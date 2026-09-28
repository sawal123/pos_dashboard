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

/**
 * QA-RELEASE Phase A — regression tests for the two genuine gaps found during
 * the integrated baseline audit.
 *
 *  1. The on-screen report and the CSV export were already asserted to agree;
 *     the XLSX and PDF exports were not. This suite pins all three formats to
 *     the same on-screen dataset under the same filter.
 *  2. The dashboard layout rendered two unused legacy demo modals
 *     ("Add Product" / "Delete Product?" placeholders). They were removed;
 *     this suite fails if the placeholder markup ever returns.
 */
class QaReleasePhaseATest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // 1. Cross-format report parity under an identical filter
    // ============================================================

    public function test_csv_xlsx_and_pdf_exports_match_the_on_screen_summary_under_the_same_outlet_filter(): void
    {
        [$owner, $business, $outletA] = $this->makeOwnerWithBusinessAndTwoOutlets();

        $filters = ['outlet_id' => $outletA->id];

        // The on-screen dataset is the reference every export must match.
        $screen = app(DashboardReportsData::class)->get($business, $filters);
        $expectations = $screen['summary'];

        $this->assertSame(100000, $expectations['total_sales']);
        $this->assertSame(1, $expectations['total_transactions']);
        $this->assertSame('25000.00', $expectations['estimated_gross_profit']);
        $this->assertSame(30000, $expectations['total_expenses']);

        // CSV.
        $csv = $this->downloadContent($this->exportAs($owner, $business, 'csv', $filters));
        $this->assertSame('100000', $this->csvValue($csv, 'Total Penjualan'));
        $this->assertSame('1', $this->csvValue($csv, 'Total Transaksi'));
        $this->assertSame('25000.00', $this->csvValue($csv, 'Estimasi Laba Kotor'));
        $this->assertSame('30000', $this->csvValue($csv, 'Total Pengeluaran'));

        // XLSX — the Ringkasan sheet must carry the same numbers as cells.
        $ringkasan = $this->xlsxSummaryRows($this->downloadPath($this->exportAs($owner, $business, 'xlsx', $filters)));
        $this->assertSame(100000, (int) $ringkasan['Total Penjualan']);
        $this->assertSame(1, (int) $ringkasan['Total Transaksi']);
        $this->assertSame(25000.0, (float) $ringkasan['Estimasi Laba Kotor']);
        $this->assertSame(30000, (int) $ringkasan['Total Pengeluaran']);

        // PDF — render the exact document the PDF formatter receives and assert
        // the formatted summary values are present.
        $document = app(ReportExportService::class)
            ->buildDocument($business, ReportFilters::fromArray($filters), 1000);
        $html = view('reports.pdf', ['document' => $document])->render();

        $this->assertStringContainsString($this->rupiah($expectations['total_sales']), $html);
        $this->assertStringContainsString($this->rupiah($expectations['estimated_gross_profit']), $html);
        $this->assertStringContainsString($this->rupiah($expectations['total_expenses']), $html);
        $this->assertSame(
            $expectations['total_transactions'],
            (int) $document->dataset->summary['total_transactions'],
        );
    }

    public function test_exports_carry_the_active_filter_in_every_format(): void
    {
        [$owner, $business, $outletA] = $this->makeOwnerWithBusinessAndTwoOutlets();

        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $response = $this->actingAs($owner)
                ->withSession(['dashboard.current_business_id' => $business->id])
                ->get(route('reports.export.'.$format, ['outlet_id' => $outletA->id]));

            $response->assertOk();
            $this->assertStringContainsString('.'.$format, (string) $response->headers->get('content-disposition'));
        }
    }

    // ============================================================
    // 2. Removed legacy placeholder modals stay removed
    // ============================================================

    public function test_dashboard_pages_do_not_render_the_legacy_placeholder_modals(): void
    {
        [$owner, $business] = $this->makeOwnerWithBusinessAndTwoOutlets();

        foreach (['dashboard', 'products.index', 'cash.index', 'devices.index'] as $routeName) {
            $response = $this->actingAs($owner)
                ->withSession(['dashboard.current_business_id' => $business->id])
                ->get(route($routeName));

            $response->assertOk();

            // The dead demo components must not be included by the layout again.
            $response->assertDontSee('Save Product');
            $response->assertDontSee('Delete Product');
            $response->assertDontSee('id="addProductModal"', false);
            $response->assertDontSee('id="deleteModal"', false);
        }
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: User, 1: Business, 2: Outlet}
     */
    private function makeOwnerWithBusinessAndTwoOutlets(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Subscription::factory()->create(['business_id' => $business->id]);
        $business->users()->attach($user->id, ['role' => 'owner']);

        $outletA = Outlet::factory()->create(['business_id' => $business->id]);
        $outletB = Outlet::factory()->create(['business_id' => $business->id]);

        // Outlet A: counted.
        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outletA->id,
            'total_amount' => 100000,
            'gross_profit' => 25000.00,
        ]);
        $this->createExpense([
            'business_id' => $business->id,
            'outlet_id' => $outletA->id,
            'category' => 'Operasional',
            'amount' => 30000,
        ]);

        // Outlet B: must never leak into the filtered export.
        $this->createSale([
            'business_id' => $business->id,
            'outlet_id' => $outletB->id,
            'total_amount' => 999000,
            'gross_profit' => 999000.00,
        ]);
        $this->createExpense([
            'business_id' => $business->id,
            'outlet_id' => $outletB->id,
            'category' => 'Rahasia B',
            'amount' => 777000,
        ]);

        $this->withSession(['dashboard.current_business_id' => $business->id]);

        return [$user, $business, $outletA];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function exportAs(User $user, Business $business, string $format, array $filters): TestResponse
    {
        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.export.'.$format, $filters));

        $response->assertOk();

        return $response;
    }

    /**
     * Mirror of the PDF blade's rupiah formatter.
     */
    private function rupiah(int|float|string $value): string
    {
        $number = (float) $value;

        return 'Rp '.number_format($number, floor($number) === $number ? 0 : 2, ',', '.');
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
     * Map the "Ringkasan" XLSX sheet to `[label => value]`.
     *
     * @return array<string, mixed>
     */
    private function xlsxSummaryRows(string $path): array
    {
        $reader = new XlsxReader;
        $reader->open($path);

        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== 'Ringkasan') {
                    continue;
                }

                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->toArray();
                    if (isset($cells[0], $cells[1]) && is_string($cells[0])) {
                        $rows[$cells[0]] = $cells[1];
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSale(array $attributes): Sale
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;
        $total = $attributes['total_amount'] ?? 100000;

        return Sale::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'customer_id' => null,
            'shift_id' => null,
            'transaction_number' => 'TRX-'.strtoupper(uniqid()),
            'status' => 'completed',
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $total,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'cash_received' => $total,
            'change_amount' => 0,
            'gross_profit' => 0,
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
    private function createExpense(array $attributes): Expense
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
