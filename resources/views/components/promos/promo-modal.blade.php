@props([
    'products' => [],
    'categories' => [],
])

@php
    $discountTypes = \App\Enums\PromoDiscountType::cases();
    $scopes = \App\Enums\PromoScope::cases();
    $statuses = \App\Enums\PromoStatus::cases();
@endphp

<div id="promoModal" class="fixed inset-0 z-[90] hidden" role="dialog" aria-modal="true" aria-labelledby="promoModalTitle" tabindex="-1">
    <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs" data-close-promo-modal></div>

    <div class="fixed inset-0 flex items-start sm:items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="w-full max-w-2xl my-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl">
            <div class="flex items-center justify-between gap-3 p-5 border-b border-slate-200/80 dark:border-slate-800">
                <div>
                    <span id="promoModalSubtitle" class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Tambah</span>
                    <h2 id="promoModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">Promo</h2>
                </div>
                <button type="button" data-close-promo-modal class="p-2 rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" aria-label="Tutup">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <form
                id="promoForm"
                method="POST"
                action="{{ route('promos.store') }}"
                data-store-url="{{ route('promos.store') }}"
                data-update-url-template="{{ route('promos.update', ['promoId' => '__ID__']) }}"
                class="p-5 space-y-4"
                novalidate
            >
                @csrf
                <input type="hidden" name="_method" value="POST" id="promoMethod">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="promoName" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Nama Promo</label>
                        <input id="promoName" name="name" type="text" required maxlength="120" value="{{ old('name') }}" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('name')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="promoCode" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Kode (opsional)</label>
                        <input id="promoCode" name="code" type="text" maxlength="40" value="{{ old('code') }}" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @error('code')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="promoType" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Jenis Diskon</label>
                        <select id="promoType" name="discount_type" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @foreach($discountTypes as $type)
                                <option value="{{ $type->value }}" @selected(old('discount_type') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="promoValue" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Nilai</label>
                        <input id="promoValue" name="discount_value" type="number" min="0" step="1" required value="{{ old('discount_value', 0) }}" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <p class="mt-1 text-[11px] text-slate-400">Persen dalam basis point: 1000 = 10%.</p>
                        @error('discount_value')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="promoScope" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Cakupan</label>
                        <select id="promoScope" name="scope" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @foreach($scopes as $scope)
                                <option value="{{ $scope->value }}" @selected(old('scope', 'all') === $scope->value)>{{ $scope->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="promoMinPurchase" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Minimal Belanja (Rp)</label>
                        <input id="promoMinPurchase" name="min_purchase" type="number" min="0" step="1" value="{{ old('min_purchase', 0) }}" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="promoMaxDiscount" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Batas Diskon (Rp)</label>
                        <input id="promoMaxDiscount" name="max_discount" type="number" min="0" step="1" value="{{ old('max_discount') }}" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="promoTargets" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Target</label>
                    <select id="promoTargets" name="target_ids[]" multiple size="4" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach($products as $product)
                            <option value="{{ $product['id'] }}" data-target-type="product">{{ $product['name'] }} ({{ $product['sku'] ?? '-' }})</option>
                        @endforeach
                        @foreach($categories as $category)
                            <option value="{{ $category['id'] }}" data-target-type="category">{{ $category['name'] }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-400">Dipakai hanya saat cakupan bukan "Semua Item".</p>
                    @error('targets')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label for="promoStartsAt" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Mulai</label>
                        <input id="promoStartsAt" name="starts_at" type="date" value="{{ old('starts_at') }}" class="w-full h-10 px-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="promoEndsAt" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Selesai</label>
                        <input id="promoEndsAt" name="ends_at" type="date" value="{{ old('ends_at') }}" class="w-full h-10 px-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="promoStartTime" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Jam Mulai</label>
                        <input id="promoStartTime" name="start_time" type="time" value="{{ old('start_time') }}" class="w-full h-10 px-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="promoEndTime" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Jam Selesai</label>
                        <input id="promoEndTime" name="end_time" type="time" value="{{ old('end_time') }}" class="w-full h-10 px-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="promoUsageTotal" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Kuota Total</label>
                        <input id="promoUsageTotal" name="usage_limit_total" type="number" min="1" step="1" value="{{ old('usage_limit_total') }}" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="promoUsageCustomer" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Kuota / Pelanggan</label>
                        <input id="promoUsageCustomer" name="usage_limit_per_customer" type="number" min="1" step="1" value="{{ old('usage_limit_per_customer') }}" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="promoStatus" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Status</label>
                        <select id="promoStatus" name="status" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', 'draft') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" data-close-promo-modal class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition-colors">
                        Simpan Promo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
