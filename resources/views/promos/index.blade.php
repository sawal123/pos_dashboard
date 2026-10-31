<x-layouts::app :title="'Promo'">
    <main id="mainContent" data-promos-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                <nav class="flex items-center gap-1.5 text-xs text-slate-400 dark:text-slate-500 mb-1" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Dashboard</a>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    <span class="text-slate-700 dark:text-slate-300 font-semibold" aria-current="page">Promo</span>
                </nav>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Promo</h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Atur potongan harga yang tersinkron ke POS dan divalidasi ulang oleh server.
                </p>
            </div>
            @if($canManagePromos ?? false)
                <button type="button" id="openPromoBtn" class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-colors flex items-center gap-1.5">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Buat Promo
                </button>
            @endif
        </div>

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/30 px-4 py-3 flex items-start gap-3" role="status">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/30 px-4 py-3" role="alert">
                <div class="flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0"></i>
                    <ul class="pl-4 list-disc text-xs text-rose-700 dark:text-rose-300">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            @foreach([
                ['label' => 'Total Promo', 'value' => $summary['total'], 'class' => 'text-slate-900 dark:text-white'],
                ['label' => 'Aktif', 'value' => $summary['active'], 'class' => 'text-emerald-600 dark:text-emerald-400'],
                ['label' => 'Draft', 'value' => $summary['draft'], 'class' => 'text-amber-600 dark:text-amber-400'],
                ['label' => 'Terjadwal', 'value' => $summary['scheduled'], 'class' => 'text-indigo-600 dark:text-indigo-400'],
            ] as $card)
                <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $card['label'] }}</span>
                    <p class="mt-3 text-xl sm:text-2xl font-extrabold tabular-nums {{ $card['class'] }}">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs">
            <form action="{{ route('promos.index') }}" method="GET" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </span>
                    <input type="text" name="q" value="{{ $currentFilters['q'] }}" placeholder="Cari nama atau kode promo..." class="w-full pl-10 pr-4 py-2 text-xs sm:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <select name="status" onchange="this.form.submit()" class="min-w-[150px] px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Semua Status</option>
                    @foreach(['active' => 'Aktif', 'draft' => 'Draft', 'inactive' => 'Nonaktif'] as $value => $label)
                        <option value="{{ $value }}" @selected($currentFilters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold">Cari</button>
                <a href="{{ route('promos.index') }}" class="px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">Reset</a>
            </form>
        </div>

        @if($items->total() > 0)
            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                            <tr>
                                <th class="py-3.5 px-4">Promo</th>
                                <th class="py-3.5 px-4">Diskon</th>
                                <th class="py-3.5 px-4">Cakupan</th>
                                <th class="py-3.5 px-4">Periode</th>
                                <th class="py-3.5 px-4">Status</th>
                                <th class="py-3.5 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                            @foreach($items as $promo)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-4">
                                        <p class="font-bold text-slate-900 dark:text-white">{{ $promo['name'] }}</p>
                                        @if($promo['code'])
                                            <p class="font-mono text-[11px] text-slate-400">{{ $promo['code'] }}</p>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 font-bold text-rose-600 dark:text-rose-400">{{ $promo['discount_label'] }}</td>
                                    <td class="py-3 px-4 text-slate-600 dark:text-slate-300">{{ $promo['scope'] }}</td>
                                    <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                                        {{ $promo['starts_at'] ?? 'Kapan saja' }}
                                        @if($promo['ends_at']) – {{ $promo['ends_at'] }} @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold {{ $promo['status_raw'] === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : ($promo['status_raw'] === 'draft' ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400') }}">
                                            {{ $promo['status'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if($canManagePromos ?? false)
                                                <button
                                                    type="button"
                                                    class="edit-promo-btn px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs"
                                                    data-promo-raw="{{ json_encode($promo) }}"
                                                >Edit</button>
                                                <form method="POST" action="{{ route('promos.status.update', ['promoId' => $promo['id']]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ $promo['status_raw'] === 'active' ? 'inactive' : 'active' }}">
                                                    <button type="submit" class="px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs">
                                                        {{ $promo['status_raw'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if($items->hasPages())
                <div>{{ $items->links() }}</div>
            @endif
        @else
            <div class="p-10 rounded-2xl bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-700 text-center space-y-2">
                <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                    <i data-lucide="badge-percent" class="w-6 h-6"></i>
                </div>
                <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ ($hasAnyPromo ?? false) ? 'Promo tidak ditemukan' : 'Belum ada promo' }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ ($hasAnyPromo ?? false) ? 'Tidak ada promo yang cocok dengan filter saat ini.' : 'Buat promo pertama agar kasir bisa memakainya dari POS.' }}
                </p>
            </div>
        @endif

        @if($canManagePromos ?? false)
            <x-promos.promo-modal :products="$products" :categories="$categories" />
        @endif
    </main>

    <script>
        function initPromosPage() {
            const root = document.querySelector('main[data-promos-page="true"]');
            if (!root || root.dataset.promosInitialized === 'true') {
                return;
            }
            root.dataset.promosInitialized = 'true';

            const modal = document.getElementById('promoModal');
            if (!modal) {
                if (window.lucide) window.lucide.createIcons();
                return;
            }

            const form = document.getElementById('promoForm');
            const subtitle = document.getElementById('promoModalSubtitle');
            const title = document.getElementById('promoModalTitle');

            const setValue = (name, value) => {
                const field = form.elements.namedItem(name);
                if (field) field.value = value === null || value === undefined ? '' : value;
            };

            const selectTargets = (scope, ids) => {
                const select = document.getElementById('promoTargets');
                if (!select) return;
                Array.from(select.options).forEach((option) => {
                    const matchesScope = option.dataset.targetType === scope;
                    option.selected = matchesScope && ids.includes(Number(option.value));
                });
            };

            const open = () => {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            };

            const close = () => {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            };

            document.getElementById('openPromoBtn')?.addEventListener('click', () => {
                form.reset();
                form.setAttribute('action', form.dataset.storeUrl);
                setValue('_method', 'POST');
                setValue('status', 'draft');
                setValue('scope', 'all');
                setValue('discount_type', 'percent');
                setValue('discount_value', '0');
                selectTargets('all', []);
                if (subtitle) subtitle.textContent = 'Tambah';
                if (title) title.textContent = 'Promo';
                open();
            });

            document.querySelectorAll('.edit-promo-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    let raw = {};
                    try {
                        raw = JSON.parse(button.dataset.promoRaw || '{}');
                    } catch (error) {
                        return;
                    }

                    form.reset();
                    form.setAttribute('action', form.dataset.updateUrlTemplate.replace('__ID__', raw.id));
                    setValue('_method', 'PATCH');
                    setValue('name', raw.name || '');
                    setValue('code', raw.code || '');
                    setValue('discount_type', raw.discount_type_raw || 'percent');
                    setValue('discount_value', raw.discount_value ?? 0);
                    setValue('scope', raw.scope_raw || 'all');
                    setValue('min_purchase', raw.min_purchase ?? 0);
                    setValue('max_discount', raw.max_discount ?? '');
                    setValue('starts_at', raw.starts_at_raw || '');
                    setValue('ends_at', raw.ends_at_raw || '');
                    setValue('start_time', raw.start_time || '');
                    setValue('end_time', raw.end_time || '');
                    setValue('usage_limit_total', raw.usage_limit_total ?? '');
                    setValue('usage_limit_per_customer', raw.usage_limit_per_customer ?? '');
                    setValue('status', raw.status_raw || 'draft');

                    const ids = Array.isArray(raw.targets) ? raw.targets.map((target) => Number(target.id)) : [];
                    selectTargets(raw.scope_raw || 'all', ids);

                    if (subtitle) subtitle.textContent = 'Edit';
                    if (title) title.textContent = raw.name || 'Promo';
                    open();
                });
            });

            modal.querySelectorAll('[data-close-promo-modal]').forEach((button) => {
                button.addEventListener('click', close);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    close();
                }
            });

            if (window.lucide) window.lucide.createIcons();
        }

        initPromosPage();

        if (!window.__promosListenersBound) {
            window.__promosListenersBound = true;
            document.addEventListener('DOMContentLoaded', initPromosPage);
            document.addEventListener('livewire:navigated', initPromosPage);
        }
    </script>
</x-layouts::app>
