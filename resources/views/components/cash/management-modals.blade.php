@props([
    'canManageCash' => false,
    'outlets' => [],
    'shifts' => [],
])

@php
    $cashErrors = $errors->getBag('cashLedger');
    $expenseErrors = $errors->getBag('expense');
    $defaultOccurredAt = now()->format('Y-m-d\TH:i');
    $cashIdempotencyKey = old('idempotency_key', (string) \Illuminate\Support\Str::uuid());
    $expenseIdempotencyKey = old('idempotency_key', (string) \Illuminate\Support\Str::uuid());
@endphp

@if($canManageCash)
    {{-- ==================== MODAL 1: CATAT KAS ==================== --}}
    <div
        id="cashLedgerModal"
        data-cash-modal
        data-open-on-load="{{ $cashErrors->any() ? 'true' : 'false' }}"
        class="hidden fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm p-4 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cashLedgerModalTitle"
    >
        <div class="min-h-full flex items-center justify-center">
            <section class="w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <i data-lucide="arrow-down-left" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h2 id="cashLedgerModalTitle" class="text-base font-bold text-slate-900 dark:text-white">Catat Kas</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Catat kas masuk atau kas keluar manual untuk pergerakan kas fisik.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        data-cash-modal-close
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                        aria-label="Tutup"
                    >
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                {{-- Modal Form --}}
                <form method="POST" action="{{ route('cash.ledger.store') }}" class="p-6 space-y-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $cashIdempotencyKey }}">

                    @if($cashErrors->any())
                        <div class="rounded-xl border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/40 p-3.5 text-xs text-rose-700 dark:text-rose-300">
                            <p class="font-bold mb-1">Periksa kembali formulir catatan kas:</p>
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach($cashErrors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Jenis Kas --}}
                        <div>
                            <label for="cashType" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Jenis Transaksi Kas</label>
                            <select
                                id="cashType"
                                name="type"
                                class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                            >
                                <option value="in" @selected(old('type') === 'in')>Kas Masuk (Tambah Saldo)</option>
                                <option value="out" @selected(old('type') === 'out')>Kas Keluar (Kurang Saldo)</option>
                            </select>
                            @if($cashErrors->has('type'))
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $cashErrors->first('type') }}</p>
                            @endif
                        </div>

                        {{-- Nominal --}}
                        <div>
                            <label for="cashAmount" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Nominal (Rp)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-xs font-bold text-slate-400 dark:text-slate-500">Rp</div>
                                <input
                                    type="number"
                                    id="cashAmount"
                                    name="amount"
                                    min="1"
                                    step="1"
                                    value="{{ old('amount') }}"
                                    required
                                    placeholder="0"
                                    class="w-full h-10 pl-10 pr-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm font-semibold text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                                >
                            </div>
                            @if($cashErrors->has('amount'))
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $cashErrors->first('amount') }}</p>
                            @endif
                        </div>

                        {{-- Outlet --}}
                        <div>
                            <label for="cashOutletId" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Outlet</label>
                            <select
                                id="cashOutletId"
                                name="outlet_id"
                                data-cash-outlet-select
                                required
                                class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                            >
                                <option value="">Pilih outlet</option>
                                @foreach($outlets as $outlet)
                                    <option value="{{ $outlet['id'] }}" @selected((string) old('outlet_id') === (string) $outlet['id'])>{{ $outlet['name'] }}</option>
                                @endforeach
                            </select>
                            @if($cashErrors->has('outlet_id'))
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $cashErrors->first('outlet_id') }}</p>
                            @endif
                        </div>

                        {{-- Shift --}}
                        <div>
                            <label for="cashShiftId" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Shift (Opsional)</label>
                            <select
                                id="cashShiftId"
                                name="shift_id"
                                data-cash-shift-select
                                class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                            >
                                <option value="">Tanpa shift</option>
                                @foreach($shifts as $shift)
                                    <option value="{{ $shift['id'] }}" data-outlet-id="{{ $shift['outlet_id'] }}" @selected((string) old('shift_id') === (string) $shift['id'])>
                                        {{ $shift['shift_number'] }} - {{ ucfirst((string) $shift['status']) }}
                                    </option>
                                @endforeach
                            </select>
                            @if($cashErrors->has('shift_id'))
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $cashErrors->first('shift_id') }}</p>
                            @endif
                        </div>

                        {{-- Kategori --}}
                        <div>
                            <label for="cashCategory" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Kategori Kas</label>
                            <select
                                id="cashCategory"
                                name="category"
                                required
                                class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                            >
                                <option value="cash_in" @selected(old('category') === 'cash_in')>Setoran Kas</option>
                                <option value="cash_out" @selected(old('category') === 'cash_out')>Penarikan Kas</option>
                                <option value="operational" @selected(old('category') === 'operational')>Operasional</option>
                                <option value="other" @selected(old('category') === 'other')>Lainnya</option>
                            </select>
                            @if($cashErrors->has('category'))
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $cashErrors->first('category') }}</p>
                            @endif
                        </div>

                        {{-- Tanggal Transaksi --}}
                        <div>
                            <label for="cashOccurredAt" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Tanggal & Waktu</label>
                            <input
                                type="datetime-local"
                                id="cashOccurredAt"
                                name="occurred_at"
                                step="60"
                                value="{{ old('occurred_at', $defaultOccurredAt) }}"
                                required
                                class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                            >
                            @if($cashErrors->has('occurred_at'))
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $cashErrors->first('occurred_at') }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Keterangan --}}
                    <div>
                        <label for="cashNote" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Keterangan (Opsional)</label>
                        <textarea
                            id="cashNote"
                            name="note"
                            rows="3"
                            placeholder="Tulis keterangan transaksi kas..."
                            class="w-full p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        >{{ old('note') }}</textarea>
                        @if($cashErrors->has('note'))
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $cashErrors->first('note') }}</p>
                        @endif
                    </div>

                    {{-- Modal Footer --}}
                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            data-cash-modal-close
                            class="w-full sm:w-auto h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="w-full sm:w-auto h-10 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-bold text-white shadow-sm shadow-indigo-600/20 transition-colors cursor-pointer"
                        >
                            Simpan Kas
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    {{-- ==================== MODAL 2: TAMBAH PENGELUARAN ==================== --}}
    <div
        id="expenseModal"
        data-cash-modal
        data-open-on-load="{{ $expenseErrors->any() ? 'true' : 'false' }}"
        class="hidden fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm p-4 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-labelledby="expenseModalTitle"
    >
        <div class="min-h-full flex items-center justify-center">
            <section class="w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <i data-lucide="receipt" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h2 id="expenseModalTitle" class="text-base font-bold text-slate-900 dark:text-white">Tambah Pengeluaran</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pengeluaran operasional terpisah dari kas, kecuali ditandai dibayar dari kas.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        data-cash-modal-close
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                        aria-label="Tutup"
                    >
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                {{-- Modal Form --}}
                <form method="POST" action="{{ route('cash.expenses.store') }}" class="p-6 space-y-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $expenseIdempotencyKey }}">

                    @if($expenseErrors->any())
                        <div class="rounded-xl border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/40 p-3.5 text-xs text-rose-700 dark:text-rose-300">
                            <p class="font-bold mb-1">Periksa kembali formulir pengeluaran:</p>
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach($expenseErrors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Deskripsi --}}
                    <div>
                        <label for="expenseDescription" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Deskripsi Pengeluaran</label>
                        <input
                            type="text"
                            id="expenseDescription"
                            name="description"
                            value="{{ old('description') }}"
                            required
                            maxlength="255"
                            placeholder="Contoh: Beli Bahan Baku, Listrik, Operasional..."
                            class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        >
                        @if($expenseErrors->has('description'))
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $expenseErrors->first('description') }}</p>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Kategori --}}
                        <div>
                            <label for="expenseCategory" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Kategori</label>
                            <div class="relative">
                                <input
                                    type="text"
                                    id="expenseCategory"
                                    name="category"
                                    list="expenseCategorySuggestions"
                                    value="{{ old('category') }}"
                                    required
                                    maxlength="255"
                                    placeholder="Pilih atau ketik kategori..."
                                    class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                                >
                                <datalist id="expenseCategorySuggestions">
                                    <option value="Operasional">
                                    <option value="Bahan Baku">
                                    <option value="Sewa & Utilitas">
                                    <option value="Gaji & Upah">
                                    <option value="Perlengkapan">
                                    <option value="Pemasaran">
                                    <option value="Lainnya">
                                </datalist>
                            </div>
                            @if($expenseErrors->has('category'))
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $expenseErrors->first('category') }}</p>
                            @endif
                        </div>

                        {{-- Nominal --}}
                        <div>
                            <label for="expenseAmount" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Nominal (Rp)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-xs font-bold text-slate-400 dark:text-slate-500">Rp</div>
                                <input
                                    type="number"
                                    id="expenseAmount"
                                    name="amount"
                                    min="1"
                                    step="1"
                                    value="{{ old('amount') }}"
                                    required
                                    placeholder="0"
                                    class="w-full h-10 pl-10 pr-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm font-semibold text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                                >
                            </div>
                            @if($expenseErrors->has('amount'))
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $expenseErrors->first('amount') }}</p>
                            @endif
                        </div>

                        {{-- Tanggal --}}
                        <div>
                            <label for="expenseOccurredAt" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Tanggal & Waktu</label>
                            <input
                                type="datetime-local"
                                id="expenseOccurredAt"
                                name="occurred_at"
                                step="60"
                                value="{{ old('occurred_at', $defaultOccurredAt) }}"
                                required
                                class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                            >
                            @if($expenseErrors->has('occurred_at'))
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $expenseErrors->first('occurred_at') }}</p>
                            @endif
                        </div>

                        {{-- Outlet --}}
                        <div>
                            <label for="expenseOutletId" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Outlet</label>
                            <select
                                id="expenseOutletId"
                                name="outlet_id"
                                data-cash-outlet-select
                                required
                                class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                            >
                                <option value="">Pilih outlet</option>
                                @foreach($outlets as $outlet)
                                    <option value="{{ $outlet['id'] }}" @selected((string) old('outlet_id') === (string) $outlet['id'])>{{ $outlet['name'] }}</option>
                                @endforeach
                            </select>
                            @if($expenseErrors->has('outlet_id'))
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $expenseErrors->first('outlet_id') }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Shift --}}
                    <div>
                        <label for="expenseShiftId" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Shift (Opsional)</label>
                        <select
                            id="expenseShiftId"
                            name="shift_id"
                            data-cash-shift-select
                            class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        >
                            <option value="">Tanpa shift</option>
                            @foreach($shifts as $shift)
                                <option value="{{ $shift['id'] }}" data-outlet-id="{{ $shift['outlet_id'] }}" @selected((string) old('shift_id') === (string) $shift['id'])>
                                    {{ $shift['shift_number'] }} - {{ ucfirst((string) $shift['status']) }}
                                </option>
                            @endforeach
                        </select>
                        @if($expenseErrors->has('shift_id'))
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $expenseErrors->first('shift_id') }}</p>
                        @endif
                    </div>

                    {{-- Dibayar dari Kas --}}
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 p-3.5 cursor-pointer hover:bg-slate-100/70 dark:hover:bg-slate-800/60 transition-colors">
                        <input
                            type="checkbox"
                            name="paid_from_cash"
                            value="1"
                            @checked(old('paid_from_cash'))
                            class="mt-0.5 w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500"
                        >
                        <div>
                            <span class="block text-xs font-bold text-slate-900 dark:text-white">Dibayar dari Kas</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">Buat pergerakan kas keluar yang terhubung langsung dengan pengeluaran ini.</span>
                        </div>
                    </label>

                    {{-- Catatan --}}
                    <div>
                        <label for="expenseNotes" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Catatan Tambahan (Opsional)</label>
                        <textarea
                            id="expenseNotes"
                            name="notes"
                            rows="3"
                            placeholder="Tulis catatan atau rincian pengeluaran..."
                            class="w-full p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        >{{ old('notes') }}</textarea>
                        @if($expenseErrors->has('notes'))
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $expenseErrors->first('notes') }}</p>
                        @endif
                    </div>

                    {{-- Modal Footer --}}
                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            data-cash-modal-close
                            class="w-full sm:w-auto h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="w-full sm:w-auto h-10 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-bold text-white shadow-sm shadow-indigo-600/20 transition-colors cursor-pointer"
                        >
                            Simpan Pengeluaran
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endif
