# Rencana Dashboard: QR/Barcode Grosir dan Sinkronisasi POS Mobile

Tanggal: 4 November 2026  
Status: Rencana; belum ada perubahan implementasi, migrasi, atau deployment.  
Repositori: `pos_dashboard`  
Pasangan rencana: [Scan QR/Barcode POS Mobile](../../../pos-mobile/docs/plans/PLAN_SCAN_QR_BARCODE_GROSIR.md).

## 1. Tujuan dan hasil akhir

Menyelaraskan fitur QR/barcode untuk bisnis **Grosir / Toko Kelontong** antara dashboard dan POS mobile:

1. Owner dapat mengisi barcode kemasan atau membuat kode internal dan QR produk dari dashboard.
2. Kode yang sama tersimpan pada produk yang sama di server dan perangkat mobile, sehingga label dari dashboard dapat dipindai mobile, dan sebaliknya.
3. Perubahan barcode melalui dashboard muncul pada pull berikutnya. Perubahan dari mobile yang memiliki izin mengelola katalog muncul pada dashboard setelah push diterima.
4. POS web dashboard juga memiliki tombol scan kamera untuk menambahkan produk ke keranjang melalui alur penjualan yang sudah ada.
5. Pembaruan bertahap tidak membuat aplikasi mobile lama menghapus barcode yang telah dibuat.

Sinkronisasi mengikuti push/pull dan otorisasi yang sudah ada. Rencana ini tidak menambahkan janji pembaruan real-time, tidak mengubah kebijakan langganan dashboard, dan tidak membuat dashboard menjadi aplikasi offline penuh.

## 2. Hasil pemeriksaan kode dashboard

| Area | Temuan | Implikasi |
| --- | --- | --- |
| Stack | Laravel, Blade/Livewire, dan aset Vite. | Ikuti komponen, layanan, serta pola navigasi yang ada. |
| Jenis bisnis | `App\Enums\BusinessType` memiliki nilai canonical `grosir`, termasuk normalisasi alias retail/kelontong. | Kondisi UI dan endpoint menggunakan bisnis aktif yang dinormalisasi. |
| Database | Tabel `products` sudah memiliki `barcode` nullable serta unique index `(business_id, barcode)` dan `(business_id, sku)`. | Jangan menambah kolom barcode atau unique index yang sama lagi. |
| Model/form | Model `Product`, `ProductRequest`, dan form katalog sudah mendukung barcode terpisah dari SKU, maksimal 255 karakter. | Gunakan field yang ada; samakan batas panjang dengan mobile. |
| Daftar produk | `DashboardProductsData` sudah menyajikan barcode dan mendukung pencarian barcode. | Tambahkan aksi QR pada daftar/detail, tanpa menduplikasi data produk. |
| POS web | `PosCatalogData` sudah mengirim barcode; halaman POS mencocokkan pencarian sebagian nama/SKU/barcode. | Scanner membutuhkan resolver kecocokan persis, terpisah dari pencarian teks biasa. |
| Tambah keranjang | Halaman POS memiliki `addToCart()`, `openVariantModal()`, dan alur varian. | Hasil scan harus menggunakan alur tersebut. |
| Push | `SyncPushRequest` menerima barcode. `SyncPushService::processProducts()` saat ini memetakan field hilang/null menjadi `null`. | Payload lama dapat mengosongkan barcode produk yang sudah ada. Ini prioritas kompatibilitas. |
| Pull | `SyncPullService` sudah menyertakan barcode, `sync_id`, `sync_version`, dan `sync_sequence`. | Perkuat pengujian round-trip dan perubahan barcode saja. |
| Metadata | `HasSyncMetadata` mengelola versi/sequence saat model disimpan. | Perubahan kode harus lewat model/layanan yang menjaga metadata. |
| Izin | Pengelolaan produk memakai `products.manage`; `cashier_safe` melarang push produk. | Scan penjualan tidak boleh sekaligus memberi hak edit/generate kode produk kepada kasir. |
| QR | Belum ditemukan generator QR produk. QR autentikasi/QRIS adalah fitur lain. | Tambahkan generator khusus label produk. |

