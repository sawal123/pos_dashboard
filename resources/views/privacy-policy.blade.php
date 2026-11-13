<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kebijakan Privasi - Net Kasir</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #475569;
            --primary: #4338ca;
            --primary-bg: #eef2ff;
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
            font-size: 28px;
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
        ul {
            margin-left: 20px;
            margin-bottom: 16px;
            color: var(--text-muted);
            font-size: 15px;
        }
        li {
            margin-bottom: 6px;
        }
        .notice-box {
            background: #f1f5f9;
            border-left: 4px solid var(--primary);
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            color: #334155;
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
            color: var(--primary);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="badge">Legal & Privasi</span>
            <h1>Kebijakan Privasi Net Kasir</h1>
            <p class="meta">Aplikasi: Net Kasir (com.netkasir.app) &bull; Terakhir diperbarui: 13 November 2026</p>
        </div>

        <p>Selamat datang di <strong>Net Kasir</strong> ("Aplikasi", "Kami"). Kebijakan Privasi ini dibuat untuk menjelaskan komitmen kami dalam melindungi dan menjaga privasi serta keamanan data para pemilik bisnis, kasir, dan pelanggan yang menggunakan aplikasi kasir Point of Sale (POS) Net Kasir.</p>

        <h2>1. Prinsip Offline-First</h2>
        <p>Net Kasir dibangun dengan arsitektur <strong>Offline-First</strong>. Seluruh data transaksi, penjualan, katalog produk, dan stok harian disimpan langsung di perangkat lokal pengguna (SQLite lokal terenkripsi). Aplikasi dapat berfungsi penuh tanpa koneksi internet dan tanpa kewajiban membuat akun.</p>

        <h2>2. Data yang Dikumpulkan (Saat Menggunakan Fitur Cloud)</h2>
        <p>Jika pengguna memilih menggunakan fitur sinkronisasi cloud atau multi-outlet, data berikut akan diproses:</p>
        <ul>
            <li><strong>Informasi Akun:</strong> Nama pengguna, alamat email, dan nomor telepon untuk autentikasi toko.</li>
            <li><strong>Data Bisnis & Transaksi:</strong> Nama outlet, katalog barang/jasa, harga, riwayat transaksi penjualan kasir, dan catatan pergeseran shift.</li>
            <li><strong>Izin Perangkat:</strong>
                <ul>
                    <li><em>Kamera:</em> Hanya untuk pemindaian barcode / QR code barang dan QRIS kasir.</li>
                    <li><em>Bluetooth:</em> Untuk menghubungkan aplikasi dengan printer thermal kasir (58mm/80mm).</li>
                    <li><em>Penyimpanan:</em> Untuk menyimpan database lokal offline dan ekspor laporan PDF/Excel.</li>
                </ul>
            </li>
        </ul>

        <h2>3. Penggunaan & Keamanan Data</h2>
        <p>Kami tidak pernah menjual, menyewakan, atau membagikan data bisnis atau pelanggan Anda kepada pihak ketiga untuk kepentingan iklan. Seluruh pertukaran data antara aplikasi mobile dan server menggunakan enkripsi HTTPS / TLS berstandar industri.</p>

        <h2>4. Hak Penghapusan Akun & Data (Account Deletion)</h2>
        <div class="notice-box">
            Pengguna berhak meminta penghapusan akun Cloud dan seluruh data bisnis terkait kapan saja. Panduan lengkap penghapusan akun dan data dapat diakses langsung pada halaman <a href="/delete-account">Prosedur Penghapusan Akun Net Kasir</a>.
        </div>

        <h2>5. Kontak Pengembang</h2>
        <p>Untuk pertanyaan, masukan, atau permintaan bantuan terkait privasi data, silakan hubungi tim kami:</p>
        <ul>
            <li><strong>Email Dukungan:</strong> support@netkasir.id / developer.netkasir@gmail.com</li>
            <li><strong>Situs Resmi:</strong> https://pos.eradig.my.id</li>
        </ul>

        <div class="footer">
            <span>&copy; 2026 Net Kasir POS. Seluruh hak cipta dilindungi.</span>
            <a href="/delete-account">Penghapusan Akun & Data &rarr;</a>
        </div>
    </div>
</body>
</html>

