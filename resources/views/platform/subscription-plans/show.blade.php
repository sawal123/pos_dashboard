<x-layouts::platform :title="'Paket: ' . $plan->name">
    @php
        $isFree = $plan->code === 'free';
        $isCloud = $plan->code === 'cloud';
    @endphp
    <div class="space-y-6">

        {{-- Flash Notification --}}
        @if(session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2.5 shadow-xs">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Back Navigation --}}
        <div>
            <a
                href="{{ route('platform.subscription-plans.index') }}"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
            >
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Kembali ke Paket &amp; Harga</span>
            </a>
        </div>

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <div class="flex items-center gap-4">
                <span class="w-14 h-14 rounded-2xl bg-gradient-to-br {{ $isFree ? 'from-emerald-600 to-teal-700' : 'from-indigo-600 to-purple-700' }} text-white flex items-center justify-center font-black text-lg shrink-0 shadow-xs">
                    <i data-lucide="{{ $isFree ? 'sparkles' : 'cloud' }}" class="w-7 h-7"></i>
                </span>
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">{{ $plan->name }}</h1>
                        <span class="font-mono text-xs px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">{{ $plan->code }}</span>
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
                        Terakhir diperbarui: {{ $plan->updated_at?->format('d M Y H:i') ?? '-' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Checkout Readiness (hanya berlaku untuk paket kanonik Cloud berbayar) --}}
        @if($isCloud)
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
        @endif

        {{-- Plan Detail Form --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs p-6 space-y-5">
            <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100 dark:border-slate-800">
                <span class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                    <i data-lucide="settings-2" class="w-4 h-4"></i>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Detail Paket</h2>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">Nama, deskripsi, dan ketersediaan paket {{ $plan->name }}.</p>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('platform.subscription-plans.update', $plan) }}"
                class="space-y-4"
                data-confirm-if-unchecked="#plan_is_active"
                data-confirm-message="{{ 'Nonaktifkan paket '.$plan->name.'?' }}"
            >
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="plan_name" class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Nama Paket</label>
                        <input
                            id="plan_name"
                            name="name"
                            type="text"
                            value="{{ old('name', $plan->name) }}"
                            maxlength="100"
                            class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            required
                        />
                        @error('name')<p class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="plan_code" class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Kode Paket (tidak dapat diubah)</label>
                        <input
                            id="plan_code"
                            type="text"
                            value="{{ $plan->code }}"
                            class="w-full px-3 py-2 rounded-xl text-xs font-mono bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 cursor-not-allowed"
                            disabled
                        />
                    </div>
                </div>

                <div>
                    <label for="plan_description" class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Deskripsi</label>
                    <textarea
                        id="plan_description"
                        name="description"
                        rows="3"
                        maxlength="1000"
                        class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >{{ old('description', $plan->description) }}</textarea>
                    @error('description')<p class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                {{-- Keunggulan / Fitur Paket (Tampil di Landing Page) --}}
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">
                                Keunggulan &amp; Fitur Paket (Tampil di Landing Page)
                            </label>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500">
                                Tambah, ubah teks, atau hapus poin keunggulan paket ini. Poin ini langsung dirender di kartu landing page.
                            </p>
                        </div>
                        <button
                            type="button"
                            onclick="addFeatureInput()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60 transition-colors cursor-pointer"
                        >
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            <span>Tambah Keunggulan</span>
                        </button>
                    </div>

                    <div id="features-container" class="space-y-2">
                        @php
                            $featuresList = old('features', $plan->features ?? []);
                            if (!is_array($featuresList) || empty($featuresList)) {
                                $featuresList = $plan->code === 'cloud' 
                                    ? ['Semua fitur Paket Free', 'Sinkronisasi Cloud otomatis & real-time', 'Web Dashboard analitik laba rugi', 'Varian Menu (Cup, Sugar, Level)', 'Hingga 5 perangkat kasir terhubung', 'Manajemen Promo & Diskon kasir', 'Backup & restore data cloud aman']
                                    : ['Aplikasi POS kasir offline cepat', 'Transaksi & cetak struk tanpa internet', 'Katalog produk & kategori lokal', 'Hitung HPP & pantau stok bahan baku', '1 perangkat kasir lokal'];
                            }
                        @endphp
                        @foreach($featuresList as $feature)
                            <div class="feature-item flex items-center gap-2">
                                <span class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                    <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                </span>
                                <input
                                    type="text"
                                    name="features[]"
                                    value="{{ $feature }}"
                                    placeholder="Tulis keunggulan paket..."
                                    maxlength="255"
                                    class="flex-1 px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                <button
                                    type="button"
                                    onclick="removeFeatureItem(this)"
                                    class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer"
                                    title="Hapus keunggulan ini"
                                >
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>

                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="hidden" name="is_active" value="0" />
                    <input
                        type="checkbox"
                        id="plan_is_active"
                        name="is_active"
                        value="1"
                        class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500"
                        @checked(old('is_active', $plan->is_active))
                    />
                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Paket aktif</span>
                </label>
                @error('is_active')<p class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror

                <div class="pt-1">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <i data-lucide="save" class="w-3.5 h-3.5"></i>
                        <span>Simpan Paket</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Price Forms --}}
        @foreach($billingPeriods as $period)
            @php
                $price = $prices->get($period);
                $label = $period === 'monthly' ? 'Harga Bulanan' : 'Harga Tahunan';
                $suffix = $period === 'monthly' ? '/ bulan' : '/ tahun';
            @endphp
            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs p-6 space-y-5">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <i data-lucide="wallet" class="w-4 h-4"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ $label }}</h2>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">Harga IDR dalam satuan minor (integer, tanpa desimal).</p>
                        </div>
                    </div>
                    @if($price && $price->is_active)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">Aktif</span>
                    @elseif($price)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">Nonaktif</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">Belum ada harga</span>
                    @endif
                </div>

                <div class="flex items-center justify-between text-[11px]">
                    <span class="text-slate-400 dark:text-slate-500">Harga saat ini</span>
                    @if($price)
                        <span class="font-bold text-slate-900 dark:text-white">
                            @if($price->price_minor === 0)
                                Rp 0 <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">(Gratis)</span>
                            @else
                                Rp {{ number_format($price->price_minor, 0, ',', '.') }} {{ $suffix }}
                            @endif
                        </span>
                    @else
                        <span class="text-slate-400 dark:text-slate-500 italic">Belum ditetapkan</span>
                    @endif
                </div>

                @if($price)
                    <form method="POST" action="{{ route('platform.subscription-plans.prices.update', [$plan, $price]) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                @else
                    <form method="POST" action="{{ route('platform.subscription-plans.prices.store', $plan) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="billing_period" value="{{ $period }}" />
                @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                        <div>
                            <label for="{{ $period }}_price" class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">
                                Harga ({{ $suffix }})
                            </label>
                            <input
                                id="{{ $period }}_price"
                                name="price_minor"
                                type="number"
                                min="{{ $isCloud ? 1 : 0 }}"
                                step="1"
                                inputmode="numeric"
                                value="{{ old('price_minor', $price?->price_minor) }}"
                                placeholder="cth: {{ $isFree ? '0' : ($period === 'monthly' ? '49000' : '490000') }}"
                                class="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                required
                            />
                            @error('price_minor')<p class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <label class="flex items-center gap-2.5 cursor-pointer pb-2">
                            <input type="hidden" name="is_active" value="0" />
                            <input
                                type="checkbox"
                                id="{{ $period }}_is_active"
                                name="is_active"
                                value="1"
                                class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500"
                                @checked(old('is_active', $price?->is_active ?? true))
                            />
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Harga aktif</span>
                        </label>
                    </div>
                    @error('is_active')<p class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror

                    @if(! $price)
                        @error('billing_period')<p class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 italic">
                            Belum ada harga {{ $period }}. Simpan untuk menetapkan harga pertama.
                        </p>
                    @endif

                    <div class="pt-1">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <i data-lucide="save" class="w-3.5 h-3.5"></i>
                            <span>Simpan {{ $label }}</span>
                        </button>
                    </div>
                </form>
            </div>
        @endforeach

    </div>

    <script>
        function addFeatureInput(value = '') {
            const container = document.getElementById('features-container');
            const div = document.createElement('div');
            div.className = 'feature-item flex items-center gap-2';
            div.innerHTML = `
                <span class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                    <i data-lucide="check" class="w-3.5 h-3.5"></i>
                </span>
                <input
                    type="text"
                    name="features[]"
                    value="${value}"
                    placeholder="Tulis keunggulan paket..."
                    maxlength="255"
                    class="flex-1 px-3 py-2 rounded-xl text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <button
                    type="button"
                    onclick="removeFeatureItem(this)"
                    class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer"
                    title="Hapus keunggulan ini"
                >
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            `;
            container.appendChild(div);
            if (window.lucide) {
                window.lucide.createIcons({ scope: div });
            }
        }

        function removeFeatureItem(btn) {
            const item = btn.closest('.feature-item');
            if (item) {
                item.remove();
            }
        }
    </script>
</x-layouts::platform>
