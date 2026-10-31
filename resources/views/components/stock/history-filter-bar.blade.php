@props([
    'products' => [],
    'users' => [],
    'filters' => [
        'q' => '',
        'product_id' => '',
        'type' => [],
        'user_id' => '',
        'from' => '',
        'to' => '',
    ],
])

@php
    $selectedTypes = is_array($filters['type'] ?? null) ? $filters['type'] : [];
    $selectedType = $selectedTypes[0] ?? '';
    $types = \App\Enums\StockMovementType::cases();
@endphp

<div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs">
    <form action="{{ route('stock.history') }}" method="GET" class="space-y-3">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3">
            {{-- Search --}}
            <div class="relative flex-1">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400 dark:text-slate-500">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </span>
                <input
                    type="text"
                    name="q"
                    value="{{ $filters['q'] ?? '' }}"
                    placeholder="Cari nama produk atau SKU..."
                    class="w-full pl-10 pr-4 py-2 text-xs sm:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                    autocomplete="off"
                >
            </div>

            {{-- Product --}}
            <select
                name="product_id"
                onchange="this.form.submit()"
                class="min-w-[160px] px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
            >
                <option value="">Semua Produk</option>
                @foreach($products as $product)
                    <option value="{{ $product['id'] }}" {{ (string) ($filters['product_id'] ?? '') === (string) $product['id'] ? 'selected' : '' }}>
                        {{ $product['name'] }} ({{ $product['sku'] }})
                    </option>
                @endforeach
            </select>

            {{-- Type --}}
            <select
                name="type"
                onchange="this.form.submit()"
                class="min-w-[140px] px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
            >
                <option value="">Semua Tipe</option>
                @foreach($types as $type)
                    <option value="{{ $type->value }}" {{ $selectedType === $type->value ? 'selected' : '' }}>
                        {{ $type->label() }}
                    </option>
                @endforeach
            </select>

            {{-- User --}}
            @if(count($users) > 0)
                <select
                    name="user_id"
                    onchange="this.form.submit()"
                    class="min-w-[140px] px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                >
                    <option value="">Semua Pengguna</option>
                    @foreach($users as $user)
                        <option value="{{ $user['id'] }}" {{ (string) ($filters['user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' }}>
                            {{ $user['name'] }}
                        </option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2 flex-wrap">
                <label class="text-[11px] font-semibold text-slate-500 dark:text-slate-400" for="filterHistoryFrom">Dari</label>
                <input
                    type="date"
                    id="filterHistoryFrom"
                    name="from"
                    value="{{ $filters['from'] ?? '' }}"
                    class="px-3 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                <label class="text-[11px] font-semibold text-slate-500 dark:text-slate-400" for="filterHistoryTo">Sampai</label>
                <input
                    type="date"
                    id="filterHistoryTo"
                    name="to"
                    value="{{ $filters['to'] ?? '' }}"
                    class="px-3 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                <button type="submit" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition-colors">
                    Terapkan
                </button>
            </div>

            <a
                href="{{ route('stock.history') }}"
                class="px-3 py-1.5 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors flex items-center gap-1.5"
            >
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5 text-slate-400"></i>
                Reset
            </a>
        </div>
    </form>
</div>
