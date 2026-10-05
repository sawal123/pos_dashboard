# Platform Admin Security Hardening (ADMIN-16)

Dokumentasi arsitektur keamanan, boundary hak akses, mitigasi risiko, dan kebijakan hardening untuk surface **Platform Admin** pada repository `pos_dashboard`.

---

## 1. Model Otorisasi Platform Admin

1. **Privilege Boundary Global**:
   - Hak akses Platform Admin dimodelkan secara terpisah dan orthogonal dari RBAC merchant (`business_user.role`).
   - Flag otoritatif: `users.is_platform_admin = true`.
   - Business owner, manager, kasir, atau user bisnis dengan level langganan Cloud tertinggi **tetap ditolak (403 Forbidden)** dari seluruh route `/platform/*`.
   - Menjadi Platform Admin tidak memberikan keanggotaan otomatis ke dalam bisnis merchant manapun.
   - Context bisnis (`dashboard.current_business_id`) didecouple sepenuhnya dari platform console.

2. **Deny-by-Default Authorization Pipeline**:
   Setiap request ke route group `/platform/*` wajib melewati urutan middleware berjenjang:
   ```text
   auth -> verified -> platform.admin -> platform.admin.2fa -> platform.security.headers
   ```
   - `auth`: Memastikan user terotentikasi via session guard. Guest diarahkan ke halaman login.
   - `verified`: Memastikan alamat email telah diverifikasi. Admin yang belum terverifikasi diarahkan ke `verification.notice`.
   - `platform.admin` (`EnsurePlatformAdmin`): Memvalidasi `isPlatformAdmin() === true`. Non-admin langsung ditolak dengan status HTTP 403.
   - `platform.admin.2fa` (`EnsurePlatformAdminTwoFactor`): Memvalidasi bahwa akun admin memiliki 2FA aktif dan terkonfirmasi (`two_factor_secret` dan `two_factor_confirmed_at`). Jika belum, diarahkan ke alur setup.
   - `platform.security.headers` (`PlatformSecurityHeaders`): Menambahkan header keamanan HTTP dan cache-control pada setiap response platform.

---

## 2. Kebijakan Autentikasi Dua Faktor (2FA)

1. **Enforcement TOTP 2FA**:
   - Platform Admin wajib memiliki TOTP 2FA yang terkonfirmasi (`two_factor_confirmed_at IS NOT NULL`) sebelum diizinkan mengakses `/platform/*`.
   - Passkey (WebAuthn) tetap didukung untuk login praktis, namun tidak menggantikan kewajiban aktivasi TOTP 2FA bagi Platform Admin.

2. **Alur Setup & Proteksi Redirect Loop**:
   - Platform Admin yang belum mengaktifkan atau belum mengonfirmasi 2FA akan dialihkan (`302 Redirect`) ke halaman `route('security.edit')` (`/settings/security`) dengan pesan:
     > *"Platform Admin wajib mengaktifkan autentikasi dua faktor sebelum mengakses konsol platform."*
   - Route `/settings/security` berada di luar prefix `/platform` dan **tidak** diberi middleware `platform.admin.2fa`, sehingga tidak terjadi redirect loop.
   - Admin dapat melakukan login, konfirmasi password, mengaktifkan 2FA melalui authenticator app, dan mengonfirmasi kode OTP.
   - Setelah 2FA berstatus `confirmed`, request berikutnya ke `/platform` akan diizinkan (HTTP 200).

3. **Freshness & Revokasi Instan**:
   - Status 2FA dibaca langsung dari state database user terkini per request (tidak dicache pada session flag).
   - Jika admin menonaktifkan 2FA melalui Security Settings, request berikutnya ke `/platform/*` seketika ditolak dan dialihkan kembali ke halaman setup 2FA.
   - Jika hak `is_platform_admin` dicabut (`false`), request berikutnya seketika menerima HTTP 403 Forbidden.

4. **Zero Bypass**:
   - Tidak ada query string bypass (`?skip_2fa=1`), header bypass (`X-Bypass-2FA`), maupun env switch untuk mengabaikan 2FA Platform Admin.

