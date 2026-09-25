<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Undangan Anggota</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;border:1px solid #e2e8f0;">
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 8px;font-size:20px;font-weight:800;">
                                Undangan bergabung ke {{ $businessName }}
                            </h1>
                            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#475569;">
                                Anda diundang untuk bergabung sebagai <strong>{{ $role }}</strong> pada bisnis
                                <strong>{{ $businessName }}</strong>.
                            </p>
                            <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#475569;">
                                Buka tautan di bawah ini untuk menerima undangan. Tautan bersifat rahasia,
                                hanya dapat dipakai sekali, dan berlaku sampai
                                <strong>{{ $expiresAt?->translatedFormat('d M Y - H:i') }}</strong>.
                            </p>
                            <p style="margin:0 0 24px;">
                                <a href="{{ $acceptUrl }}"
                                   style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:12px;font-size:14px;font-weight:700;">
                                    Terima Undangan
                                </a>
                            </p>
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8;">
                                Jika Anda tidak mengenali undangan ini, abaikan saja email ini.
                                Jangan meneruskan tautan ini kepada siapa pun.
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:16px 0 0;font-size:11px;color:#94a3b8;">
                    Email otomatis dari NexaPOS. Mohon tidak membalas email ini.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