## 3. Ruang lingkup

### Wajib

- Memperbaiki semantik barcode pada push agar kompatibel dengan mobile lama.
- Menetapkan kontrak kode yang sama dengan mobile: string maksimal **255 karakter**, trim tepi, nol di depan tetap utuh, SKU dan barcode terpisah.
- Memperkuat validasi benturan kode pada dashboard dan push, termasuk barcode produk A yang sama dengan SKU produk B.
- Menambah generator kode internal stabil, pratinjau QR, dan unduh label PNG pada katalog grosir.
- Menambah scan kamera pada POS web grosir dan opsi scan untuk mengisi barcode pada form produk.
- Memastikan versi/sequence, konflik, retry, dan hak akses sinkronisasi tetap benar.
- Menjalankan QA dashboard ↔ mobile pada backend uji yang sama.

### Di luar tahap ini

Barcode per varian/kemasan, konversi dus ke satuan, scanner perangkat keras, cetak label massal/thermal, perubahan aturan harga grosir, WebSocket untuk katalog, dan implementasi offline penuh POS web.

## 4. Kontrak bersama dashboard dan mobile

### Identitas dan isi QR

- `sync_id` tetap menjadi identitas produk untuk sinkronisasi. Barcode tidak menjadi pengganti primary key atau `sync_id`.
- `sku` tetap mengikuti kontrak SKU yang ada, termasuk SKU sinkronisasi bentukan mobile.
- `barcode` menjadi kode scan utama, berisi barcode kemasan atau kode internal berprefix `NXP-` dengan UUID.
- Isi QR adalah nilai barcode sebagai teks biasa. Tidak memuat URL, harga, stok, data akun, atau ID lokal.
- Tidak menyimpan gambar QR di tabel produk. Bentuk gambar dari kode yang tersimpan saat diperlukan.
- Null pada server dinormalisasi mobile menjadi string kosong; nonempty string harus dipertahankan persis di kedua sisi.
- Pencocokan scan menggunakan kesamaan persis setelah trim tepi. Jangan mengubah menjadi angka atau memakai pencarian `LIKE`/`includes`.
- Produk dengan nama/harga baru tetap memiliki QR yang sama selama barcode tidak diganti secara sengaja.

### Semantik push yang diusulkan

**Kontrak berikut adalah usulan baru, belum tersedia pada backend.** Implementasikan dan dokumentasikan sebelum mobile baru menggunakannya.

Tambahkan `barcode_action` opsional pada setiap item `changes.products`, dengan nilai `set` atau `clear`:

| Payload barcode pada push | Produk sudah ada | Produk baru |
| --- | --- | --- |
| Field tidak dikirim, tanpa action | Pertahankan barcode. | Simpan tanpa barcode. |
| `null` atau string kosong, tanpa action | Pertahankan barcode; kompatibilitas mobile lama. | Simpan tanpa barcode. |
| String nonempty, tanpa action | Perlakukan sebagai set legacy, dengan validasi dan concurrency yang berlaku. | Simpan kode setelah validasi. |
| `barcode_action: set` + string nonempty | Set/ganti kode secara eksplisit setelah validasi. | Simpan kode setelah validasi. |
| `barcode_action: clear` + `barcode: null` | Kosongkan secara eksplisit setelah validasi dan otorisasi. | Tolak sebagai kombinasi yang tidak diperlukan untuk create. |
| Action tidak dikenal atau kombinasi tidak sah | Tolak melalui error validasi terstruktur. | Tolak melalui error validasi terstruktur. |

Contoh **item produk**, bukan envelope push lengkap:

