@php
    $statusLabel = match ($payment?->status) {
        'paid' => 'Lunas',
        'pending' => 'Menunggu pembayaran',
        'failed' => 'Gagal',
        'expired' => 'Kedaluwarsa',
        'cancelled' => 'Dibatalkan',
        'refunded' => 'Dikembalikan',
        default => '-',
    };

    $title = $orderId === ''
        ? 'Status Pembayaran'
        : ($confirmed ? 'Pembayaran Dikonfirmasi' : 'Pembayaran Diproses');
@endphp

<!DOCTYPE html>
<html lang="id" class="light">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 antialiased">
    <main class="min-h-screen flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-sm p-6 sm:p-8 space-y-5">
            <div class="flex items-center gap-2.5">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm shadow-indigo-600/30">
                    <i data-lucide="zap" class="h-5 w-5"></i>
                </div>
                <span class="text-xl font-extrabold tracking-tight">
                    Nexa<span class="text-indigo-600 dark:text-indigo-400">POS</span>
                </span>
            </div>

            <div class="space-y-1">
                <h1 class="text-xl font-extrabold tracking-tight">{{ $title }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Status ini berasal dari server, bukan dari redirect browser. Langganan hanya aktif setelah
                    pembayaran diverifikasi oleh webhook Midtrans.
                </p>
            </div>

            @if ($orderId === '')
                <div class="rounded-xl border border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/20 p-4">
                    <p class="text-sm text-rose-800 dark:text-rose-200">
                        Parameter pembayaran tidak lengkap. Silakan kembali ke halaman langganan.
                    </p>
                </div>
            @elseif ($payment === null)
                <div class="rounded-xl border border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/20 p-4">
                    <p class="text-sm text-rose-800 dark:text-rose-200">
                        Pembayaran dengan Order ID <span class="font-semibold">{{ $orderId }}</span> tidak ditemukan.
                    </p>
                </div>
            @else
                <div class="rounded-xl border p-4 {{ $confirmed
                    ? 'border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/20'
                    : 'border-amber-200/80 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20' }}">
                    <p class="text-sm font-semibold {{ $confirmed ? 'text-emerald-800 dark:text-emerald-200' : 'text-amber-800 dark:text-amber-200' }}">
                        {{ $confirmed ? 'Pembayaran diterima.' : 'Pembayaran belum dikonfirmasi.' }}
                    </p>
                    <p class="mt-1 text-xs {{ $confirmed ? 'text-emerald-800/90 dark:text-emerald-200/90' : 'text-amber-800/90 dark:text-amber-200/90' }}">
                        {{ $confirmed
                            ? 'Langganan Cloud akan aktif pada bisnis ini setelah proses server selesai.'
                            : 'Jika Anda sudah membayar, tunggu beberapa saat lalu buka kembali halaman Langganan.' }}
                    </p>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-3">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Order ID</dt>
                        <dd class="mt-0.5 font-mono text-xs font-semibold break-all">{{ $payment->provider_order_id }}</dd>
                    </div>
                    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-3">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Status</dt>
                        <dd class="mt-0.5 font-semibold">{{ $statusLabel }}</dd>
                    </div>
                    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-3">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Jumlah</dt>
                        <dd class="mt-0.5 font-semibold">
                            {{ ($payment->currency === 'IDR' ? 'Rp' : $payment->currency.' ') }}{{ number_format($payment->amount, 0, ',', '.') }}
                        </dd>
                    </div>
                    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-3">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Dibayar pada</dt>
                        <dd class="mt-0.5 font-semibold">
                            {{ $payment->paid_at?->translatedFormat('d M Y - H:i') ?? '-' }}
                        </dd>
                    </div>
                </dl>
            @endif

            <div class="flex flex-col sm:flex-row gap-2 pt-1">
                @auth
                    <a href="{{ route('subscriptions.index') }}"
                       class="inline-flex items-center justify-center h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                        Kembali ke Langganan
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center justify-center h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors">
                        Masuk ke Dashboard
                    </a>
                @endauth
            </div>
        </div>
    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>
