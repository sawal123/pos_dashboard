<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Kata Sandi - NexaPOS</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            line-height: 1.6;
        }
        .container {
            max-width: 560px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #0f172a;
            padding: 28px 32px;
            text-align: center;
        }
        .logo-text {
            color: #ffffff;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .logo-text span {
            color: #818cf8;
        }
        .content {
            padding: 32px;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 0;
            margin-bottom: 16px;
        }
        .text {
            font-size: 14px;
            color: #475569;
            margin-bottom: 20px;
        }
        .btn-wrapper {
            text-align: center;
            margin: 28px 0;
        }
        .btn {
            background-color: #4f46e5;
            color: #ffffff !important;
            padding: 12px 28px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 10px;
            display: inline-block;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
        }
        .notice-box {
            background-color: #f1f5f9;
            border-left: 4px solid #6366f1;
            padding: 14px 16px;
            border-radius: 6px;
            margin: 24px 0;
            font-size: 13px;
            color: #334155;
        }
        .link-text {
            font-size: 12px;
            color: #64748b;
            word-break: break-all;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }
        .link-text a {
            color: #4f46e5;
            text-decoration: underline;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 32px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="logo-text">Nexa<span>POS</span></h1>
        </div>

        <div class="content">
            <h2 class="title">Reset Kata Sandi Akun</h2>
            <p class="text">
                Halo{{ !empty($user->name) ? ', ' . htmlspecialchars($user->name) : '' }},
            </p>
            <p class="text">
                Kami menerima permintaan untuk mengatur ulang kata sandi akun NexaPOS Anda. Klik tombol di bawah ini untuk membuat kata sandi baru:
            </p>

            <div class="btn-wrapper">
                <a href="{{ $url }}" class="btn" target="_blank" rel="noopener">Atur Ulang Kata Sandi</a>
            </div>

            <div class="notice-box">
                <strong>Penting:</strong> Tautan verifikasi ini bersifat rahasia dan berlaku selama <strong>{{ $count ?? 30 }} menit</strong>. Jika Anda tidak melakukan reset dalam kurun waktu tersebut, tautan akan kedaluwarsa secara otomatis demi keamanan akun Anda.
            </div>

            <p class="text" style="font-size: 13px; color: #64748b;">
                Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini. Kata sandi akun Anda tidak akan berubah.
            </p>

            <div class="link-text">
                Jika tombol di atas tidak dapat diklik, salin dan buka tautan berikut di peramban (browser) Anda:<br>
                <a href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a>
            </div>
        </div>

        <div class="footer">
            Email ini dikirim secara otomatis oleh sistem NexaPOS.<br>
            &copy; {{ date('Y') }} NexaPOS. Seluruh hak cipta dilindungi.
        </div>
    </div>
</body>
</html>
