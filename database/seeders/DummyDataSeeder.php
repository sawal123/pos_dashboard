<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\CashLedger;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dummy data lengkap untuk pengembangan lokal:
 * users, businesses, business_user (peran), subscriptions, outlets, devices,
 * categories, products, customers, shifts, sales + sale_items, cash_ledger dan
 * expenses.
 *
 * Idempotent — aman dijalankan berulang: master data dicocokkan lewat natural
 * key (email/slug/kode/identifier/sku/nama), data transaksi hanya dibuat sekali
 * (dicocokkan lewat transaction_number / reference_id / idempotency_key).
 *
 * Semua akun memakai password `password` dan email terverifikasi.
 */
class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $owner = $this->user('Budi Santoso', 'owner@example.com');
        $cashier = $this->user('Siti Aminah', 'cashier@example.com');
        $member = $this->user('Andi Pratama', 'member@example.com');

        $kopi = $this->business('Kopi Nusantara', 'kopi-nusantara', 'cafe', 'cloud');
        $laundry = $this->business('Laundry Bersih Wangi', 'laundry-bersih-wangi', 'laundry', 'cloud');
        $grosir = $this->business('Toko Grosir Makmur', 'toko-grosir-makmur', 'grosir', 'free');

        $this->attach($owner, $kopi, Business::ROLE_OWNER);
        $this->attach($owner, $laundry, Business::ROLE_OWNER);
        $this->attach($cashier, $kopi, Business::ROLE_CASHIER);
        $this->attach($member, $laundry, Business::ROLE_MEMBER);
        $this->attach($member, $grosir, Business::ROLE_OWNER);

        if ($admin !== null) {
            $this->attach($admin, $kopi, Business::ROLE_OWNER);
            $this->attach($admin, $laundry, Business::ROLE_OWNER);
            $this->attach($admin, $grosir, Business::ROLE_OWNER);
        }

        $this->seedKopiNusantara($kopi);
        $this->seedLaundry($laundry);
        $this->seedGrosir($grosir);

        $this->command->info('Dummy data selesai. Password semua akun: "password".');
        $this->command->table(
            ['Email', 'Bisnis (peran)'],
            [
                ['admin@gmail.com', 'Kopi Nusantara (owner), Laundry Bersih Wangi (owner), Toko Grosir Makmur (owner)'],
                ['owner@example.com', 'Kopi Nusantara (owner), Laundry Bersih Wangi (owner)'],
                ['cashier@example.com', 'Kopi Nusantara (cashier)'],
                ['member@example.com', 'Laundry Bersih Wangi (member), Toko Grosir Makmur (owner)'],
            ],
        );
    }

    // ──────────────────────────────────────────────────────────────── Kopi ──

    private function seedKopiNusantara(Business $business): void
    {
        $utama = $this->outlet($business, 'Outlet Utama', 'KOPI-01', 'Jl. Merdeka No. 10');
        $cabang = $this->outlet($business, 'Outlet Cabang Selatan', 'KOPI-02', 'Jl. Sudirman No. 45');

        $this->device($business, $utama, 'DUMMY-KOPI-DEV-1', 'POS Kasir Depan');
        $this->device($business, $utama, 'DUMMY-KOPI-DEV-2', 'Tablet Kasir 2');
        $this->device($business, $cabang, 'DUMMY-KOPI-DEV-3', 'POS Cabang Selatan');

        $kopiCat = $this->category($business, 'Kopi');
        $nonKopiCat = $this->category($business, 'Non-Kopi');
        $snackCat = $this->category($business, 'Makanan Ringan');

        $this->product($business, $kopiCat, 'KOPI-ESPRESSO', 'Espresso', 18000, 6000, 100);
        $this->product($business, $kopiCat, 'KOPI-AMERICANO', 'Americano', 22000, 7000, 100);
        $this->product($business, $kopiCat, 'KOPI-LATTE', 'Cafe Latte', 25000, 9000, 100);
        $this->product($business, $kopiCat, 'KOPI-CAPPUCCINO', 'Cappuccino', 26000, 9500, 100);
        $this->product($business, $kopiCat, 'KOPI-MATCHA', 'Matcha Latte', 28000, 11000, 80);
        $this->product($business, $nonKopiCat, 'NONKOPI-TEA', 'Es Teh Manis', 8000, 2000, 120);
        $this->product($business, $snackCat, 'SNACK-CROISSANT', 'Croissant Butter', 20000, 9000, 20);
        $this->product($business, $snackCat, 'SNACK-BROWNIES', 'Brownies Cokelat', 15000, 6000, 8, ['min_stock' => 10]);

        $andi = $this->customer($business, 'Andi Wijaya', '081234567001', 'andi@example.com');
        $sari = $this->customer($business, 'Sari Dewi', '081234567002', 'sari@example.com');
        $this->customer($business, 'Walk-in Customer', null, null);

        $shiftHariIni = $this->shift($business, $utama, 'KOPI-SHIFT-0002', 'open', 500000, $this->at(0, 7, 30));
        $this->shift($business, $utama, 'KOPI-SHIFT-0001', 'closed', 500000, $this->at(1, 7, 30), 1750000, $this->at(1, 21, 0));

        // Kas manual: modal awal.
        $this->cashEntry($business, $utama, $shiftHariIni, 'DUMMY-KOPI-MODAL-1', 'in', 500000, 'modal', 'Modal awal kasir', $this->at(0, 7, 30));

        $sales = [
            [13, 9, 'cash', [['KOPI-AMERICANO', 2.0], ['SNACK-CROISSANT', 1.0]]],
            [12, 10, 'qris', [['KOPI-LATTE', 1.0], ['KOPI-ESPRESSO', 2.0]]],
            [11, 8, 'cash', [['KOPI-CAPPUCCINO', 1.0], ['NONKOPI-TEA', 2.0]]],
            [10, 14, 'cash', [['KOPI-MATCHA', 2.0], ['SNACK-BROWNIES', 3.0]]],
            [9, 11, 'qris', [['KOPI-LATTE', 3.0]]],
            [7, 9, 'cash', [['KOPI-AMERICANO', 1.0], ['SNACK-CROISSANT', 2.0]]],
            [6, 16, 'cash', [['KOPI-ESPRESSO', 4.0], ['NONKOPI-TEA', 1.0]]],
            [5, 13, 'qris', [['KOPI-CAPPUCCINO', 2.0], ['KOPI-LATTE', 1.0]]],
            [4, 10, 'cash', [['KOPI-MATCHA', 1.0], ['SNACK-BROWNIES', 1.0]]],
            [3, 15, 'cash', [['KOPI-LATTE', 2.0], ['SNACK-CROISSANT', 1.0]]],
            [2, 9, 'qris', [['KOPI-AMERICANO', 3.0]]],
            [1, 12, 'cash', [['KOPI-ESPRESSO', 2.0], ['KOPI-CAPPUCCINO', 2.0]]],
            [0, 8, 'cash', [['KOPI-AMERICANO', 2.0], ['SNACK-CROISSANT', 1.0]]],
            [0, 11, 'qris', [['KOPI-LATTE', 1.0], ['KOPI-MATCHA', 1.0]]],
            [0, 13, 'cash', [['NONKOPI-TEA', 3.0], ['SNACK-BROWNIES', 2.0]]],
        ];
        $this->sales($business, $utama, $shiftHariIni, $sales, 'KOPI-TRX', [$andi, $sari, null]);

        $this->expense($business, $utama, $shiftHariIni, 'DUMMY-KOPI-EXP-1', 'Belanja susu & biji kopi', 'Belanja Stok', 750000, $this->at(2, 9), true);
        $this->expense($business, $utama, $shiftHariIni, 'DUMMY-KOPI-EXP-2', 'Token listrik outlet', 'Operasional', 200000, $this->at(4, 10), true);
        $this->expense($business, $utama, null, 'DUMMY-KOPI-EXP-3', 'Gaji barista harian', 'Gaji', 150000, $this->at(0, 18), false);
    }

    // ───────────────────────────────────────────────────────────── Laundry ──

    private function seedLaundry(Business $business): void
    {
        $pusat = $this->outlet($business, 'Outlet Pusat', 'LDY-01', 'Jl. Melati No. 3');
        $this->device($business, $pusat, 'DUMMY-LDY-DEV-1', 'POS Laundry Pusat');

        $cuciCat = $this->category($business, 'Cuci');
        $tambahanCat = $this->category($business, 'Setrika & Tambahan');

        $cuciKering = $this->product($business, $cuciCat, 'LDY-CUCIKERING', 'Cuci Kering', 7000, 2500, 0, ['kind' => 'service', 'unit' => 'kg', 'pricing_unit' => 'kg', 'min_quantity' => 1]);
        $cuciSetrika = $this->product($business, $cuciCat, 'LDY-CUCISETRIKA', 'Cuci Setrika', 10000, 3500, 0, ['kind' => 'service', 'unit' => 'kg', 'pricing_unit' => 'kg', 'min_quantity' => 1]);
        $setrika = $this->product($business, $tambahanCat, 'LDY-SETRIKA', 'Setrika Saja', 5000, 1800, 0, ['kind' => 'service', 'unit' => 'kg', 'pricing_unit' => 'kg', 'min_quantity' => 1]);
        $this->product($business, $tambahanCat, 'LDY-EXPRESS', 'Cuci Express (1 Hari)', 15000, 6000, 0, ['kind' => 'service', 'unit' => 'kg', 'pricing_unit' => 'kg', 'min_quantity' => 1, 'estimated_duration' => '1 hari']);
        $this->product($business, $tambahanCat, 'LDY-DETERJEN', 'Deterjen Sachet', 3000, 1500, 40, ['unit' => 'pcs']);

        $rina = $this->customer($business, 'Rina Laundry', '082134567001', 'rina@example.com');
        $budi = $this->customer($business, 'Budi Kost', '082134567002', null);

        $shift = $this->shift($business, $pusat, 'LDY-SHIFT-0001', 'open', 300000, $this->at(0, 7, 0));

        $sales = [
            [10, 9, 'cash', [['LDY-CUCISETRIKA', 4.0]]],
            [8, 10, 'cash', [['LDY-CUCIKERING', 6.5]]],
            [6, 14, 'cash', [['LDY-EXPRESS', 3.0], ['LDY-DETERJEN', 2.0]]],
            [4, 11, 'cash', [['LDY-SETRIKA', 5.0]]],
            [2, 9, 'unpaid', [['LDY-CUCISETRIKA', 8.0]]],
            [0, 10, 'unpaid', [['LDY-CUCIKERING', 3.5]]],
            [0, 15, 'cash', [['LDY-CUCISETRIKA', 2.0], ['LDY-SETRIKA', 2.0]]],
        ];
        $this->sales($business, $pusat, $shift, $sales, 'LDY-TRX', [$rina, $budi, null]);

        $this->expense($business, $pusat, $shift, 'DUMMY-LDY-EXP-1', 'Beli deterjen & pewangi', 'Belanja Stok', 350000, $this->at(3, 9), true);
        $this->expense($business, $pusat, $shift, 'DUMMY-LDY-EXP-2', 'Servis mesin cuci', 'Operasional', 450000, $this->at(1, 13), true);
    }

    // ────────────────────────────────────────────────────────────── Grosir ──

    private function seedGrosir(Business $business): void
    {
        $pusat = $this->outlet($business, 'Toko Pusat', 'GRO-01', 'Pasar Baru Blok C');
        $this->device($business, $pusat, 'DUMMY-GRO-DEV-1', 'POS Grosir Pusat');

        $sembakoCat = $this->category($business, 'Sembako');
        $minumanCat = $this->category($business, 'Minuman');

        $this->product($business, $sembakoCat, 'GRO-BERAS', 'Beras Premium 5kg', 65000, 58000, 40, ['unit' => 'karung']);
        $this->product($business, $sembakoCat, 'GRO-GULA', 'Gula Pasir 1kg', 16000, 14000, 60, ['unit' => 'pcs']);
        $this->product($business, $sembakoCat, 'GRO-MINYAK', 'Minyak Goreng 2L', 34000, 31000, 50, ['unit' => 'pouch']);
        $this->product($business, $sembakoCat, 'GRO-TELUR', 'Telur Ayam 1kg', 28000, 26000, 30, ['unit' => 'kg']);
        $this->product($business, $minumanCat, 'GRO-AQUA', 'Air Mineral 600ml (dus)', 45000, 40000, 25, ['unit' => 'dus']);
        $this->product($business, $minumanCat, 'GRO-KOPISACHET', 'Kopi Sachet (renceng)', 12000, 10000, 5, ['min_stock' => 10]);

        $tini = $this->customer($business, 'Warung Bu Tini', '085712345001', null);
        $sebelah = $this->customer($business, 'Toko Sebelah', '085712345002', null);

        $shift = $this->shift($business, $pusat, 'GRO-SHIFT-0001', 'open', 400000, $this->at(0, 6, 30));

        $sales = [
            [9, 8, 'cash', [['GRO-BERAS', 2.0], ['GRO-GULA', 3.0]]],
            [7, 9, 'cash', [['GRO-MINYAK', 4.0], ['GRO-TELUR', 2.0]]],
            [5, 7, 'qris', [['GRO-AQUA', 3.0], ['GRO-KOPISACHET', 5.0]]],
            [3, 10, 'cash', [['GRO-BERAS', 1.0], ['GRO-MINYAK', 1.0], ['GRO-GULA', 2.0]]],
            [1, 8, 'cash', [['GRO-TELUR', 3.0], ['GRO-AQUA', 2.0]]],
            [0, 7, 'cash', [['GRO-BERAS', 3.0], ['GRO-GULA', 4.0]]],
            [0, 9, 'qris', [['GRO-MINYAK', 2.0], ['GRO-KOPISACHET', 3.0]]],
            [0, 12, 'cash', [['GRO-TELUR', 1.0], ['GRO-AQUA', 1.0]]],
        ];
        $this->sales($business, $pusat, $shift, $sales, 'GRO-TRX', [$tini, $sebelah, null]);

        $this->expense($business, $pusat, $shift, 'DUMMY-GRO-EXP-1', 'Kulakan sembako', 'Belanja Stok', 2500000, $this->at(2, 6), true);
        $this->expense($business, $pusat, $shift, 'DUMMY-GRO-EXP-2', 'Sewa lapak bulan ini', 'Operasional', 600000, $this->at(0, 7), false);
    }

    // ─────────────────────────────────────────────────────────────── Helpers ──

    /**
     * Buat penjualan + item + kas (untuk penjualan tunai lunas) sekaligus.
     *
     * @param  array<int, array{0:int,1:int,2:string,3:array<int, array{0:string,1:float}>}>  $specs
     * @param  array<int, Customer|null>  $customers
     */
    private function sales(Business $business, Outlet $outlet, ?Shift $shift, array $specs, string $prefix, array $customers): void
    {
        foreach ($specs as $i => $spec) {
            [$daysAgo, $hour, $payment, $items] = $spec;

            $number = sprintf('%s-%04d', $prefix, $i + 1);
            $soldAt = $this->at($daysAgo, $hour, 0);
            $customer = $customers[$i % count($customers)];

            $this->sale($business, $outlet, $shift, $customer, $number, $items, $payment, $soldAt);
        }
    }

    /**
     * @param  array<int, array{0:string,1:float}>  $items
     */
    private function sale(Business $business, Outlet $outlet, ?Shift $shift, ?Customer $customer, string $number, array $items, string $payment, \DateTimeInterface $soldAt): ?Sale
    {
        if (Sale::where('business_id', $business->id)->where('transaction_number', $number)->exists()) {
            return null;
        }

        $subtotal = 0;
        $costTotal = 0.0;
        $lines = [];

        foreach ($items as [$sku, $qty]) {
            $product = Product::where('business_id', $business->id)->where('sku', $sku)->first();

            if ($product === null) {
                continue;
            }

            $unitPrice = (int) $product->price;
            $unitCost = (float) $product->cost;
            $lineTotal = (int) round($unitPrice * $qty);

            $subtotal += $lineTotal;
            $costTotal += $unitCost * $qty;

            $lines[] = [
                'product' => $product,
                'unit_price' => $unitPrice,
                'quantity' => $qty,
                'line_total' => $lineTotal,
                'unit_cost' => $unitCost,
            ];
        }

        if ($lines === []) {
            return null;
        }

        $paid = $payment !== 'unpaid';
        $isCash = in_array($payment, ['cash'], true);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customer?->id,
            'shift_id' => $shift?->id,
            'transaction_number' => $number,
            'status' => 'completed',
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $subtotal,
            'gross_profit' => round($subtotal - $costTotal, 2),
            'payment_method' => $paid ? $payment : null,
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'paid_at' => $paid ? $soldAt : null,
            'cash_received' => $isCash && $paid ? $subtotal : null,
            'change_amount' => $isCash && $paid ? 0 : null,
            'order_status' => $business->normalizedBusinessType() === 'laundry' ? 'Masuk' : null,
            'sold_at' => $soldAt,
        ]);

        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $line['product'];

            SaleItem::create([
                'business_id' => $business->id,
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'product_name' => (string) $product->name,
                'product_sku' => (string) $product->sku,
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'line_total' => $line['line_total'],
                'cost_snapshot' => $line['unit_cost'],
                'unit' => (string) $product->unit,
                'kind' => (string) $product->kind,
                'pricing_unit' => (string) $product->pricing_unit,
                'line_cost' => round($line['unit_cost'] * $line['quantity'], 2),
            ]);
        }

        if ($isCash && $paid) {
            $this->cashEntry(
                $business,
                $outlet,
                $shift,
                'DUMMY-CASH-'.$number,
                'in',
                (int) $sale->total_amount,
                'Penjualan',
                'Pembayaran '.$number,
                $soldAt,
                (string) $sale->sync_id,
            );
        }

        return $sale;
    }

    private function user(string $name, string $email): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'email_verified_at' => now(), 'password' => 'password'],
        );
    }

    private function business(string $name, string $slug, string $type, string $plan): Business
    {
        $business = Business::updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'active', 'business_type' => $type],
        );

        Subscription::updateOrCreate(
            ['business_id' => $business->id],
            [
                'plan' => $plan,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => $plan === 'cloud' ? now()->addMonth() : null,
            ],
        );

        return $business;
    }

    private function attach(User $user, Business $business, string $role): void
    {
        $user->businesses()->syncWithoutDetaching([
            $business->id => ['role' => $role],
        ]);
    }

    private function outlet(Business $business, string $name, string $code, ?string $address = null): Outlet
    {
        return Outlet::firstOrCreate(
            ['business_id' => $business->id, 'code' => $code],
            ['name' => $name, 'status' => 'active', 'address' => $address],
        );
    }

    private function device(Business $business, Outlet $outlet, string $identifier, string $name): Device
    {
        return Device::firstOrCreate(
            ['business_id' => $business->id, 'identifier' => $identifier],
            [
                'outlet_id' => $outlet->id,
                'name' => $name,
                'platform' => 'android',
                'status' => 'active',
                'registered_at' => now(),
                'last_seen_at' => now(),
            ],
        );
    }

    private function category(Business $business, string $name): Category
    {
        return Category::firstOrCreate(
            ['business_id' => $business->id, 'name' => $name],
            ['status' => 'active'],
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function product(Business $business, Category $category, string $sku, string $name, int $price, float $cost, float $stock, array $extra = []): Product
    {
        return Product::firstOrCreate(
            ['business_id' => $business->id, 'sku' => $sku],
            array_merge([
                'category_id' => $category->id,
                'name' => $name,
                'kind' => 'product',
                'price' => $price,
                'cost' => $cost,
                'stock' => $stock,
                'unit' => 'pcs',
                'min_stock' => 0,
                'pricing_unit' => 'pcs',
                'min_quantity' => 0,
                'status' => 'active',
            ], $extra),
        );
    }

    private function customer(Business $business, string $name, ?string $phone, ?string $email): Customer
    {
        return Customer::firstOrCreate(
            ['business_id' => $business->id, 'name' => $name],
            ['phone' => $phone, 'email' => $email, 'status' => 'active'],
        );
    }

    private function shift(Business $business, Outlet $outlet, string $number, string $status, int $openingCash, \DateTimeInterface $openedAt, ?int $closingCash = null, ?\DateTimeInterface $closedAt = null): Shift
    {
        return Shift::firstOrCreate(
            ['business_id' => $business->id, 'shift_number' => $number],
            [
                'outlet_id' => $outlet->id,
                'status' => $status,
                'opening_cash' => $openingCash,
                'closing_cash' => $closingCash,
                'opened_at' => $openedAt,
                'closed_at' => $closedAt,
            ],
        );
    }

    private function cashEntry(Business $business, Outlet $outlet, ?Shift $shift, string $reference, string $type, int $amount, ?string $category, ?string $note, \DateTimeInterface $occurredAt, ?string $saleSyncId = null, ?int $expenseId = null): CashLedger
    {
        return CashLedger::firstOrCreate(
            ['business_id' => $business->id, 'reference_id' => $reference, 'type' => $type],
            [
                'outlet_id' => $outlet->id,
                'shift_id' => $shift?->id,
                'amount' => $amount,
                'category' => $category,
                'note' => $note,
                'occurred_at' => $occurredAt,
                'sale_sync_id' => $saleSyncId,
                'expense_id' => $expenseId,
            ],
        );
    }

    private function expense(Business $business, Outlet $outlet, ?Shift $shift, string $idempotencyKey, string $description, ?string $category, int $amount, \DateTimeInterface $occurredAt, bool $paidFromCash): Expense
    {
        $expense = Expense::firstOrCreate(
            ['business_id' => $business->id, 'idempotency_key' => $idempotencyKey],
            [
                'outlet_id' => $outlet->id,
                'shift_id' => $shift?->id,
                'description' => $description,
                'category' => $category,
                'amount' => $amount,
                'status' => 'recorded',
                'occurred_at' => $occurredAt,
            ],
        );

        if ($paidFromCash && $expense->wasRecentlyCreated) {
            $this->cashEntry(
                $business,
                $outlet,
                $shift,
                'DUMMY-EXPCASH-'.$idempotencyKey,
                'out',
                $amount,
                CashLedger::CATEGORY_EXPENSE,
                'Kas keluar: '.$description,
                $occurredAt,
                null,
                $expense->id,
            );
        }

        return $expense;
    }

    private function at(int $daysAgo, int $hour, int $minute = 0): \DateTimeInterface
    {
        return now()->subDays($daysAgo)->setTime($hour, $minute);
    }
}
