<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tautan Atur Ulang Kata Sandi</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f5;font-family:'Segoe UI',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:16px;border:1px solid #dcebe2;">
        <tr>
            <td style="padding:28px 30px;">
                <p style="margin:0 0 4px;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#2f7d5b;">Luminpark</p>
                <h1 style="margin:0 0 14px;font-size:20px;line-height:1.35;">Atur ulang kata sandi</h1>
                <p style="margin:0 0 10px;font-size:14px;line-height:1.6;">Halo {{ $nama }},</p>
                <p style="margin:0 0 18px;font-size:14px;line-height:1.6;">
                    Kami menerima permintaan untuk mengatur ulang kata sandi akun Luminpark Anda.
                    Klik tombol di bawah ini untuk menentukan kata sandi baru. Tautan ini hanya berlaku
                    {{ $menitKedaluwarsa }} menit dan sekali pakai.
                </p>
                <p style="margin:0 0 20px;text-align:center;">
                    <a href="{{ $url }}"
                       style="display:inline-block;background:#111827;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none;padding:12px 26px;border-radius:12px;">
                        Atur Ulang Kata Sandi
                    </a>
                </p>
                <p style="margin:0 0 6px;font-size:12px;line-height:1.6;color:#4b5563;word-break:break-all;">
                    Jika tombol tidak berfungsi, salin tautan ini ke peramban:<br>{{ $url }}
                </p>
                <p style="margin:18px 0 0;font-size:12px;line-height:1.6;color:#6b7280;">
                    Abaikan email ini bila Anda tidak pernah meminta pengaturan ulang kata sandi. Kata sandi lama Anda
                    tetap berlaku sampai tautan ini dipakai. Jangan bagikan tautan ini kepada siapa pun.
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 30px;border-top:1px solid #e5e7eb;font-size:11px;color:#6b7280;">
                Email sistem otomatis &mdash; tidak perlu dibalas. &copy; {{ date('Y') }} Perumahan Luminpark
            </td>
        </tr>
    </table>
</body>
</html>
