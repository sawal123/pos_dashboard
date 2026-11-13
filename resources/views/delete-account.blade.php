<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penghapusan Akun & Data - Net Kasir</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #475569;
            --primary: #dc2626;
            --primary-bg: #fef2f2;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            line-height: 1.6;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 24px;
            margin-bottom: 24px;
        }
        .badge {
            display: inline-block;
            background: var(--primary-bg);
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 999px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        .meta {
            font-size: 13px;
            color: var(--text-muted);
        }
        h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            margin-top: 28px;
            margin-bottom: 12px;
        }
        p {
            font-size: 15px;
            color: var(--text-muted);
            margin-bottom: 14px;
        }
        ol, ul {
            margin-left: 20px;
            margin-bottom: 16px;
            color: var(--text-muted);
            font-size: 15px;
        }
        li {
            margin-bottom: 8px;
        }
        .step-card {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }
        .step-card strong {
            color: var(--text-main);
        }
        .footer {
            border-top: 1px solid var(--border-color);
            padding-top: 24px;
            margin-top: 32px;
            font-size: 13px;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        a {
            color: #4338ca;
            text-decoration: underline;
        }
        .btn-mail {
            display: inline-block;
            background: #dc2626;
            color: #ffffff !important;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="badge">Kebijakan Akun & Data</span>
            <h1>Prosedur Penghapusan Akun & Data Pengguna</h1>
            <p class="meta">Aplikasi: Net Kasir (com.netkasir.app) &bull; Developer: Net Kasir Team</p>
        </div>

        <p>Sesuai dengan komitmen perlindungan data pribadi dan kebijakan Google Play Store mengenai penghapusan akun (<em>Account Deletion Policy</em>), pengguna aplikasi <strong>Net Kasir</strong> berhak untuk mengajukan permohonan penghapusan akun serta seluruh data bisnis yang tersimpan di server Cloud kami kapan saja.</p>

        <h2>Cara Mengajukan Penghapusan Akun & Data</h2>
        
        <div class="step-card">
            <p><strong>Langkah 1: Mengirim Permohonan via Email</strong></p>
            <p>Kirimkan permohonan penghapusan akun melalui email terdaftar Anda ke alamat:</p>
            <p><a href="mailto:support@netkasir.id?subject=Permohonan%20Penghapusan%20Akun%20Net%20Kasir" class="btn-mail">Kirim Email ke support@netkasir.id</a></p>
            <p style="margin-top: 10px; font-size: 13px;"><em>Atau alternatif email: developer.netkasir@gmail.com</em></p>
        </div>

        <div class="step-card">
            <p><strong>Langkah 2: Format Informasi yang Dibutuhkan</strong></p>
            <ul>
                <li><strong>Subjek Email:</strong> Permohonan Penghapusan Akun Net Kasir</li>
                <li><strong>Email Terdaftar:</strong> Alamat email yang digunakan saat mendaftar akun</li>
                <li><strong>Nama Toko/Bisnis:</strong> Nama outlet atau bisnis yang terdaftar di akun Anda</li>
                <li><strong>Alasan (Opsional):</strong> Keterangan singkat alasan penghapusan akun</li>
            </ul>
        </div>

        <h2>Data yang Dihapus Secara Permanen</h2>
        <p>Setelah permohonan diverifikasi, kami akan menghapus secara permanen dari server Cloud:</p>
        <ul>
            <li>Informasi Profil Pengguna (Nama, Email, Nomor Telepon, Kata Sandi).</li>
            <li>Profil Bisnis dan Toko/Outlet terdaftar.</li>
            <li>Seluruh riwayat transaksi kasir, struk penjualan, dan laporan keuangan di server.</li>
            <li>Katalog produk, daftar varian, dan stok opname di database Cloud.</li>
        </ul>

        <h2>Periode Retensi & Pemrosesan</h2>
        <p>Permohonan penghapusan akun akan diproses dalam waktu maksimal <strong>7 (tujuh) hari kerja</strong> setelah identitas kepemilikan akun berhasil diverifikasi. Setelah proses penghapusan selesai, konfirmasi tertulis akan dikirimkan kembali ke alamat email Anda.</p>

        <div class="footer">
            <span>&copy; 2026 Net Kasir POS. Seluruh hak cipta dilindungi.</span>
            <a href="/privacy-policy">&larr; Kembali ke Kebijakan Privasi</a>
        </div>
    </div>
</body>
</html>

