# ADMIN-06 — Platform Admin Premium Plan & Pricing

## Ringkasan

Halaman Platform Admin untuk mengelola **paket Premium Cloud** dan **harga
Bulanan/Tahunan** yang sudah disimpan di database oleh PREM-D02C.

> Harga yang saat ini ada di development kemungkinan besar **DUMMY / PLACEHOLDER**
> (lihat `SubscriptionPlanSeeder`: `49000` / `490000`). Itu **bukan** harga
> bisnis final. **Platform Admin bertanggung jawab menetapkan harga production.**
> UI menampilkan nilai database apa adanya.

Scope sengaja sempit: hanya paket kanonik `cloud` + harga Monthly/Yearly `IDR`.
Tidak ada CRUD paket bebas, tidak menyentuh entitlement, webhook Midtrans,
status mapping, renewal, device limit, Cloud Login, mobile checkout, atau cloud
backup/restore.

## Route

Semua di dalam grup `platform` (prefix `/platform`, middleware
`auth` + `verified` + `platform.admin`):

| Method | Path | Name | Aksi |
| --- | --- | --- | --- |
| GET | `/platform/subscription-plans` | `platform.subscription-plans.index` | Daftar paket & harga |
| GET | `/platform/subscription-plans/{plan}` | `platform.subscription-plans.show` | Detail & form edit |
| PATCH | `/platform/subscription-plans/{plan}` | `platform.subscription-plans.update` | Ubah nama/deskripsi/aktif paket |
| POST | `/platform/subscription-plans/{plan}/prices` | `platform.subscription-plans.prices.store` | Buat harga yang belum ada |
| PATCH | `/platform/subscription-plans/{plan}/prices/{price}` | `platform.subscription-plans.prices.update` | Ubah harga/aktif harga |

Mutasi memakai **ID resource** (`{plan}`, `{price}`), bukan string billing period.
Route harga memakai `scopeBindings()` sehingga `{price}` wajib milik `{plan}`
(jika tidak → 404). Pembuatan harga pertama memakai endpoint koleksi dengan
`billing_period` kanonik tervalidasi (`monthly`/`yearly`).

## Authorization

- `EnsurePlatformAdmin` → `abort(403)` untuk semua non-Platform-Admin.
- Business owner / member / user biasa → **403**.
- Guest → redirect ke `route('login')` (middleware `auth`).
- Otorisasi server-side; sidebar hanya kosmetik.

## UI

- **Sidebar**: menu **Paket & Harga** (icon `tags`) tepat setelah **Langganan**,
  aktif pada `platform.subscription-plans.*`.
- **Index**: panel *Checkout readiness* (hijau "Checkout siap" / kuning "Checkout
  belum siap" + alasan faktual), kartu paket dengan Nama, Kode, status paket,
  harga Bulanan & Tahunan (format Rupiah, mis. `Rp 49.000`), dan "Terakhir
  diperbarui".
- **Show**: form Detail Paket (nama, deskripsi, aktif), form Harga Bulanan dan
  Harga Tahunan (nilai minor + toggle aktif), plus panel readiness.
- Flash `session('status')`; validasi inline `@error(...)`; konfirmasi
  `onsubmit confirm()` saat menonaktifkan paket. Tanpa Alpine (mengikuti pola
  Platform Admin existing).

## Update plan

Boleh mengubah `name`, `description`, `is_active`. **`code` immutable** —
ditampilkan read-only dan ditolak (`prohibited`) bila dikirim; paket tidak pernah
dihapus.

## Update price

- Input memakai nilai Rupiah manusiawi = integer minor (`59000` ⇒ `price_minor =
  59000`). Tidak ada float.
- Validasi: `integer`, `min:1`, `max:1000000000`.
- `currency` tetap `IDR`, `billing_period` tetap milik baris tersebut —
  keduanya `prohibited` dari body mutasi.
- Enable/disable memakai `is_active`; baris **tidak pernah dihapus**.

## Missing price row

Production bisa punya paket `cloud` tanpa baris harga. Halaman tetap menampilkan
form; menyimpan harga valid akan membuat baris `subscription_plan_prices` dengan
`billing_period` (`monthly`/`yearly`) dan `currency = IDR` kanonik. Tidak perlu
membuat baris manual di database.

## Checkout readiness

Readiness berasal dari `App\Services\Subscription\SubscriptionCheckoutService::readiness()`
(sumber tunggal aturan yang juga menentukan `checkout_available`). Bukan aturan
yang diduplikasi di Blade.

Contoh alasan faktual:

- `Paket Cloud belum tersedia.`
- `Paket Cloud sedang nonaktif.`
- `Harga Bulanan belum tersedia atau nonaktif.`
- `Harga Tahunan belum tersedia atau nonaktif.`
- `Midtrans belum dikonfigurasi.`

Checkout hanya "siap" bila paket cloud aktif, harga Monthly **dan** Yearly aktif
& valid, dan Midtrans terkonfigurasi. Tidak ada credential yang ditampilkan.

## Perlindungan snapshot payment historis

Mengubah harga **tidak** mengubah `subscription_payments.amount`, `.currency`,
`.billing_period`, atau snapshot historis apa pun. Payment adalah snapshot
immutable: payment lama tetap `49000`, checkout baru mendapat harga baru. Ada
regression test untuk ini.

## Security

- Otorisasi server-side (middleware platform admin) di semua route.
- Field kanonik immutable: `code`, `billing_period`, `currency`.
- Mass assignment dibatasi: service hanya `forceFill` field yang diizinkan.
- Service domain (`SubscriptionPlanAdministrationService`) memvalidasi invariant
  dan memakai `DB::transaction`.
- Tidak menyimpan/menampilkan Midtrans credential atau provider payload sensitif.

## Audit logging

Belum ada audit log Platform Admin di project ini (yang ada hanya
`MembershipAuditLogger` untuk keanggotaan bisnis). Task ini **tidak** membuat
framework audit baru. **Follow-up**: catat perubahan harga/status via mekanisme
audit platform saat tersedia.

## Known limitations

- Hanya paket kanonik `cloud`; tidak ada pembuatan paket baru dari UI.
- Currency terkunci `IDR`; billing period terkunci `monthly`/`yearly`.
- Belum ada audit trail perubahan harga/status.
- Harga dev saat ini dummy/placeholder — wajib ditetapkan Platform Admin sebelum
  production.
