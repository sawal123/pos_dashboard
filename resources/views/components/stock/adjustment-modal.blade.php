@props([
    'products' => [],
])

@php
    $oldMode = old('form_mode');
    $oldLines = old('lines', []);
    $lineCount = max(3, is_array($oldLines) ? count($oldLines) : 0);
    $defaultKey = (string) \Illuminate\Support\Str::uuid();
@endphp

<div
    id="stockMovementModal"
    class="fixed inset-0 z-[90] hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="stockMovementModalTitle"
    data-restore-mode="{{ in_array($oldMode, ['adjustment', 'receipt'], true) ? $oldMode : '' }}"
    tabindex="-1"
>
    <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs" data-close-stock-movement></div>

    <div class="fixed inset-0 flex items-start sm:items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="w-full max-w-2xl my-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl">
            <div class="flex items-center justify-between gap-3 p-5 border-b border-slate-200/80 dark:border-slate-800">
                <div>
                    <h2 id="stockMovementModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">
                        Catat Pergerakan Stok
                    </h2>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        Setiap pencatatan menghasilkan satu baris riwayat stok.
                    </p>
                </div>
                <button type="button" data-close-stock-movement class="p-2 rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" aria-label="Tutup">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            {{-- Tabs --}}
            <div class="px-5 pt-4">
                <div class="flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800 w-fit">
                    <button type="button" data-stock-tab="adjustment" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                        Penyesuaian
                    </button>
                    <button type="button" data-stock-tab="receipt" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                        Penerimaan
                    </button>
                </div>
            </div>

            {{-- ==================== Adjustment ==================== --}}
            <form
                method="POST"
                action="{{ route('stock.adjustments.store') }}"
                class="p-5 space-y-4"
                data-stock-panel="adjustment"
            >
                @csrf
                <input type="hidden" name="form_mode" value="adjustment">
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $defaultKey) }}">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="adjustmentProduct">Produk</label>
                    <select
                        id="adjustmentProduct"
                        name="product_id"
                        required
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">Pilih produk</option>
                        @foreach($products as $product)
                            <option value="{{ $product['id'] }}" {{ (string) old('product_id') === (string) $product['id'] ? 'selected' : '' }}>
                                {{ $product['name'] }} ({{ $product['sku'] }}) — {{ $product['unit'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('product_id')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="adjustmentType">Jenis</label>
                        <select
                            id="adjustmentType"
                            name="type"
                            required
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="adjustment" {{ old('type', 'adjustment') === 'adjustment' ? 'selected' : '' }}>Penyesuaian</option>
                            <option value="usage" {{ old('type') === 'usage' ? 'selected' : '' }}>Pemakaian</option>
                            <option value="return" {{ old('type') === 'return' ? 'selected' : '' }}>Retur</option>
                        </select>
                        @error('type')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="adjustmentDirection">Arah</label>
                        <select
                            id="adjustmentDirection"
                            name="direction"
                            required
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="in" {{ old('direction', 'in') === 'in' ? 'selected' : '' }}>Tambah stok</option>
                            <option value="out" {{ old('direction') === 'out' ? 'selected' : '' }}>Kurangi stok</option>
                        </select>
                        @error('direction')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="adjustmentQuantity">Jumlah</label>
                    <input
                        id="adjustmentQuantity"
                        type="number"
                        name="quantity"
                        step="0.001"
                        min="0.001"
                        required
                        value="{{ old('quantity') }}"
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                    @error('quantity')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="adjustmentNote">Catatan</label>
                    <textarea
                        id="adjustmentNote"
                        name="note"
                        rows="2"
                        maxlength="500"
                        class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >{{ old('note') }}</textarea>
                    @error('note')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" data-close-stock-movement class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition-colors">
                        Simpan Penyesuaian
                    </button>
                </div>
            </form>

            {{-- ==================== Receipt ==================== --}}
            <form
                method="POST"
                action="{{ route('stock.receipts.store') }}"
                class="p-5 space-y-4"
                data-stock-panel="receipt"
            >
                @csrf
                <input type="hidden" name="form_mode" value="receipt">
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $defaultKey) }}">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="receiptReference">Nomor Referensi</label>
                        <input
                            id="receiptReference"
                            type="text"
                            name="reference_id"
                            maxlength="255"
                            value="{{ old('reference_id') }}"
                            placeholder="mis. PO-2026-001"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        @error('reference_id')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5" for="receiptNote">Catatan</label>
                        <input
                            id="receiptNote"
                            type="text"
                            name="note"
                            maxlength="500"
                            value="{{ old('note') }}"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                        @error('note')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Baris Penerimaan</span>
                        <button type="button" id="addReceiptLineBtn" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                            + Tambah baris
                        </button>
                    </div>

                    <div id="receiptLines" class="space-y-2">
                        @for($i = 0; $i < $lineCount; $i++)
                            <div class="grid grid-cols-1 sm:grid-cols-[1fr_100px_130px] gap-2 receipt-line">
                                <select
                                    name="lines[{{ $i }}][product_id]"
                                    class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">Pilih produk</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product['id'] }}" {{ (string) old('lines.'.$i.'.product_id') === (string) $product['id'] ? 'selected' : '' }}>
                                            {{ $product['name'] }} ({{ $product['sku'] }})
                                        </option>
                                    @endforeach
                                </select>
                                <input
                                    type="number"
                                    name="lines[{{ $i }}][quantity]"
                                    step="0.001"
                                    min="0.001"
                                    placeholder="Jumlah"
                                    value="{{ old('lines.'.$i.'.quantity') }}"
                                    class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                <input
                                    type="number"
                                    name="lines[{{ $i }}][unit_cost]"
                                    step="0.01"
                                    min="0"
                                    placeholder="HPP / unit"
                                    value="{{ old('lines.'.$i.'.unit_cost') }}"
                                    class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                            </div>
                        @endfor
                    </div>

                    @error('lines')<p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    @error('lines.*')<p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" data-close-stock-movement class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition-colors">
                        Simpan Penerimaan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <template id="receiptLineTemplate">
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_100px_130px] gap-2 receipt-line">
            <select class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Pilih produk</option>
                @foreach($products as $product)
                    <option value="{{ $product['id'] }}">{{ $product['name'] }} ({{ $product['sku'] }})</option>
                @endforeach
            </select>
            <input type="number" step="0.001" min="0.001" placeholder="Jumlah" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input type="number" step="0.01" min="0" placeholder="HPP / unit" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
    </template>
</div>
