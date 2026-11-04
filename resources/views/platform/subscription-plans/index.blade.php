<x-layouts::platform :title="'Paket & Harga'">
    <div class="space-y-6">

        {{-- Flash Notification --}}
        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2.5 shadow-xs">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Page Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-black tracking-tight text-slate-900 dark:text-white">Paket &amp; Harga</h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Kelola paket langganan (Free, Cloud, dll.) beserta harga Bulanan dan Tahunan.
                </p>
            </div>
            <div>
                <button
                    type="button"
                    onclick="document.getElementById('create-plan-modal').classList.remove('hidden')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                >
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Tambah Paket</span>
                </button>
            </div>
        </div>

        {{-- Checkout Readiness --}}
        @php
            $isReady = (bool) ($readiness['ready'] ?? false);
        @endphp
        <div class="p-5 rounded-2xl border shadow-xs {{ $isReady ? 'bg-emerald-50/70 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800' : 'bg-amber-50/70 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800' }}">
            <div class="flex items-start gap-3">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $isReady ? 'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400' : 'bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-400' }}">
                    <i data-lucide="{{ $isReady ? 'check-circle' : 'alert-triangle' }}" class="w-5 h-5"></i>
                </span>
                <div class="space-y-1.5 min-w-0">
                    <h2 class="text-sm font-bold {{ $isReady ? 'text-emerald-800 dark:text-emerald-300' : 'text-amber-800 dark:text-amber-300' }}">
                        {{ $isReady ? 'Checkout siap' : 'Checkout belum siap' }}
                    </h2>
                    @if($isReady)
                        <p class="text-[11px] text-emerald-700 dark:text-emerald-400">
                            Paket Cloud aktif dengan harga Bulanan &amp; Tahunan yang valid, dan Midtrans sudah dikonfigurasi.
                        </p>
                    @else
                        <ul class="list-disc list-inside space-y-0.5 text-[11px] text-amber-700 dark:text-amber-400">
                            @foreach($readiness['reasons'] as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- Plan Cards --}}
        @forelse($plans as $plan)
            @php
                $prices = $plan->prices->keyBy('billing_period');
                $monthly = $prices->get('monthly');
                $yearly = $prices->get('yearly');
                $isFree = $plan->code === 'free';
            @endphp
            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs p-6 space-y-5">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="w-12 h-12 rounded-2xl bg-gradient-to-br {{ $isFree ? 'from-emerald-600 to-teal-700' : 'from-indigo-600 to-purple-700' }} text-white flex items-center justify-center font-black shrink-0 shadow-xs">
                            <i data-lucide="{{ $isFree ? 'sparkles' : 'cloud' }}" class="w-6 h-6"></i>
                        </span>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg font-black tracking-tight text-slate-900 dark:text-white">{{ $plan->name }}</h2>
                                <span class="font-mono text-[11px] px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">{{ $plan->code }}</span>
                                @if($plan->is_active)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        Nonaktif
                                    </span>
                                @endif
                            </div>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                {{ $plan->description ?: 'Tidak ada deskripsi.' }} &bull; Terakhir diperbarui: {{ $plan->updated_at?->format('d M Y H:i') ?? '-' }}
                            </p>
                        </div>
                    </div>
                    <div class="shrink-0">
                        <a
                            href="{{ route('platform.subscription-plans.show', $plan) }}"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <i data-lucide="settings-2" class="w-4 h-4"></i>
                            <span>Kelola</span>
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Monthly --}}
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Bulanan</span>
                            @if($monthly && $monthly->is_active)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">Aktif</span>
                            @elseif($monthly)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">Nonaktif</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">Belum ada harga</span>
                            @endif
                        </div>
                        <p class="mt-2 text-xl font-black text-slate-900 dark:text-white">
                            @if($monthly)
                                @if($monthly->price_minor === 0)
                                    Rp 0 <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">(Gratis)</span>
                                @else
                                    Rp {{ number_format($monthly->price_minor, 0, ',', '.') }}
                                    <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">/ bulan</span>
                                @endif
                            @else
                                <span class="text-sm font-semibold text-slate-400 dark:text-slate-500 italic">Belum ditetapkan</span>
                            @endif
                        </p>
                    </div>

                    {{-- Yearly --}}
                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Tahunan</span>
                            @if($yearly && $yearly->is_active)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">Aktif</span>
                            @elseif($yearly)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">Nonaktif</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">Belum ada harga</span>
                            @endif
                        </div>
                        <p class="mt-2 text-xl font-black text-slate-900 dark:text-white">
                            @if($yearly)
                                @if($yearly->price_minor === 0)
                                    Rp 0 <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">(Gratis)</span>
                                @else
                                    Rp {{ number_format($yearly->price_minor, 0, ',', '.') }}
                                    <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">/ tahun</span>
                                @endif
                            @else
                                <span class="text-sm font-semibold text-slate-400 dark:text-slate-500 italic">Belum ditetapkan</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-700 p-10 text-center space-y-2">
                <i data-lucide="package-x" class="w-8 h-8 mx-auto text-slate-300 dark:text-slate-600"></i>
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-300">Belum ada paket langganan</h2>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                    Klik tombol "Tambah Paket" untuk membuat paket baru (misalnya Paket Free atau Cloud).
                </p>
            </div>
        @endforelse

        {{-- Create Plan Modal --}}
        <div
            id="create-plan-modal"
            class="{{ ($errors->has('code') || $errors->has('name') || $errors->has('description') || $errors->has('is_active')) ? '' : 'hidden' }} fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                            <i data-lucide="plus-circle" class="w-5 h-5"></i>
                        </span>
                        <div>
                            <h2 class="text-base font-black text-slate-900 dark:text-white">Tambah Paket Langganan</h2>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">Buat paket baru untuk pelanggan (misal: free, cloud, pro).</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onclick="document.getElementById('create-plan-modal').classList.add('hidden')"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    >
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('platform.subscription-plans.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="new_plan_code" class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">
                            Kode Paket <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="new_plan_code"
                            name="code"
                            type="text"
                            placeholder="cth: free, basic, enterprise"
                            value="{{ old('code') }}"
                            maxlength="50"
                            pattern="^[a-z0-9_-]+$"
                            class="w-full px-3 py-2 rounded-xl text-xs font-mono bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            required
                        />
                        <p class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">Huruf kecil, angka, tanda hubung (-), atau garis bawah (_). Unik dan permanen.</p>
                        @error('code')<p class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="new_plan_name" class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">
                            Nama Paket <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="new_plan_name"
                            name="name"
                            type="text"
                            placeholder="cth: Paket Free, Paket Starter"
                            value="{{ old('name') }}"
                            maxlength="100"
                            class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            required
                        />
                        @error('name')<p class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="new_plan_description" class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">
                            Deskripsi (Opsional)
                        </label>
                        <textarea
                            id="new_plan_description"
                            name="description"
                            rows="3"
                            maxlength="1000"
                            placeholder="Jelaskan cakupan fitur atau manfaat paket ini..."
                            class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >{{ old('description') }}</textarea>
                        @error('description')<p class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <label class="flex items-center gap-2.5 cursor-pointer pt-1">
                        <input type="hidden" name="is_active" value="0" />
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500"
                            @checked(old('is_active', true))
                        />
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Aktifkan paket langsung</span>
                    </label>
                    @error('is_active')<p class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror

                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            onclick="document.getElementById('create-plan-modal').classList.add('hidden')"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                        >
                            <i data-lucide="check" class="w-4 h-4"></i>
                            <span>Simpan Paket</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-layouts::platform>