---

## 3. Rate Limiting Mutasi Berisiko Tinggi

1. **Throttling Login & 2FA (Existing Fortify)**:
   - Login rate limiter: 5 percobaan per menit per kombinasi username dan IP (`login`).
   - Two-factor challenge limiter: 5 percobaan per menit per session ID (`two-factor`).
   - Passkeys limiter: 10 percobaan per menit per kredensial/session (`passkeys`).

2. **Platform Mutation Rate Limiter**:
   - Didaftarkan secara terpusat di `AppServiceProvider` dengan nama: `platform-admin-mutations`.
   - Limit: **30 request per menit** per authenticated user ID (dengan fallback ke IP address).
   - Diterapkan secara selektif hanya pada route mutasi berisiko tinggi (POST/PATCH):
     - `PATCH /platform/businesses/{business}/status`
     - `PATCH /platform/subscriptions/{subscription}/activate`
     - `POST /platform/subscriptions/{subscription}/renew`
     - `PATCH /platform/subscriptions/{subscription}/downgrade`
     - `PATCH /platform/subscriptions/{subscription}/inactivate`
     - `PATCH /platform/subscription-plans/{plan}`
     - `POST /platform/subscription-plans/{plan}/prices`
     - `PATCH /platform/subscription-plans/{plan}/prices/{price}`
     - `PATCH /platform/devices/{device}/deactivate`
     - `PATCH /platform/devices/{device}/activate`
     - `PATCH /platform/settings/{setting}`
   - Route pembacaan (GET) tidak dikenakan limiter mutasi ini, menjaga kecepatan observabilitas dashboard.
   - Rejection status: HTTP 429 Too Many Requests (tidak menghasilkan record audit log palsu/gagal).

---

## 4. Keamanan Session & Proteksi Fixation

1. **Session Driver**:
   - Default stack: database session driver (`SESSION_DRIVER=database`).
   - JSON serialization diaktifkan (`SESSION_SERIALIZATION=json`) untuk mencegah PHP object deserialization gadget attacks.

2. **Revokasi Perangkat Lain (Session Revocation)**:
   - Pada pembaruan kata sandi di Security Settings, dipanggil canonical `Auth::logoutOtherDevices($password)`.
   - Jika session backend mendukung, semua session aktif lain untuk user tersebut akan diinvaliasi secara aman.

3. **Demotion & State Tampering**:
   - Pengecekan authorization `isPlatformAdmin()` dan 2FA berjalan per HTTP request. Perubahan role langsung efektif tanpa menunggu session timeout.

---

## 5. Pencegahan IDOR & Integritas Resource

1. **Nested Resource Binding Scope**:
   - Route `subscription-plans/{plan}/prices/{price}` dilindungi dengan `scopeBindings()`.
   - Memodifikasi Price milik Plan A melalui URL Plan B akan langsung menghasilkan **404 Not Found** tanpa mutasi DB dan tanpa audit log.

2. **Canonical Plan Isolation**:
   - Route manajemen paket platform membatasi akses hanya ke canonical Cloud plan. Percobaan mengakses ID paket non-cloud menghasilkan **404 Not Found**.

3. **Platform Settings Whitelist**:
   - Hanya key yang terdaftar di `PlatformSettingDefinition` yang dapat dimutasi runtime. Percobaan mengirim slug tidak dikenal menghasilkan **404 Not Found**.
   - Input dilindungi validasi ketat, atribut internal (`key`, `updated_by`) tidak dapat di-overwrite via mass-assignment.

4. **Audit Log Immutability**:
   - Platform Audit Log adalah append-only. Tidak ada route POST, PATCH, atau DELETE untuk audit log.
   - Input manipulasi identitas aktor (`actor_user_id`, `actor_email`, `actor_name`) diabaikan; aktor selalu diambil langsung dari `$request->user()`.

---

## 6. HTTP Security & Cache-Control Headers

Diterapkan oleh middleware `PlatformSecurityHeaders` pada seluruh response `/platform/*`:

| Header | Nilai | Tujuan |
|---|---|---|
| `X-Content-Type-Options` | `nosniff` | Mencegah MIME-sniffing oleh browser |
| `X-Frame-Options` | `DENY` | Mencegah clickjacking dan perampasan frame console |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Mencegah kebocoran URL internal ke domain pihak ketiga |
| `Cache-Control` | `no-store, private` | Mencegah proxy bersama dan cache browser lokal menyimpan data rahasia |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Diterbitkan kondisional pada environment production melalui koneksi HTTPS |

---

## 7. Perlindungan Data Sensitif & Sanitasi Rahasia

1. **Sanitasi Audit Log Terpusat**:
   - `PlatformAuditLogger` menggunakan daftar kata kunci terlarang:
     `password`, `remember_token`, `token`, `snap_token`, `provider_payload`, `redirect_url`, `server_key`, `client_key`, `signature`, `secret`, `storage_path`, `backup_payload`, `cloud_backup_contents`, `payload`, `authorization`, `cookie`, `csrf`, `app_key`, `api_key`, `private_key`, `credential`.
   - Sanitasi berjalan rekursif pada struktur array nested dan tidak peka huruf besar/kecil (`case-insensitive` & format dash/underscore).

2. **Tampilan UI Platform**:
   - Kredensial Midtrans (`server_key`, `client_key`) hanya dirender sebagai status boolean konfigurasi (Terkonfigurasi / Belum).
   - Detail pembayaran hanya menampilkan status ketersediaan Snap token; tidak ada bagian token yang dirender. Raw payload kredensial, signature, maupun server key tidak pernah diekspos.
   - Detail user tidak mengekspos hash kata sandi, remember token, secret 2FA, recovery codes, maupun material WebAuthn.
   - Halaman backup hanya memonitor kesiapan dan status kesehatan operasional, tanpa mengekspos path internal file storage.

3. **Perlindungan Stored XSS**:
   - Semua input nama bisnis, perangkat, dan paket dirender melalui auto-escaping Blade (`{{ }}` atau fungsi `e()`).
   - Tidak ada penggunaan tag Blade mentah `{!! !!}` untuk field yang dikontrol pengguna tanpa sanitasi ketat.

---

## 8. Kontrol yang Ditunda (Deferred Controls)

1. **Content Security Policy (CSP)**:
   - **Status**: *Deferred*
   - **Alasan**: Aplikasi menggunakan kombinasi Blade layout, Flux UI, Livewire 3, dan Vite bundling yang memerlukan evaluasi inline styles & scripts secara komprehensif. Menambahkan CSP ketat secara gegabah tanpa pengujian frontend menyeluruh berisiko merusak fungsionalitas UI modal dan transisi dinamis.

2. **HSTS Header Unconditional**:
   - **Status**: *Conditional (Production + Secure HTTPS only)*
   - **Alasan**: Emisi HSTS secara tidak bersyarat pada development lokal atau test environment (HTTP) dapat menyebabkan browser memblokir koneksi lokal. Pada server produksi, HSTS idealnya dikelola oleh reverse proxy (Nginx / Cloudflare), namun middleware tetap menyediakannya jika request berstatus secure di production.

3. **Password Re-confirmation pada POST/PATCH Mutasi**:
   - **Status**: *Deferred ke alur UX khusus jika diperlukan di masa depan*
   - **Alasan**: Middleware bawaan `password.confirm` Laravel mengandalkan `redirect()->intended()`, yang hanya mempertahankan target GET dan membuang payload POST/PATCH. Menerapkannya langsung pada endpoint mutasi menyebabkan kegagalan HTTP dan hilangnya data form operator. Boundary keamanan saat ini diperkuat oleh TOTP 2FA wajib + rate limiting per admin.

---

## 9. Rekomendasi Environment Produksi

Pastikan variabel lingkungan (`.env`) pada server produksi dikonfigurasi sebagai berikut:

```env
APP_ENV=production
APP_DEBUG=false

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_SERIALIZATION=json

# Midtrans Production Gateway
MIDTRANS_IS_PRODUCTION=true
```