```json
{
  "sync_id": "b2869d5a-0c84-4d41-9229-795a1e6b8a3c",
  "base_sync_version": 4,
  "name": "Beras 5 kg",
  "sku": "SKU-SYNC-YANG-SUDAH-ADA",
  "barcode": "0891234567890",
  "barcode_action": "set",
  "price": 65000,
  "kind": "product",
  "status": "active"
}
```

Keputusan lanjutan:

- Tambahkan penanda kemampuan additive pada objek bisnis di `/api/mobile/context`, dengan usulan `catalog_capabilities.product_barcode_v1: true`. Field ini menandakan dukungan kontrak, bukan izin mengedit produk.
- Pertahankan `sync_capabilities`, `cashier_sync_v1`, serta `push_mode` yang berlaku; jangan mengganti kontrak kasir hanya karena generator QR ditambahkan.
- Mobile memeriksa kemampuan backend sebelum mengirim `barcode_action`. Bila kemampuan belum tersedia, jangan melakukan pengosongan barcode melalui sync.
- Mobile baru mengirim action berdasarkan maksud perubahan produk; jangan menyimpulkan clear hanya karena field lama kosong.
- Pull selalu menyertakan barcode; `null` dari server baru adalah kondisi kosong yang authoritative. Field yang hilang pada respons lama tidak boleh ditafsirkan mobile sebagai perintah menghapus kode.
- Jangan otomatis membuat barcode pada proses push legacy yang tidak membawa barcode. Generate harus berasal dari aksi/form yang jelas agar retry tidak menghasilkan kode berbeda.
- Perbarui dokumentasi kedua repositori dengan nama field final yang sama sebelum implementasi klien.

## 5. Validasi, transaksi, dan kompatibilitas data

1. Gunakan aturan kode bersama di service backend yang dipanggil dari dashboard, generate QR, dan push.
2. Tolak kode kosong untuk action set, panjang lebih dari 255 karakter, input non-string, serta karakter kontrol yang tidak didukung. Trim tepi tanpa mengubah isi kode.
3. Periksa kode dalam lingkup `business_id` aktif, termasuk produk inactive/deleted sesuai kebijakan unique yang sudah ada; jangan memperkenalkan pemakaian ulang kode secara diam-diam.
4. Saat SKU/barcode diubah, periksa benturan lintas kolom dengan produk lain. Field SKU dan barcode pada produk yang sama boleh bernilai sama.
5. Audit data lama sebelum rollout: barcode kosong berbentuk string, barcode/SKU silang, serta perbedaan collation database dengan resolver string mobile. Laporkan benturan; jangan mengganti kode otomatis.
6. Unique index barcode yang ada tetap menjadi pengaman database. Lengkapi perlindungan race untuk benturan lintas SKU/barcode dengan mekanisme penguncian konsisten dalam transaksi semua jalur perubahan kode, termasuk perubahan SKU.
7. Pastikan dua permintaan generate terhadap produk yang sama mengembalikan kode yang sama: lock record, cek apakah kode sudah ada, lalu generate hanya bila kosong.
8. Perubahan barcode harus menaikkan metadata sync satu kali melalui save yang benar. Pratinjau/unduh QR dan generate ulang atas kode yang sama tidak menambah versi.
9. Pengecualian duplicate/concurrency harus mengikuti respons terstruktur yang sudah dipakai, tanpa membocorkan produk bisnis lain atau menjadi error 500 mentah.
10. Pertahankan atomicity envelope push. Jika satu item konflik, jangan mengakui seluruh envelope berhasil sambil menyimpan sebagian perubahan di luar kontrak.
11. Hormati `base_sync_version`, `request_id`, deduplikasi, dan rekonsiliasi status request yang ada. Jangan mengubah envelope tersimpan yang status penerimaannya belum pasti saat upgrade klien.

Migrasi dashboard hanya dibuat bila audit menunjukkan kebutuhan penyesuaian panjang, collation, atau mekanisme pengamanan tambahan. Kolom barcode dasarnya sudah tersedia.

