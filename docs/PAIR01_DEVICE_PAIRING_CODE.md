# PAIR-01 — Kasir Device Pairing (Kode Verifikasi 4 Digit)

Status: implemented (dashboard + mobile API + POS app)
Related: `DASH17_DEVICE_MANAGEMENT.md`, `INT01_CASHIER_SYNC_V1.md`, mobile `INT02_MOBILE_CASHIER_INTEGRATION.md`

## Aturan produk

| # | Aturan | Penegakan |
|---|--------|-----------|
| 1 | Pairing hanya tersedia untuk **paket Cloud aktif** | `Business::hasCloudAccess()` saat *issue* dan *redeem*; route dashboard memakai `premium.access:cloud_devices` |
| 2 | **Tidak berlaku untuk role owner** | Endpoint redeem menolak semua role selain `cashier` (`PAIRING_CASHIER_ONLY`). Perangkat owner dikelola dari dashboard dan di-bind otomatis oleh server (lazy provisioning di `SyncContextResolver`) |
| 3 | Pendaftaran perangkat **khusus role kasir** | Hanya `cashier` yang dapat menukar kode. Kasir tidak bisa lagi memanggil `POST /api/mobile/devices` |
| 4 | Kode **4 digit**, berlaku **30 menit**, sekali pakai | `DevicePairingCode::CODE_LENGTH = 4`, `TTL_MINUTES = 30`, `used_at`/`revoked_at` |
| 5 | Setelah kode diinput, server mencatat **nama perangkat, IP, dan info keamanan lain** | `devices.paired_user_id`, `paired_at`, `last_ip`, `user_agent`, `app_version` (+ `platform`, `notes` model perangkat) |

Tambahan pengamanan:

- Yang disimpan hanya **hash HMAC-SHA256** kode (per bisnis), bukan kode mentah.
- Kode dibatasi **rate limit** 5 percobaan / 15 menit per (bisnis, kasir) untuk mencegah brute force 4 digit.
- Membuat kode baru otomatis **membatalkan** kode lama yang belum dipakai pada outlet yang sama.
- `CLOUD_DEVICE_LIMIT` (PREM-D02A) tetap ditegakkan saat perangkat baru dibuat.
- Kode terikat **business + outlet**; kode bisnis lain tidak bisa dipakai (`PAIRING_CODE_INVALID`).

## Alur

1. **Pemilik** membuka Dashboard → Perangkat → **Kode Kasir**, memilih outlet.
2. Server membuat kode 4 digit (30 menit) dan menampilkannya di dashboard (dengan countdown + tombol batalkan).
3. Pemilik memberikan kode ke kasir.
4. **Kasir** membuka aplikasi → Sinkronisasi → **Pasangkan Perangkat (Kode 4 Digit)** → input kode + nama perangkat.
5. Aplikasi memanggil `POST /api/mobile/devices/pair` membawa kode + identitas perangkat.
6. Server memvalidasi, membuat/menyelesaikan device row untuk outlet pada kode, mencatat IP/user agent/versi app, menandai kode terpakai.
7. Aplikasi me-refresh `/api/mobile/context` sehingga `device_context` terisi; sync push/pull kasir menjadi mungkin.

## Dashboard

- Route: `POST devices/pairing-code` (`devices.pairing-code`) dan `DELETE devices/pairing-code/{codeId}` (`devices.pairing-code.revoke`).
- Permission: `devices.manage` + premium `cloud_devices` (owner-only).
- Kode yang masih aktif tampil di panel "Kode pairing aktif" beserta sisa waktu dan tombol batalkan.
- Tombol lama "Daftarkan Perangkat" (input identifier manual) **dihapus dari UI**; modal perangkat yang tersisa hanya untuk edit metadata/status perangkat yang sudah ada.

## Mobile API

### `POST /api/mobile/devices/pair`

Auth: Sanctum + ability `mobile`. Throttle: `10,1`.

Request:

```json
{
  "business_id": 10,
  "code": "4721",
  "device_identifier": "123e4567-e89b-12d3-a456-426614174000",
  "name": "Kasir Depan",
  "platform": "android",
  "app_version": "1.0.1",
  "device_model": "Samsung A9"
}
```

Success `200`:

```json
{
  "data": {
    "id": 42,
    "identifier": "123e4567-...",
    "business_id": 10,
    "outlet_id": 101,
    "status": "active",
    "name": "Kasir Depan",
    "platform": "android",
    "paired_at": "2026-11-15T13:11:00+00:00",
    "outlet": { "id": 101, "name": "Outlet Pusat" }
  }
}
```

Error codes:

| HTTP | code | Arti |
|------|------|------|
| 403 | `MOBILE_TOKEN_REQUIRED` | Token bukan token mobile |
| 403 | `BUSINESS_ACCESS_DENIED` | Bukan anggota bisnis |
| 403 | `PAIRING_CASHIER_ONLY` | Role bukan kasir (owner/member) |
| 403 | `CLOUD_SUBSCRIPTION_REQUIRED` | Paket Cloud tidak aktif |
| 403 | `DEVICE_INACTIVE` | Perangkat dengan identifier ini dinonaktifkan |
| 403 | `CLOUD_DEVICE_LIMIT_REACHED` | Kuota perangkat Cloud penuh |
| 409 | `PAIRING_CODE_ALREADY_USED` | Kode sudah dipakai |
| 409 | `DEVICE_OUTLET_MISMATCH` | Identifier terdaftar di outlet lain |
| 410 | `PAIRING_CODE_EXPIRED` | Kode kedaluwarsa (> 30 menit) |
| 410 | `PAIRING_CODE_REVOKED` | Kode dibatalkan pemilik |
| 422 | `PAIRING_CODE_INVALID` | Kode salah / tidak ada |
| 429 | `PAIRING_RATE_LIMITED` | Terlalu banyak percobaan |

## Sync

- `SyncContextResolver`: identifier yang tidak dikenal **ditolak** untuk role kasir (`DEVICE_NOT_REGISTERED`) sampai kasir menyelesaikan pairing.
- Owner/member tetap mendapat lazy provisioning agar tidak perlu pairing.
- Binding sync (pull/push binding, context guard) memakai `registeredDeviceId` yang sama, jadi integritas P23/P38 tidak berubah.

## Tests

- `tests/Feature/DevicePairingTest.php` — 23 test: cloud-only, cashier-only, 4 digit, 30 menit, single-use, revoke, rate limit, tenant isolation, device limit, outlet/identifier keamanan, kontrak sync kasir vs owner, surface dashboard.
- `pos-mobile/src/__tests__/device-pairing.spec.js` — 17 test: normalisasi kode, payload request, pemetaan error, store binding, modal pairing.
