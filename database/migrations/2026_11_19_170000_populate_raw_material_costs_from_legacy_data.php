<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Map of raw materials (by SKU) to their unit cost / HPP derived from
     * legacy data (u663455529_temuanspace (1).sql: pengeluarans & ingredients).
     *
     * @return array<string, float>
     */
    private function legacyCosts(): array
    {
        return [
            'RAW-0001' => 3000.00,  // Ifu Mie (pcs)
            'RAW-0002' => 3500.00,  // Indomie (bks)
            'RAW-0003' => 1800.00,  // Telur (butir) - from pengeluaran: 30 butir = Rp 54.000
            'RAW-0004' => 8000.00,  // Roti (bks) - from pengeluaran: 2 bks = Rp 16.000
            'RAW-0005' => 29.00,    // Kentang (gram) - from pengeluaran: 1 kg = Rp 29.000
            'RAW-0006' => 2000.00,  // Sosis (pcs) - from pengeluaran: Rp 38.000/kg
            'RAW-0007' => 1800.00,  // Nugget (pcs) - from pengeluaran: Rp 37.000/kg
            'RAW-0008' => 2500.00,  // Dimsum Ayam (pcs)
            'RAW-0009' => 3000.00,  // Dimsum Udang (pcs)
            'RAW-0010' => 1500.00,  // Bakso (pcs)
            'RAW-0011' => 3500.00,  // Udang (pcs) - from pengeluaran: Rp 35.000
            'RAW-0012' => 10.00,    // Kwitiaw (gram) - from pengeluaran: Mie tiaw 1kg = Rp 10.000
            'RAW-0013' => 15000.00, // Ayam (potong) - from pengeluaran: 2 potong = Rp 33.000
            'RAW-0014' => 3000.00,  // Dimsum Rumput Laut (pcs)
            'RAW-0016' => 280.00,   // Kopi Manual (bks/gram) - from pengeluaran: Kopi manual Rp 280.000/kg
            'RAW-0017' => 15000.00, // SKM (susu kental manis) (bks)
            'RAW-0018' => 20000.00, // UHT Full Cream (kotak) - from pengeluaran: Milklife
            'RAW-0019' => 122.00,   // Pwdr Cokelat (miligram/gram) - from pengeluaran: Powder coklat Rp 122.000/kg
            'RAW-0020' => 53000.00, // Powder Taro (bks) - from pengeluaran: Bubuk taro Rp 53.000
            'RAW-0021' => 130.00,   // Pwdr Redvalvet (miligram/gram)
            'RAW-0022' => 140.00,   // Matcha (miligram/gram)
            'RAW-0023' => 45.00,    // Creamer (gram)
            'RAW-0024' => 23000.00, // Marjan Syrup Melon (botol)
            'RAW-0025' => 23000.00, // Marjan Lyche (botol)
            'RAW-0026' => 25.00,    // Marjan Manga (miligram/gram)
            'RAW-0027' => 25000.00, // Marjan Vanilla (botol)
            'RAW-0028' => 35.00,    // Syrp Blue Lagon (miligram/gram)
            'RAW-0029' => 23000.00, // Marjan Strobery (botol) - from pengeluaran: Sirup marjan strawberi Rp 62.300 / 3 btl
            'RAW-0030' => 40.00,    // Syrp Peach (miligram/gram)
            'RAW-0031' => 40.00,    // Syrp Hazelnut (miligram/gram)
            'RAW-0032' => 40.00,    // Syrp Salted Caramel (miligram/gram)
            'RAW-0033' => 35.00,    // Syrp Mojito Mint (miligram/gram)
            'RAW-0034' => 35.00,    // Syrp Banana (miligram/gram)
            'RAW-0035' => 40.00,    // Syrp Caramel (miligram/gram)
            'RAW-0036' => 20.00,    // Gula Putih (gram) - from pengeluaran: Rp 5.000 / 250gr
            'RAW-0037' => 25.00,    // Gula Aren (miligram/gram)
            'RAW-0038' => 8000.00,  // Soda Sprite (kaleng/ml)
            'RAW-0039' => 21000.00, // Cleo (galon) - from pengeluaran: Cleo 5 galon = Rp 105.000
            'RAW-0040' => 500.00,   // Cup Dingin (pcs)
            'RAW-0041' => 400.00,   // Cup Plastik (pcs)
            'RAW-0042' => 15000.00, // Coklat Extra (bks)
            'RAW-0043' => 32000.00, // La Bold 16 (bks)
            'RAW-0044' => 24000.00, // Super 12 (bks)
            'RAW-0045' => 31000.00, // La Ice Purple Boost 16 (bks)
            'RAW-0046' => 17000.00, // Djarum 76 Apel (bks)
            'RAW-0047' => 22000.00, // Insta Water Melon (bks)
            'RAW-0048' => 23000.00, // Chief 16 (bks)
            'RAW-0049' => 18000.00, // Djarum 76 aple royal (pcs/bks)
            'RAW-0050' => 23000.00, // Sryp Jeruk (botol)
            'RAW-0051' => 50.00,    // Saus Caramel (gram)
            'RAW-0052' => 50.00,    // Saus Caramel (gram)
            'RAW-0053' => 23700.00, // Marjan Sirup Lemon (botol) - from pengeluaran: 4 btl = Rp 94.800
            'RAW-0054' => 15000.00, // Spaghetti (pcs)
            'RAW-0055' => 114.00,   // Butterscotch (gram) - preserved from legacy ingredients table
            'RAW-0056' => 86.00,    // Gold Label (gram) - preserved from legacy ingredients table
            'RAW-0057' => 120.00,   // Crumble Caramel (gram) - preserved from legacy ingredients table
            'RAW-0058' => 5.00,     // Air Panas (gram)
            'RAW-0059' => 15000.00, // Es Batu (bks) - from pengeluaran: Rp 15.000 / Rp 16.000
            'RAW-0060' => 8000.00,  // Tisu (pcs)
            'RAW-0061' => 10000.00, // Sedotan (bks)
            'RAW-0062' => 3000.00,  // Buah Lemon (pcs) - from pengeluaran: Lemon Rp 12.000 - Rp 20.000
            'RAW-0063' => 3000.00,  // Buah Lychee (pcs)
            'RAW-0064' => 5000.00,  // TEH (pcs)
            'RAW-0065' => 45000.00, // Thai Tea Mix (bks)
            'RAW-0066' => 4000.00,  // Ifo mie (bks)
            'RAW-0067' => 23000.00, // Marjan sosh Lych (botol)
            'RAW-0068' => 45000.00, // Sirup Butterscotch (botol)
            'RAW-0069' => 42500.00, // Minyak bimoli (liter) - from pengeluaran: Bimoli 1 Liter = Rp 42.500
            'RAW-0070' => 2300.00,  // Selada (ons) - from pengeluaran: Salada 3 ons = Rp 7.000
            'RAW-0071' => 2800.00,  // Bawan Merah (ons) - from pengeluaran: 1/4 kg = Rp 7.000
            'RAW-0072' => 1800.00,  // Tomat (ons) - from pengeluaran: 1/2 kg = Rp 9.000
            'RAW-0073' => 12500.00, // Tempe (bks) - from pengeluaran: 2 bks = Rp 25.000
            'RAW-0074' => 7000.00,  // Timun (kilogram) - from pengeluaran: Timun 1kg = Rp 7.000
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $costs = $this->legacyCosts();

        foreach ($costs as $sku => $cost) {
            DB::table('products')
                ->where('sku', $sku)
                ->where(function ($query) {
                    $query->whereNull('cost')
                        ->orWhere('cost', 0)
                        ->orWhereNull('hpp')
                        ->orWhere('hpp', 0);
                })
                ->update([
                    'cost' => $cost,
                    'hpp' => $cost,
                    'hpp_calculated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $costs = $this->legacyCosts();

        // Only revert SKUs that were populated by this migration (exclude 55, 56, 57 which were in old dump)
        $preservedSkus = ['RAW-0055', 'RAW-0056', 'RAW-0057'];

        foreach (array_keys($costs) as $sku) {
            if (in_array($sku, $preservedSkus, true)) {
                continue;
            }

            DB::table('products')
                ->where('sku', $sku)
                ->update([
                    'cost' => 0.00,
                    'hpp' => 0.00,
                ]);
        }
    }
};