## 6. Alur dashboard

### A. Form produk grosir

- Pertahankan kolom SKU dan barcode yang sudah ada; tambahkan bantuan **Barcode kemasan atau kode scan produk**.
- Tambahkan ikon kamera untuk mengisi barcode, dengan input manual tetap tersedia.
- Scan pada form hanya mengisi field; penyimpanan tetap melalui submit dan validasi produk.
- Untuk produk grosir baru yang dapat dijual dan barcode kosong, siapkan kode internal dalam transaksi penyimpanan yang berhasil.
- Pada update, bedakan field tidak diubah dari permintaan mengosongkan. Jangan mengganti kode karena pengguna hanya mengubah harga/nama.
- Jangan memaksa barcode atau generator pada layanan Laundry maupun bahan baku yang tidak dijual.

### B. QR produk

- Tambahkan aksi ringkas pada daftar/detail produk grosir: **Lihat QR** jika barcode ada, **Buat Kode & QR** jika belum ada dan pengguna berhak mengelola produk.
- Pratinjau dan unduh untuk barcode yang sudah tersedia adalah operasi baca; tidak boleh menulis melalui GET.
- Aksi generate/assign menggunakan POST yang dilindungi CSRF, permission `products.manage`, dan konteks bisnis aktif dari server.
- Endpoint generate mengambil produk dengan scope bisnis; ID produk tenant lain tidak dapat digunakan.
- Generate terhadap produk berkode tidak menimpa barcode kemasan. Return barcode yang sudah ada.
- Pratinjau menampilkan nama produk, kode yang dapat dibaca, QR dengan ruang putih, dan **Unduh PNG**.
- Render QR menggunakan dependency yang dibundel Vite atau renderer server yang dipilih setelah uji; tidak memanggil layanan QR pihak ketiga/CDN runtime.
- Mengganti/mengosongkan kode memerlukan aksi jelas dengan penjelasan bahwa label lama perlu diganti.

### C. Scan POS web

- Tombol **Scan** tampil di dekat pencarian hanya jika tipe bisnis aktif adalah `grosir` dan pengguna memang dapat membuka POS.
- Minta kamera ketika tombol diketuk; pilih kamera belakang jika tersedia dan gunakan decoder lokal yang lolos pengujian browser.
- Target format sama dengan mobile: QR Code, EAN-13, EAN-8, UPC-A, UPC-E, dan Code 128.
- Satu hasil scan menghentikan sesi. Cari kecocokan barcode/SKU persis pada katalog aktif bisnis, bukan hasil filter yang sedang terlihat.
- Produk ditemukan: teruskan ke `addToCart()`; pilihan varian tetap melalui `openVariantModal()` dan konfirmasi yang berlaku.
- Cegah pembacaan beberapa frame menambahkan banyak item. Scan sesi berikutnya boleh menambah kuantitas produk yang sama.
- Kode ambigu, tidak dikenal, atau stok habis tidak menambah produk; tampilkan pesan singkat dan opsi mencoba lagi.
- Stop kamera/listener pada tutup, route/navigation Livewire, pergantian bisnis/outlet, dan tab masuk background. Abaikan callback sesi lama.
- Browser tanpa kamera/izin ditolak tetap dapat memakai pencarian dan input manual. Uji akses kamera pada HTTPS atau konteks lokal yang didukung.
- Scan tidak mengirim gambar kamera ke backend. Checkout tetap memvalidasi harga, stok, shift, varian, serta hak akses di server.

## 7. Hak akses dan batas tenant

