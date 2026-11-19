# ⚠️ PANDUAN KESELAMATAN DATABASE & MIGRATION (PENTING BAGI SELURUH DEVELOPER / AI AGENT)

> [!CAUTION]
> **DILARANG KERAS MENJALANKAN PERINTAH DESTRUKTIF:**
> * `php artisan migrate:fresh`
> * `php artisan migrate:refresh`
> * `php artisan db:wipe`
> * `php artisan migrate:reset`
> 
> **Database sistem ini sudah berjalan di lingkungan PRODUCTION dan berisi data transaksi nyata, riwayat kasir, outlet, dan pelanggan yang AKTIF.** Menjalankan perintah di atas akan menghapus seluruh data produksi secara permanen!

---

## 1. Aturan Penambahan & Perubahan Skema Database

1. **Selalu Buat File Migration Baru (Additive Only):**
   * Jangan pernah mengedit file migration yang sudah pernah dijalankan di production jika perubahan tersebut bersifat destruktif.
   * Gunakan perintah:
     ```bash
     php artisan make:migration add_<nama_kolom>_to_<nama_tabel>_table --table=<nama_tabel>
     ```

2. **Gunakan Pengecekan Keberadaan Kolom / Tabel:**
   Selalu bungkus penambahan kolom atau indeks dengan pengecekan aman:
   ```php
   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration
   {
       public function up(): void
       {
           Schema::table('nama_tabel', function (Blueprint $table) {
               if (!Schema::hasColumn('nama_tabel', 'nama_kolom_baru')) {
                   $table->string('nama_kolom_baru')->nullable()->after('kolom_sebelumnya');
               }
           });
       }

       public function down(): void
       {
           Schema::table('nama_tabel', function (Blueprint $table) {
               if (Schema::hasColumn('nama_tabel', 'nama_kolom_baru')) {
                   $table->dropColumn('nama_kolom_baru');
               }
           });
       }
   };
   ```

3. **Kolom Baru Wajib Nullable atau Memiliki Nilai Default:**
   * Karena tabel sudah memiliki ribuan baris data, kolom baru **TIDAK BOLEH** bersifat `NOT NULL` tanpa nilai `default()`.
   * Selalu gunakan `->nullable()` atau `->default(...)` agar baris data lama tidak menyebabkan query error saat migrasi dijalankan.

4. **Jangan Pernah Menghapus Kolom / Tabel Tanpa Izin:**
   * Dilarang melakukan `dropColumn`, `dropTable`, atau mengubah tipe data yang berisiko memotong data (*data truncation*) tanpa instruksi dan persetujuan eksplisit dari Owner sistem.

5. **Backup Database Sebelum Migrasi:**
   * Sebelum mengeksekusi `php artisan migrate` di server atau lingkungan lokal yang tersinkronisasi, pastikan salinan dump SQL database telah dicadangkan.

---

## 2. Ringkasan Perintah yang Aman
* ✅ `php artisan migrate` (hanya menjalankan migrasi baru yang belum pernah dieksekusi).
* ✅ `php artisan migrate:status` (mengecek status migrasi).
* ❌ `php artisan migrate:fresh` (**DILARANG KERAS**).
* ❌ `php artisan migrate:refresh` (**DILARANG KERAS**).
