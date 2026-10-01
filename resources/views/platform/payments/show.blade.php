<x-layouts::platform :title="'Detail Pembayaran ' . $payment->provider_order_id">
    <div class="space-y-6">

        {{-- Breadcrumb & Back Action --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('platform.payments.index') }}"
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 transition-colors"
                    title="Kembali ke Daftar Pembayaran"
                >
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                            {{ $payment->provider_order_id }}
                        </h1>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $diagnostic['badge_class'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $diagnostic['dot_class'] }}"></span>
                            {{ $diagnostic['label'] }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Pembayaran ID #{{ $payment->id }} &bull; Provider: {{ strtoupper($payment->provider) }}
                    </p>
                </div>
            </div>

            {{-- Status & Amount Header Pill --}}
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 block">Nominal Snapshot</span>
                    <span class="text-lg md:text-xl font-black text-slate-900 dark:text-white">
                        Rp {{ number_format($payment->amount, 0, ',', '.') }}
                    </span>
                </div>
                @php
                    $statusClass = match($payment->status) {
                        \App\Models\SubscriptionPayment::STATUS_PAID => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border-emerald-200/60 dark:border-emerald-800/60',
                        \App\Models\SubscriptionPayment::STATUS_PENDING => 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border-amber-200/60 dark:border-amber-800/60',
                        \App\Models\SubscriptionPayment::STATUS_FAILED => 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border-rose-200/60 dark:border-rose-800/60',
                        \App\Models\SubscriptionPayment::STATUS_EXPIRED => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                        \App\Models\SubscriptionPayment::STATUS_CANCELLED => 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                        \App\Models\SubscriptionPayment::STATUS_REFUNDED => 'bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-400 border-purple-200/60 dark:border-purple-800/60',
                        default => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                    };
                    $statusLabel = match($payment->status) {
                        \App\Models\SubscriptionPayment::STATUS_PAID => 'Berhasil',
                        \App\Models\SubscriptionPayment::STATUS_PENDING => 'Menunggu Pembayaran',
                        \App\Models\SubscriptionPayment::STATUS_FAILED => 'Gagal',
                        \App\Models\SubscriptionPayment::STATUS_EXPIRED => 'Kedaluwarsa',
                        \App\Models\SubscriptionPayment::STATUS_CANCELLED => 'Dibatalkan',
                        \App\Models\SubscriptionPayment::STATUS_REFUNDED => 'Dikembalikan',
                        default => ucfirst($payment->status),
                    };
                @endphp
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold border {{ $statusClass }}">
                    {{ $statusLabel }}
                </span>
            </div>
        </div>

        {{-- Operator Observability Banner --}}
        <div class="p-3.5 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200/70 dark:border-indigo-800/50 flex items-start gap-3">
            <i data-lucide="info" class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5"></i>
            <div class="text-xs text-indigo-900 dark:text-indigo-200">
                <span class="font-bold">Konsol Observabilitas Operator:</span>
                Halaman ini menampilkan data historis pembayaran dan diagnostik integritas sistem secara read-only. Platform Admin tidak melakukan mutasi status sepihak atau rekonsiliasi otomatis tanpa verifikasi payment gateway.
            </div>
        </div>

        {{-- Diagnostic Integritas Pembayaran & Aktivasi (Historical) --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        <i data-lucide="{{ $diagnostic['icon'] }}" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                        Diagnostik Integritas Pembayaran &amp; Aktivasi
                    </h2>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $diagnostic['badge_class'] }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $diagnostic['dot_class'] }}"></span>
                    {{ $diagnostic['label'] }}
                </span>
            </div>

            <div class="mt-4 space-y-3">
                <p class="text-xs text-slate-600 dark:text-slate-300">
                    {{ $diagnostic['summary'] }}
                </p>

                @if(!empty($diagnostic['info_notice']))
                    <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200/80 dark:border-indigo-800/60 flex items-start gap-2">
                        <i data-lucide="info" class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5"></i>
                        <span class="text-xs text-indigo-900 dark:text-indigo-200">{{ $diagnostic['info_notice'] }}</span>
                    </div>
                @endif

                @if(!empty($diagnostic['reasons']))
                    <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/60">
                        <p class="text-xs font-bold text-amber-900 dark:text-amber-200 flex items-center gap-1.5">
                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400"></i>
                            Penyebab inkonsistensi terdeteksi:
                        </p>
                        <ul class="mt-1.5 space-y-1 text-xs text-amber-800 dark:text-amber-300 list-disc list-inside">
                            @foreach($diagnostic['reasons'] as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Historical Consistency Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs">
                    <div>
                        <span class="text-slate-400 dark:text-slate-500 block text-[11px]">Status Pembayaran</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">
                            {{ $statusLabel }} ({{ $payment->status }})
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500 block text-[11px]">Waktu Aktivasi Transaksi</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">
                            {{ $payment->activated_at ? $payment->activated_at->format('d M Y H:i:s') : 'Belum tercatat (-)' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500 block text-[11px]">Masa Berlaku Transaksi</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">
                            {{ $payment->expires_at ? $payment->expires_at->format('d M Y H:i:s') : '-' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 dark:text-slate-500 block text-[11px]">Integritas Historis</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">
                            {{ $diagnostic['label'] }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Panel Status Langganan Saat Ini (Current Subscription & Entitlement) --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                            Status Langganan Bisnis Saat Ini (Current Subscription)
                        </h2>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">
                            Status hak akses saat ini dievaluasi secara independen dari riwayat transaksi pembayaran.
                        </p>
                    </div>
                </div>
                <div>
                    @if($hasCloudAccess)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold border bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border-emerald-200/60 dark:border-emerald-800/60">
                            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                            Granted (Aktif)
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold border bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border-rose-200/60 dark:border-rose-800/60">
                            <i data-lucide="shield-x" class="w-3.5 h-3.5"></i>
                            Denied (Tidak Aktif)
                        </span>
                    @endif
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                <div>
                    <span class="text-slate-400 dark:text-slate-500 block text-[11px]">Current Plan</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">
                        @if($subscription)
                            {{ ucfirst($subscription->plan) }}
                        @else
                            <span class="text-slate-500">Free Tier</span>
                        @endif
                    </span>
                </div>
                <div>
                    <span class="text-slate-400 dark:text-slate-500 block text-[11px]">Current Status</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">
                        @if($subscription)
                            {{ ucfirst($subscription->status) }}
                        @else
                            <span class="text-slate-400 italic">Belum terdaftar</span>
                        @endif
                    </span>
                </div>
                <div>
                    <span class="text-slate-400 dark:text-slate-500 block text-[11px]">Current Entitlement</span>
                    @if($hasCloudAccess)
                        <span class="inline-flex items-center gap-1 font-bold text-emerald-600 dark:text-emerald-400">
                            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                            Granted
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 font-bold text-rose-500 dark:text-rose-400">
                            <i data-lucide="shield-x" class="w-3.5 h-3.5"></i>
                            Denied
                        </span>
                    @endif
                </div>
                <div>
                    <span class="text-slate-400 dark:text-slate-500 block text-[11px]">Current Expires At</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">
                        {{ $subscription?->expires_at ? $subscription->expires_at->format('d M Y H:i:s') : '-' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Main Details Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Identitas Pembayaran & Provider --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="credit-card" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                        Identitas Pembayaran &amp; Provider
                    </h2>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-xs">
                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Payment ID</dt>
                        <dd class="font-mono font-bold text-slate-900 dark:text-white mt-0.5">#{{ $payment->id }}</dd>
                    </div>

                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Provider</dt>
                        <dd class="font-bold text-slate-900 dark:text-white uppercase mt-0.5">{{ $payment->provider }}</dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Provider Order ID</dt>
                        <dd class="font-mono font-bold text-slate-900 dark:text-white break-all mt-0.5">{{ $payment->provider_order_id }}</dd>
                    </div>

                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Provider Transaction ID</dt>
                        <dd class="font-mono text-slate-800 dark:text-slate-200 mt-0.5">{{ $payment->provider_transaction_id ?? '-' }}</dd>
                    </div>

                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Provider Payment Type</dt>
                        <dd class="font-medium text-slate-800 dark:text-slate-200 mt-0.5">{{ $payment->provider_payment_type ?? '-' }}</dd>
                    </div>

                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Transaction Status Provider</dt>
                        <dd class="font-medium text-slate-800 dark:text-slate-200 mt-0.5">{{ $payment->provider_transaction_status ?? '-' }}</dd>
                    </div>

                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Fraud Status</dt>
                        <dd class="font-medium text-slate-800 dark:text-slate-200 mt-0.5">{{ $payment->provider_fraud_status ?? '-' }}</dd>
                    </div>

                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Snap Token</dt>
                        <dd class="font-medium text-slate-800 dark:text-slate-200 mt-0.5 flex items-center gap-1.5">
                            @if($payment->snap_token)
                                <i data-lucide="lock" class="w-3.5 h-3.5 text-emerald-500"></i>
                                <span>Tersedia (••••{{ substr($payment->snap_token, -4) }})</span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Idempotency Key</dt>
                        <dd class="font-mono text-slate-800 dark:text-slate-200 mt-0.5">
                            @if($payment->idempotency_key)
                                <span>••••{{ substr($payment->idempotency_key, -8) }}</span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Informasi Bisnis --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                            <i data-lucide="building-2" class="w-4 h-4"></i>
                        </span>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                            Informasi Bisnis Terkait
                        </h2>
                    </div>
                    @if($business)
                        <a
                            href="{{ route('platform.businesses.show', $business) }}"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold transition-colors"
                        >
                            <span>Buka Bisnis</span>
                            <i data-lucide="external-link" class="w-3 h-3"></i>
                        </a>
                    @endif
                </div>

                @if($business)
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-xs">
                        <div class="sm:col-span-2">
                            <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Nama Bisnis</dt>
                            <dd class="font-bold text-slate-900 dark:text-white text-sm mt-0.5">{{ $business->name }}</dd>
                        </div>

                        <div>
                            <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Slug</dt>
                            <dd class="font-mono text-slate-800 dark:text-slate-200 mt-0.5">{{ $business->slug }}</dd>
                        </div>

                        <div>
                            <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Status Bisnis</dt>
                            <dd class="mt-0.5">
                                @if($business->status === 'active')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        Nonaktif
                                    </span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Owner / Pemilik</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-200 mt-0.5">
                                {{ $business->owners->first()?->name ?? 'Belum ditentukan' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Email Owner</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-200 mt-0.5">
                                {{ $business->owners->first()?->email ?? '-' }}
                            </dd>
                        </div>
                    </dl>
                @else
                    <div class="py-6 text-center text-slate-400 dark:text-slate-500 italic text-xs">
                        Bisnis terkait tidak ditemukan atau telah dihapus dari sistem.
                    </div>
                @endif
            </div>

            {{-- Snapshot Langganan & Finansial --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="sparkles" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                        Snapshot Langganan &amp; Finansial
                    </h2>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400">
                    <i data-lucide="shield-alert" class="w-3.5 h-3.5 inline mr-1 text-slate-400"></i>
                    Nilai di bawah ini merupakan <strong>snapshot historis</strong> saat order dibuat, bukan harga katalog saat ini.
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-xs">
                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Paket Snapshot</dt>
                        <dd class="font-bold text-slate-900 dark:text-white mt-0.5">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold {{ $payment->plan === 'cloud' ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                                {{ ucfirst($payment->plan) }}
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Periode Tagihan Snapshot</dt>
                        <dd class="font-medium text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ $payment->billing_period === 'monthly' ? 'Bulanan (Monthly)' : ($payment->billing_period === 'yearly' ? 'Tahunan (Yearly)' : ucfirst($payment->billing_period)) }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Nominal Transaksi (Amount)</dt>
                        <dd class="font-black text-slate-900 dark:text-white text-sm mt-0.5">
                            Rp {{ number_format($payment->amount, 0, ',', '.') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Mata Uang</dt>
                        <dd class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ $payment->currency }}</dd>
                    </div>

                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">Masa Berlaku Snapshot</dt>
                        <dd class="font-medium text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ $payment->expires_at ? $payment->expires_at->format('d M Y H:i') : '-' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-400 dark:text-slate-500 text-[11px]">User Pembuat / Payer</dt>
                        <dd class="font-medium text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ $payment->user?->name ?? 'Sistem' }}
                            @if($payment->user?->email)
                                <span class="text-slate-400 block text-[10px]">{{ $payment->user->email }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Timeline Pembayaran --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                        Timeline Pembayaran
                    </h2>
                </div>

                <div class="relative pl-6 space-y-4 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800 text-xs">
                    {{-- Order Dibuat --}}
                    <div class="relative">
                        <span class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-indigo-600 border-2 border-white dark:border-slate-900"></span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200">Order Dibuat (Created At)</div>
                        <div class="text-[11px] text-slate-400 dark:text-slate-500">
                            {{ $payment->created_at ? $payment->created_at->format('d M Y H:i:s') : '-' }}
                        </div>
                    </div>

                    {{-- Waktu Pembayaran --}}
                    <div class="relative">
                        <span class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full {{ $payment->paid_at ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700' }} border-2 border-white dark:border-slate-900"></span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200">Waktu Pembayaran (Paid At)</div>
                        <div class="text-[11px] text-slate-400 dark:text-slate-500">
                            {{ $payment->paid_at ? $payment->paid_at->format('d M Y H:i:s') : 'Belum dibayar (-)' }}
                        </div>
                    </div>

                    {{-- Waktu Aktivasi --}}
                    <div class="relative">
                        <span class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full {{ $payment->activated_at ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700' }} border-2 border-white dark:border-slate-900"></span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200">Aktivasi Entitlement (Activated At)</div>
                        <div class="text-[11px] text-slate-400 dark:text-slate-500">
                            {{ $payment->activated_at ? $payment->activated_at->format('d M Y H:i:s') : 'Belum diaktivasi (-)' }}
                        </div>
                    </div>

                    {{-- Waktu Kedaluwarsa --}}
                    <div class="relative">
                        <span class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full {{ $payment->expires_at ? 'bg-slate-500' : 'bg-slate-300 dark:bg-slate-700' }} border-2 border-white dark:border-slate-900"></span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200">Masa Berlaku (Expires At)</div>
                        <div class="text-[11px] text-slate-400 dark:text-slate-500">
                            {{ $payment->expires_at ? $payment->expires_at->format('d M Y H:i:s') : '-' }}
                        </div>
                    </div>

                    {{-- Terakhir Diperbarui --}}
                    <div class="relative">
                        <span class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-slate-400 border-2 border-white dark:border-slate-900"></span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200">Terakhir Diperbarui (Updated At)</div>
                        <div class="text-[11px] text-slate-400 dark:text-slate-500">
                            {{ $payment->updated_at ? $payment->updated_at->format('d M Y H:i:s') : '-' }}
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Provider Metadata Aman --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="file-json" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                        Metadata Provider (Disaring Aman)
                    </h2>
                </div>
                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                    <i data-lucide="shield" class="w-3 h-3 inline mr-1 text-emerald-500"></i>
                    Kredensial dan signature key dikecualikan secara otomatis.
                </span>
            </div>

            @if(!empty($safeMetadata))
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs" aria-label="Metadata Aman Provider">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($safeMetadata as $label => $val)
                                <tr>
                                    <td class="py-2.5 pr-4 font-semibold text-slate-600 dark:text-slate-400 w-1/3">
                                        {{ $label }}
                                    </td>
                                    <td class="py-2.5 font-mono text-slate-900 dark:text-slate-100 break-all">
                                        {{ $val }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-6 text-center text-slate-400 dark:text-slate-500 italic text-xs">
                    Tidak ada metadata tambahan dari provider untuk transaksi ini.
                </div>
            @endif
        </div>

    </div>
</x-layouts::platform>
