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

        return [
            'capabilities' => [
                'cloud_backup' => [
                    'name' => 'Cloud Backup',
                    'code' => PremiumPolicy::CAPABILITY_CLOUD_BACKUP,
                    'declared' => $isBackupDeclared,
                    'backend_available' => false,
                    'status_label' => 'Capability Terdaftar, Backend Belum Tersedia',
                    'description' => 'Pencadangan snapshot data operasional merchant ke storage cloud terpusat.',
                ],
                'cloud_restore' => [
                    'name' => 'Cloud Restore',
                    'code' => PremiumPolicy::CAPABILITY_CLOUD_RESTORE,
                    'declared' => $isRestoreDeclared,
                    'backend_available' => false,
                    'status_label' => 'Capability Terdaftar, Backend Belum Tersedia',
                    'description' => 'Pemulihan data bisnis dari arsip snapshot cloud ke perangkat kasir.',
                ],
            ],
            'readiness_summary' => [
                'backend_status' => 'Belum Diimplementasikan',
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
                    'component' => 'Backend API Endpoint (/api/mobile/backup)',
                    'type' => 'API Route & Controller',
                    'status' => 'Belum Diimplementasikan',
                    'is_ready' => false,
                    'notes' => 'Belum ada endpoint route, controller, maupun handler backup di server.',
                ],
                [
                    'component' => 'Backend API Endpoint (/api/mobile/restore)',
                    'type' => 'API Route & Controller',
                    'status' => 'Belum Diimplementasikan',
                    'is_ready' => false,
                    'notes' => 'Belum ada endpoint route, controller, maupun handler restore di server.',
                ],
                [
                    'component' => 'Model & Tabel Database Riwayat Backup',
                    'type' => 'Database Schema',
                    'status' => 'Belum Tersedia',
                    'is_ready' => false,
                    'notes' => 'Belum ada model Eloquent CloudBackup maupun tabel database backup.',
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
            'future_telemetry_contract' => [
                ['field' => 'backup_id', 'type' => 'UUID', 'description' => 'Pengenal unik transaksi pencadangan (idempotency token).'],
                ['field' => 'business_id', 'type' => 'Foreign Key', 'description' => 'Relasi pemilik data bisnis yang dicadangkan.'],
                ['field' => 'device_id', 'type' => 'Foreign Key', 'description' => 'Perangkat POS Mobile asal snapshot data.'],
                ['field' => 'status', 'type' => 'Enum / String', 'description' => 'Status eksekusi (in_progress, completed, failed).'],
                ['field' => 'started_at', 'type' => 'Timestamp', 'description' => 'Waktu inisiasi upload snapshot oleh perangkat.'],
                ['field' => 'completed_at', 'type' => 'Timestamp', 'description' => 'Waktu verifikasi dan commit ke cloud storage.'],
                ['field' => 'size_bytes', 'type' => 'BigInteger', 'description' => 'Ukuran file arsip snapshot terkompresi.'],
                ['field' => 'storage_reference', 'type' => 'String', 'description' => 'Kunci referensi objek di cloud object storage provider.'],
                ['field' => 'checksum', 'type' => 'String', 'description' => 'Hash SHA-256 untuk verifikasi integritas data arsip.'],
            ],
        ];
    }
}
