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
    <div
        id="cashLedgerModal"
        data-cash-modal
        data-open-on-load="{{ $cashErrors->any() ? 'true' : 'false' }}"
        class="hidden fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-sm p-4 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cashLedgerModalTitle"
    >
        <div class="min-h-full flex items-center justify-center">
            <section class="w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl">
                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <h2 id="cashLedgerModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">Catat Kas</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Kas masuk dan kas keluar manual tercatat sebagai pergerakan kas fisik.</p>
                    </div>
                    <button type="button" data-cash-modal-close class="p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 dark:hover:text-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('cash.ledger.store') }}" class="px-5 py-5 space-y-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $cashIdempotencyKey }}">

                    @if($cashErrors->any())
                        <div class="rounded-xl border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-xs text-rose-700 dark:text-rose-300">
                            <p class="font-bold mb-1">Periksa kembali catatan kas.</p>
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach($cashErrors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="space-y-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Jenis Kas</span>
                            <select name="type" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="in" @selected(old('type') === 'in')>Kas Masuk</option>
                                <option value="out" @selected(old('type') === 'out')>Kas Keluar</option>
                            </select>
                        </label>

                        <label class="space-y-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Nominal</span>
                            <input name="amount" type="number" min="1" step="1" value="{{ old('amount') }}" required class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </label>

                        <label class="space-y-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Outlet</span>
                            <select name="outlet_id" data-cash-outlet-select required class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Pilih outlet</option>
                                @foreach($outlets as $outlet)
                                    <option value="{{ $outlet['id'] }}" @selected((string) old('outlet_id') === (string) $outlet['id'])>{{ $outlet['name'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="space-y-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Shift</span>
                            <select name="shift_id" data-cash-shift-select class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Tanpa shift</option>
                                @foreach($shifts as $shift)
                                    <option value="{{ $shift['id'] }}" data-outlet-id="{{ $shift['outlet_id'] }}" @selected((string) old('shift_id') === (string) $shift['id'])>
                                        {{ $shift['shift_number'] }} - {{ ucfirst((string) $shift['status']) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="space-y-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Kategori</span>
                            <select name="category" required class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="cash_in" @selected(old('category') === 'cash_in')>Setoran Kas</option>
                                <option value="cash_out" @selected(old('category') === 'cash_out')>Penarikan Kas</option>
                                <option value="operational" @selected(old('category') === 'operational')>Operasional</option>
                                <option value="other" @selected(old('category') === 'other')>Lainnya</option>
                            </select>
                        </label>

                        <label class="space-y-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Tanggal Transaksi</span>
                            <input name="occurred_at" type="datetime-local" step="60" value="{{ old('occurred_at', $defaultOccurredAt) }}" required class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </label>
                    </div>

                    <label class="space-y-1.5 block">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Keterangan</span>
                        <textarea name="note" rows="3" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('note') }}</textarea>
                    </label>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
                        <button type="button" data-cash-modal-close class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">Batal</button>
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-bold text-white shadow-sm shadow-indigo-600/20">Simpan Kas</button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <div
        id="expenseModal"
        data-cash-modal
        data-open-on-load="{{ $expenseErrors->any() ? 'true' : 'false' }}"
        class="hidden fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-sm p-4 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-labelledby="expenseModalTitle"
    >
        <div class="min-h-full flex items-center justify-center">
            <section class="w-full max-w-2xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl">
                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <h2 id="expenseModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">Tambah Pengeluaran</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Pengeluaran operasional terpisah dari kas, kecuali ditandai dibayar dari kas.</p>
                    </div>
                    <button type="button" data-cash-modal-close class="p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 dark:hover:text-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('cash.expenses.store') }}" class="px-5 py-5 space-y-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $expenseIdempotencyKey }}">

                    @if($expenseErrors->any())
                        <div class="rounded-xl border border-rose-200 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-xs text-rose-700 dark:text-rose-300">
                            <p class="font-bold mb-1">Periksa kembali pengeluaran.</p>
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach($expenseErrors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="space-y-1.5 sm:col-span-2">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Deskripsi</span>
                            <input name="description" type="text" value="{{ old('description') }}" required maxlength="255" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </label>

                        <label class="space-y-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Kategori</span>
                            <input name="category" type="text" value="{{ old('category') }}" required maxlength="255" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </label>

                        <label class="space-y-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Nominal</span>
                            <input name="amount" type="number" min="1" step="1" value="{{ old('amount') }}" required class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </label>

                        <label class="space-y-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Tanggal</span>
                            <input name="occurred_at" type="datetime-local" step="60" value="{{ old('occurred_at', $defaultOccurredAt) }}" required class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </label>

                        <label class="space-y-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Outlet</span>
                            <select name="outlet_id" data-cash-outlet-select required class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Pilih outlet</option>
                                @foreach($outlets as $outlet)
                                    <option value="{{ $outlet['id'] }}" @selected((string) old('outlet_id') === (string) $outlet['id'])>{{ $outlet['name'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="space-y-1.5">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Shift</span>
                            <select name="shift_id" data-cash-shift-select class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Tanpa shift</option>
                                @foreach($shifts as $shift)
                                    <option value="{{ $shift['id'] }}" data-outlet-id="{{ $shift['outlet_id'] }}" @selected((string) old('shift_id') === (string) $shift['id'])>
                                        {{ $shift['shift_number'] }} - {{ ucfirst((string) $shift['status']) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="sm:col-span-2 flex items-start gap-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950/60 px-3 py-3">
                            <input type="checkbox" name="paid_from_cash" value="1" @checked(old('paid_from_cash')) class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">Dibayar dari Kas</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">Buat pergerakan kas keluar yang terhubung dengan pengeluaran ini.</span>
                            </span>
                        </label>
                    </div>

                    <label class="space-y-1.5 block">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Catatan</span>
                        <textarea name="notes" rows="3" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                    </label>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
                        <button type="button" data-cash-modal-close class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">Batal</button>
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-bold text-white shadow-sm shadow-indigo-600/20">Simpan Pengeluaran</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endif
