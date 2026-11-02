<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mode Offline - NexaPOS</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="theme-color" content="#4f46e5">
    <style>
        :root {
            --bg-color: #090d16;
            --card-bg: #0f172a;
            --border-color: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --amber: #f59e0b;
        }
        @media (prefers-color-scheme: light) {
            :root {
                --bg-color: #f8fafc;
                --card-bg: #ffffff;
                --border-color: #e2e8f0;
                --text-main: #0f172a;
                --text-muted: #64748b;
            }
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .offline-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            max-width: 480px;
            width: 100%;
            padding: 36px 28px;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.25);
            animation: fadeIn 0.3s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .icon-badge {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(79, 70, 229, 0.15));
            border: 1px solid rgba(245, 158, 11, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--amber);
        }
        .icon-badge svg {
            width: 36px;
            height: 36px;
        }
        h1 {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 10px;
        }
        p.subtitle {
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-muted);
            margin-bottom: 24px;
        }
        .button-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 24px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }
        .btn-primary {
            background-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
        .btn-secondary {
            background-color: transparent;
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }
        .btn-secondary:hover {
            background-color: var(--border-color);
        }
        .notice-box {
            background-color: rgba(79, 70, 229, 0.08);
            border: 1px solid rgba(79, 70, 229, 0.2);
            border-radius: 12px;
            padding: 14px;
            text-align: left;
            font-size: 12px;
            line-height: 1.5;
            color: var(--text-muted);
        }
        .notice-box strong {
            color: var(--text-main);
            display: block;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="offline-card">
        <div class="icon-badge">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.5 10.5a6 6 0 00-6.7 1.5M16.5 7.5a10 10 0 00-11-1.3M1.5 1.5l21 21M9.88 9.88A3 3 0 009 12a3 3 0 003 3c.8 0 1.54-.31 2.12-.88M12 19h.01" />
            </svg>
        </div>

        <h1>Koneksi Terputus (Mode Offline)</h1>
        <p class="subtitle">
            Perangkat Anda sedang tidak terhubung ke jaringan internet.
            Halaman yang sudah pernah dibuka atau Zona Kasir tetap dapat diakses dari cache lokal.
        </p>

        <div class="button-group">
            <button onclick="window.location.reload()" class="btn btn-primary" type="button">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Coba Muat Ulang Halaman
            </button>
            <a href="/pos" class="btn btn-secondary">
                Buka Zona Kasir (POS)
            </a>
            <a href="/dashboard" class="btn btn-secondary">
                Kembali ke Dashboard
            </a>
        </div>

        <div class="notice-box">
            <strong>💡 Informasi Otomatis</strong>
            Halaman ini akan secara otomatis memuat ulang begitu koneksi internet Anda terhubung kembali.
        </div>
    </div>

    <script>
        // Auto-reload when device gets back online
        window.addEventListener('online', function() {
            window.location.reload();
        });
    </script>
</body>
</html>