| Aksi | Aturan |
| --- | --- |
| Scan dan tambah keranjang | Mengikuti akses POS dan `POS_SELL` yang sudah berlaku. |
| Lihat/unduh QR yang sudah ada | Mengikuti akses baca produk serta lingkup bisnis aktif. |
| Membuat, mengganti, atau menghapus kode | Mengikuti `PRODUCTS_MANAGE`; tidak diberikan otomatis kepada cashier. |
| Push perubahan kode dari mobile | Hanya mode/role yang sudah diizinkan mengubah produk. |
| Cashier-safe sync | Tetap menolak mutasi produk. Kasir menarik katalog/kode yang sudah dibuat pengelola. |
| Bisnis Cafe/Laundry | Perilaku barcode yang sudah ada tetap kompatibel; UI scanner/generator grosir tidak muncul. |

Penanda kemampuan kontrak tidak melewati verifikasi role, device, bisnis, atau entitlement. Kode boleh sama pada bisnis yang berbeda; endpoint dan sync tidak boleh mencocokkan produk lintas bisnis.

## 8. Peta file implementasi

| File/area | Rencana perubahan |
| --- | --- |
| `app/Services/Sync/SyncPushService.php` | Semantik preserve/set/clear dan validasi kode dalam transaksi. |
| `app/Http/Requests/Api/SyncPushRequest.php` | Validasi action dan kombinasi field yang diperbolehkan. |
| `app/Services/Sync/SyncPullService.php` | Pertahankan barcode terpisah dan uji perubahan barcode saja pada incremental pull. |
| `app/Http/Controllers/Api/MobileContextController.php` | Penanda kemampuan additive kontrak barcode. |
| `app/Services/Dashboard/CatalogManagementService.php` | Persistensi kode stabil, preserve saat edit field lain, dan penggunaan validator bersama. |
| `app/Http/Requests/Dashboard/ProductRequest.php` | Normalisasi, batas 255, serta benturan lintas SKU/barcode. |
| `app/Services/Catalog/ProductCodeService.php` (usulan baru) | Generate kode, validasi bersama, dan assign idempotent. |
| `app/Http/Controllers/Dashboard/ProductsController.php`, `routes/web.php` | Aksi generate yang terotorisasi; akses preview/unduh sesuai rancangan renderer. |
| `app/Services/Dashboard/DashboardProductsData.php` | Permission/kondisi UI QR yang diperlukan. |
| `resources/views/products/index.blade.php` dan komponen produk | Aksi QR, pratinjau, serta integrasi form. |
| `resources/js/` dan komponen scanner/QR baru | Modul scan, resolver, lifecycle, dan ekspor label; nama final mengikuti pola proyek. |
| `app/Services/Pos/PosCatalogData.php`, `resources/views/pos/index.blade.php` | Kondisi grosir dan integrasi scanner dengan keranjang. |
| `tests/Feature/` dan tes frontend yang tersedia | Kontrak sync, hak akses, generate, resolver, dan regresi POS. |
| `docs/sync/` dan `docs/qa/` | Kontrak final serta bukti integrasi dashboard-mobile. |

## 9. Urutan pengerjaan dan rollout

1. **Bekukan kontrak bersama.** Selaraskan batas 255, isi QR, prefix kode, `barcode_action`, kemampuan backend, dan aturan hak akses pada kedua plan.
2. **Audit data dan baseline.** Periksa kode duplikat/collation, tes sync/produk yang sudah ada, serta perilaku envelope pada retry.
3. **Rilis backend kompatibilitas lebih dahulu.** Lindungi barcode dari null/missing payload lama; tambahkan action, validasi bersama, capability, dan tes. Mobile lama harus tetap dapat sync tanpa menghapus kode.
4. **Implementasi katalog dan QR dashboard.** Assign kode stabil, pratinjau, unduh PNG, serta permissions. Jangan backfill barcode massal saat halaman dibuka.
5. **Implementasi mobile sesuai plan pasangan.** Persistensi barcode terpisah, action/capability, generate QR, scanner, dan mapper push/pull.
6. **Implementasi scanner POS web.** Gunakan katalog dan alur keranjang yang sama; uji izin serta navigasi.
7. **Uji lintas aplikasi.** Backend uji, dashboard, mobile baru, dan setidaknya simulasi payload mobile lama.
8. **Aktifkan fitur bertahap setelah QA lulus.** Catat versi dukungan dan cara memperbaiki kode bentrok. Rollback UI tetap mempertahankan data barcode serta perlindungan null legacy pada backend.

