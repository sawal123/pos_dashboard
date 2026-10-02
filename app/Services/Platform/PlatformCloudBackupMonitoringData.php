<?php

namespace App\Services\Platform;

use App\Services\Subscription\PremiumPolicy;

class PlatformCloudBackupMonitoringData
{
    public function __construct(
        protected PremiumPolicy $premiumPolicy,
    ) {}

    /**
     * Gather authoritative readiness, capability, and telemetry availability data.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $declaredCapabilities = $this->premiumPolicy->capabilities();
        $isBackupDeclared = in_array(PremiumPolicy::CAPABILITY_CLOUD_BACKUP, $declaredCapabilities, true);
        $isRestoreDeclared = in_array(PremiumPolicy::CAPABILITY_CLOUD_RESTORE, $declaredCapabilities, true);

        $isBackupAvailable = $this->premiumPolicy->isCapabilityAvailable(PremiumPolicy::CAPABILITY_CLOUD_BACKUP);
        $isRestoreAvailable = $this->premiumPolicy->isCapabilityAvailable(PremiumPolicy::CAPABILITY_CLOUD_RESTORE);

        $futureObservabilityRequirements = [
            [
                'field' => 'backup identifier',
                'type' => 'Identifier Unik',
                'description' => 'Pengenal unik transaksi pencadangan (idempotency reference).',
            ],
            [
                'field' => 'business reference',
                'type' => 'Relasi Bisnis',
                'description' => 'Referensi entitas bisnis pemilik data cadangan.',
            ],
            [
                'field' => 'source device reference',
                'type' => 'Relasi Perangkat',
                'description' => 'Referensi perangkat POS sumber snapshot data.',
            ],
            [
                'field' => 'execution status',
                'type' => 'Status Eksekusi',
                'description' => 'Status tahapan siklus hidup proses pencadangan.',
            ],
            [
                'field' => 'started timestamp',
                'type' => 'Waktu Mulai',
                'description' => 'Waktu inisiasi pengiriman berkas cadangan oleh perangkat.',
            ],
            [
                'field' => 'completed timestamp',
                'type' => 'Waktu Selesai',
                'description' => 'Waktu verifikasi dan penyelesaian penyimpanan cadangan.',
            ],
            [
                'field' => 'size metadata',
                'type' => 'Metadata Ukuran',
                'description' => 'Informasi kuantitatif ukuran data atau berkas pencadangan.',
            ],
            [
                'field' => 'storage reference (jika applicable)',
                'type' => 'Referensi Storage',
                'description' => 'Lokasi atau pengenal penyimpanan data cadangan di cloud (opsional sesuai arsitektur storage).',
            ],
            [
                'field' => 'integrity metadata (jika applicable)',
                'type' => 'Metadata Integritas',
                'description' => 'Informasi verifikasi keabsahan dan integritas berkas cadangan (opsional sesuai mekanisme verifikasi).',
            ],
            [
                'field' => 'restore event reference',
                'type' => 'Relasi Pemulihan',
                'description' => 'Tautan ke riwayat atau kejadian pemulihan data yang menggunakan cadangan ini.',
            ],
        ];

        return [
            'capabilities' => [
                'cloud_backup' => [
                    'name' => 'Cloud Backup',
                    'code' => PremiumPolicy::CAPABILITY_CLOUD_BACKUP,
                    'declared' => $isBackupDeclared,
                    'backend_available' => $isBackupAvailable,
                    'status_label' => $isBackupAvailable ? 'Tersedia' : 'Capability Terdaftar, Backend Belum Tersedia',
                    'description' => 'Pencadangan snapshot data operasional merchant ke storage cloud terpusat.',
                ],
                'cloud_restore' => [
                    'name' => 'Cloud Restore',
                    'code' => PremiumPolicy::CAPABILITY_CLOUD_RESTORE,
                    'declared' => $isRestoreDeclared,
                    'backend_available' => $isRestoreAvailable,
                    'status_label' => $isRestoreAvailable ? 'Tersedia' : 'Capability Terdaftar, Backend Belum Tersedia',
                    'description' => 'Pemulihan data bisnis dari arsip snapshot cloud ke perangkat kasir.',
                ],
            ],
            'readiness_summary' => [
                'backend_status' => $isBackupAvailable && $isRestoreAvailable ? 'Tersedia' : 'Belum Diimplementasikan',
                'backup_history' => 'Tidak Tersedia',
                'restore_history' => 'Tidak Tersedia',
                'storage_usage' => 'Tidak Tersedia',
            ],
            'readiness_matrix' => [
                [
                    'component' => 'Deklarasi Capability Cloud Backup',
                    'type' => 'Product Policy',
                    'status' => 'Terdaftar (Declared)',
                    'is_ready' => true,
                    'notes' => 'Tercantum di config/premium.php dan PremiumPolicy::CAPABILITY_CLOUD_BACKUP.',
                ],
                [
                    'component' => 'Deklarasi Capability Cloud Restore',
                    'type' => 'Product Policy',
                    'status' => 'Terdaftar (Declared)',
                    'is_ready' => true,
                    'notes' => 'Tercantum di config/premium.php dan PremiumPolicy::CAPABILITY_CLOUD_RESTORE.',
                ],
                [
                    'component' => 'Backend API Endpoint (/api/mobile/backups)',
                    'type' => 'API Route & Controller',
                    'status' => 'Tersedia',
                    'is_ready' => true,
                    'notes' => 'PREM-D03: upload, daftar, detail, dan unduh snapshot privat via CloudBackupController.',
                ],
                [
                    'component' => 'Backend API Endpoint Restore (/api/mobile/restore)',
                    'type' => 'API Route & Controller',
                    'status' => 'Belum Diimplementasikan',
                    'is_ready' => false,
                    'notes' => 'Eksekusi restore tetap di perangkat mobile (PREM-M06); server hanya menyediakan snapshot privat terotorisasi untuk diunduh.',
                ],
                [
                    'component' => 'Model & Tabel Database Riwayat Backup',
                    'type' => 'Database Schema',
                    'status' => 'Tersedia',
                    'is_ready' => true,
                    'notes' => 'PREM-D03: App\Models\CloudBackup + tabel cloud_backups pada disk privat.',
                ],
                [
                    'component' => 'Model & Tabel Database Riwayat Restore',
                    'type' => 'Database Schema',
                    'status' => 'Belum Tersedia',
                    'is_ready' => false,
                    'notes' => 'Belum ada model Eloquent CloudRestore maupun tabel audit restore.',
                ],
                [
                    'component' => 'Metrik Akuntansi Storage Cloud',
                    'type' => 'Storage Telemetry',
                    'status' => 'Belum Tersedia',
                    'is_ready' => false,
                    'notes' => 'Belum ada integrasi object storage provider maupun pelacakan pemakaian byte.',
                ],
                [
                    'component' => 'Penjadwalan Otomatis (Scheduled Backup)',
                    'type' => 'Background Job & Queue',
                    'status' => 'Belum Tersedia',
                    'is_ready' => false,
                    'notes' => 'Belum ada scheduler atau worker queue untuk backup berkala.',
                ],
            ],
            'future_observability_requirements' => $futureObservabilityRequirements,
            'future_telemetry_contract' => $futureObservabilityRequirements,
        ];
    }
}
