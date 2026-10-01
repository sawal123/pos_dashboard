# PREM-D02C — Database-Managed Premium Plans & Pricing

## Tujuan

Sumber harga Premium dipindahkan dari konfigurasi/ENV ke **database** agar
Platform Admin bisa mengubah harga tanpa mengubah source code. Server tetap
menjadi satu-satunya penentu harga; client tidak pernah mengirim harga.

## Schema

### `subscription_plans`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `code` | string | **unique**, canonical `cloud` |
| `name` | string | display name, mis. `Cloud` |
| `description` | text nullable | |
| `is_active` | boolean | default `true` |
| `created_at` / `updated_at` | timestamp | |

### `subscription_plan_prices`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `subscription_plan_id` | FK → `subscription_plans` | `cascadeOnDelete` |
| `billing_period` | string | canonical `monthly` / `yearly` |
| `currency` | char(3) | canonical `IDR` |
| `price_minor` | unsigned bigint | integer minor unit |
| `is_active` | boolean | default `true` |
| `created_at` / `updated_at` | timestamp | |

Constraint:

- `unique(subscription_plan_id, billing_period, currency)` — mencegah dua harga
  ambigu untuk penawaran yang sama.

Struktur ini sudah cukup untuk task Platform Admin berikutnya ("Paket & Harga")
tanpa perubahan schema: melihat harga, mengubah harga, enable/disable harga,
serta enable/disable plan (`is_active`).

## Canonical plan

- `code = cloud`, `name = Cloud`, `is_active = true`.
- Billing period kanonik: `monthly`, `yearly` (dari `PremiumPolicy`).
- Currency kanonik: `IDR`.

## Dummy prices (BUKAN harga resmi)

Seeder development (`SubscriptionPlanSeeder`) mengisi **placeholder**:

- Cloud Monthly: `49000` IDR minor (Rp49.000)
- Cloud Yearly: `490000` IDR minor (Rp490.000)

> **Nominal tersebut adalah DUMMY / PLACEHOLDER, BUKAN harga bisnis final.**
> Jangan dipakai sebagai harga resmi dan jangan di-hardcode di mobile.

Aturan seeder:

- Idempotent dengan `firstOrCreate` (aman dijalankan berkali-kali).
- Harga dummy hanya ditulis di environment `local` / `testing`. Production
  **tidak** diisi harga dummy — checkout tetap fail-closed sampai admin
  menetapkan harga resmi.
- Existing price **tidak pernah ditimpa**. Initial/demo seeding terpisah dari
  mutasi harga (mutasi harga adalah tugas admin, bukan seeder).

## Price storage

Integer minor unit. Untuk IDR tidak ada konversi: `Rp49.000 → price_minor = 49000`.
Tidak ada float/decimal untuk amount checkout.

## Server-authoritative pricing

`App\Services\Subscription\PremiumPricing` membaca database:

- `priceFor($plan, $period)` → `{ currency, price_minor }` hanya jika: plan aktif,
  price aktif, billing period didukung policy, `price_minor > 0`, currency ada.
- `periodsFor($plan)` → daftar periode aktif sesuai urutan policy.
- `hasActivePrice($plan)` → apakah plan punya minimal satu harga aktif.
- `isConfigured()` → apakah ada harga aktif.

Jika data tidak ada / nonaktif → **fail closed** (`null` / list kosong). Tidak ada
harga yang pernah "ditebak".

## Checkout

`SubscriptionCheckoutService` mengambil harga langsung dari `PremiumPricing`
(database). Client **tidak boleh** mengirim `amount`, `currency`, atau
`price_minor`; field tersebut `prohibited` dan request ditolak `422`.

`checkout_available` (GET `/api/mobile/subscription/plans`) hanya `true` bila:

1. canonical Cloud plan tersedia (aktif),
2. harga `monthly` **dan** `yearly` tersedia/valid, dan
3. Midtrans configured.

## Historical payment snapshot

Setiap `subscription_payments` menyimpan snapshot immutable:
`plan`, `billing_period`, `currency`, `amount`.

Tidak ada relasi live dari payment ke harga saat ini. Jika admin menaikkan harga
`49000 → 59000`:

- payment lama tetap `49000`,
- checkout baru mendapat `59000`.

## Fail-closed behavior

- Plan tidak ada / nonaktif → `priceFor` `null`, catalog kosong/`available=false`,
  `checkout_available` `false`.
- Price nonaktif → periode tidak ditawarkan.
- Billing period tidak didukung → diabaikan.
- Harga belum di-set (production) → catalog kosong, checkout `409
  CHECKOUT_UNAVAILABLE`.

## Config cleanup

- `config/premium.php` tidak lagi punya key `pricing`.
- ENV `PREMIUM_PRICING_CONFIGURED`, `PREMIUM_CLOUD_MONTHLY_PRICE_MINOR`,
  `PREMIUM_CLOUD_YEARLY_PRICE_MINOR` dihapus dari `.env.example`.
- `benefits` tetap product-policy copy (`premium.plan.benefits`), bukan harga —
  DB otoritatif hanya untuk plan/period/currency/price/active.
- Midtrans credentials tetap di ENV (`MIDTRANS_*`), tidak dipindah ke database.

## Mengubah harga

Saat ini (tanpa UI): ubah row `subscription_plan_prices.price_minor` untuk plan
dan periode terkait. Ke depan, Platform Admin UI "Paket & Harga" akan memakai
model `SubscriptionPlan` / `SubscriptionPlanPrice` (`is_active` untuk
enable/disable) tanpa perubahan schema.

## Rencana Platform Admin pricing UI

Task berikutnya dapat menambahkan UI untuk:

- melihat plan & harga,
- mengubah `price_minor`,
- enable/disable price (`subscription_plan_prices.is_active`),
- enable/disable plan (`subscription_plans.is_active`),

semua sudah didukung schema ini.
