# Update Fitur: Kalkulasi HPP & Manajemen Resep (BOM)

- **Branch**: `feat/hpp-d01-product-hpp-recipe`
- **Tanggal Rilis**: 02 November 2026
- **Status**: Selesai & Teruji (168 Unit/Feature Tests Passed, Pint Passed)

---

## 1. Ringkasan Fitur
Fitur HPP (Harga Pokok Penjualan) menyediakan sistem kalkulasi modal produk yang fleksibel:
- **Harga Modal Acuan (`cost`)**: Tetap menjadi nilai resmi yang dipakai kasir POS untuk perhitungan laba kotor transaksi dan valuasi stok.
- **HPP Terhitung (`hpp`)**: Angka estimasi modal hasil kalkulasi sistem (melalui rata-rata tertimbang penerimaan barang atau kalkulasi resep).
- **Pilihan Sumber Modal (`cost_source`)**:
  - `manual`: Harga modal diketik bebas oleh pengguna. Penerimaan barang tidak akan menimpa harga modal secara diam-diam.
  - `hpp`: Harga modal otomatis disinkronkan mengikuti hasil kalkulasi HPP sistem.
- **Isolasi POS**: Kasir di aplikasi kasir tidak melihat HPP atau formula resep, menjaga kerahasiaan margin dan formula bisnis.

---

## 2. Metode Kalkulasi HPP (`hpp_method`)
1. **Rata-rata Tertimbang (`average`)**:
   Dihitung saat penerimaan stok masuk dengan memperhitungkan sisa stok yang ada:
   $$\text{HPP Baru} = \frac{(\text{Sisa Stok} \times \text{Modal Lama}) + (\text{Qty Masuk} \times \text{Harga Beli Baru})}{\text{Sisa Stok} + \text{Qty Masuk}}$$
   Jika sisa stok $\le 0$, HPP langsung disesuaikan ke harga beli baru.
2. **Resep / Bill of Materials (`recipe`)**:
   Untuk produk olahan (contoh: Americano, Cafe Latte, Makanan):
   $$\text{HPP Resep} = \sum (\text{Takaran Bahan} \times \text{Modal Bahan}) + \text{Overhead Tetap} + (\text{Subtotal Bahan} \times \% \text{Overhead})$$
   Kaskade resep otomatis memperbarui produk jadi apabila harga modal bahan baku penyusunnya berubah.

---

## 3. Komponen UI & UX Baru
- **Modal Produk Responsif**: Header judul dan tombol aksi sticky, dengan area form yang dapat di-scroll secara independen sehingga form tetap utuh dan nyaman di semua ukuran layar.
- **Tombol Dinamis "+ Tambah Bahan" di Tab Persediaan**:
  - Pada tab **Persediaan**, tombol tambah otomatis berubah menjadi **"+ Tambah Bahan"** dengan judul modal **"TAMBAH Bahan Baku"**.
  - Opsi *"Dijual di POS"* otomatis tidak dicentang (`is_sellable = false`), harga jual default Rp 0, dan otomatis dialihkan kembali ke tab Persediaan setelah disimpan.
- **Tombol Otomatis "Generate SKU"**:
  - Input field SKU kini memiliki tombol **Generate SKU** (baik di label atas maupun tombol inline di dalam input field).
  - Meng-generate kode unik secara cerdas berdasarkan jenis item (`RAW-XXXXXX` untuk bahan baku, `SKU-XXXXXX` untuk produk fisik, dan `SRV-XXXXXX` untuk layanan).
- **Select2 Searchable Bahan Baku**:
  - Dropdown bahan baku pada resep kini dilengkapi fitur pencarian cepat (nama bahan, SKU, atau harga satuan).
  - Tampilan telah disesuaikan dengan tema gelap (Dark Mode) dan terang (Light Mode) NexaPOS.
  - Pembersihan baris dan pemilihan bahan terintegrasi secara reaktif dengan penghitungan estimasi HPP dan margin keuntungan secara live.

---

## 4. Seeder Data Contoh
Data simulasi bahan baku, produk olahan resep (Cafe/FnB), snack titipan, dan merchandise dapat di-generate melalui:
```bash
php artisan db:seed --class=ProductHppSeeder
```

---

## 5. Skema Database & Migrasi
- `database/migrations/2026_11_02_000001_add_hpp_columns_to_products_table.php`
- `database/migrations/2026_11_02_000002_create_product_recipe_items_table.php`
- `database/migrations/2026_11_02_000003_create_product_cost_histories_table.php`