## 10. Matriks pengujian penerimaan

| Skenario | Hasil wajib |
| --- | --- |
| Barcode dibuat di dashboard → mobile pull → scan | Produk benar masuk keranjang mobile. |
| QR dibuat mobile berizin → push → dashboard | Barcode yang sama muncul dan QR dashboard dapat dipindai. |
| Dashboard mengganti harga/nama saja | Barcode dan QR tetap sama. |
| Mobile lama mengirim null/missing barcode | Barcode server tetap ada, termasuk ketika field produk lain berubah. |
| Mobile baru set/clear dengan versi sesuai | Perubahan diterima dan terlihat pada pull berikutnya. |
| Clear tanpa action, action tidak dikenal, tipe salah | Perilaku mengikuti kontrak; tidak ada pengosongan tak sengaja. |
| Dua perangkat mengubah produk dari versi lama | Konflik terstruktur, tanpa overwrite diam-diam. |
| Retry request/generate dan double click | Tidak menghasilkan kode baru, produk ganda, atau kenaikan versi berulang. |
| Hanya barcode berubah | Incremental pull mengirim perubahan sesuai sequence. |
| Duplicate dalam satu bisnis/lintas SKU-barcode | Ditolak dengan pesan yang sesuai, termasuk race dua request. |
| Kode sama pada dua bisnis | Diperbolehkan sesuai scope; tidak ada akses silang. |
| Kode bernol di depan | Nilai tetap identik pada form, DB, push, pull, label, dan scanner. |
| Cashier mencoba generate/edit/push produk | Ditolak; akses scan penjualan yang sah tetap berfungsi. |
| QR dibuka/diunduh beberapa kali | Tidak mengubah record atau metadata sync. |
| Scan POS web dengan varian/filter aktif | Produk dicocokkan dari katalog; varian dan kuantitas mengikuti alur yang benar. |
| Izin ditolak, tab disembunyikan, navigasi/tenant berubah | Kamera berhenti; tidak ada callback lama menambah keranjang. |
| QR/barcode tidak dikenal atau ambigu | Tidak ada produk yang ditambahkan otomatis. |
| Backup/restore mobile lalu sync | Kode dan identitas produk tidak berubah; konflik mengikuti aturan yang ada. |
| Cafe, Laundry, QRIS, dan QR autentikasi | Tidak mengalami regresi dari perubahan QR produk grosir. |

Jalankan tes produk/pos/sync yang relevan, `lint:check`, pemeriksaan tipe, build aset, serta tes integrasi database uji. Pengujian race/unique/collation perlu dilakukan pada engine database yang sama dengan deployment, tidak hanya pada mock atau SQLite pengujian.

## 11. Definisi selesai

- [ ] Kontrak barcode final terdokumentasi dan sama pada dashboard/mobile.
- [ ] Backend melindungi barcode dari payload null mobile lama.
- [ ] Generate kode bersifat stabil, terotorisasi, dan tidak menimpa barcode kemasan.
- [ ] QR produk dashboard dan mobile berisi kode yang sama dan dapat dipindai silang.
- [ ] Scanner POS web grosir memakai alur keranjang/varian yang sudah ada.
- [ ] Role cashier dan lingkup bisnis tetap ditegakkan di server.
- [ ] Round-trip, incremental pull, concurrency, retry, serta backup/restore diuji.
- [ ] Tes, build, dan QA browser/Android tercatat; keterbatasan yang belum diuji disebutkan.
- [ ] Urutan rollout backend → dashboard/mobile dan langkah rollback yang mempertahankan kode disiapkan.
