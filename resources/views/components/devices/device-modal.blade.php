{{-- DASH-17 — owner-only device registration / metadata edit modal. --}}
@props([
    'outlets' => [],
])

@php
    // Restore state after a validation redirect: only a *modal* submission
    // carries `device_form`, so a failed status toggle never reopens the modal.
    $oldForm = old('device_form');
    $oldDeviceId = old('device_id');
    $isEditRestore = $oldForm === 'edit' && $oldDeviceId !== null && $oldDeviceId !== '';
    $restoreMode = $oldForm === 'edit' ? 'edit' : ($oldForm === 'create' ? 'create' : '');
    $formAction = $isEditRestore
        ? route('devices.update', ['deviceId' => $oldDeviceId])
        : route('devices.store');
@endphp

<div
    id="deviceModal"
    class="fixed inset-0 z-[90] hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="deviceModalTitle"
    data-restore-mode="{{ $restoreMode }}"
    data-restore-id="{{ $oldDeviceId }}"
    tabindex="-1"
>
    <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs" data-close-device-modal></div>

    <div class="fixed inset-0 flex items-start sm:items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="w-full max-w-lg my-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 sticky top-0 bg-white dark:bg-slate-900 z-10 rounded-t-2xl">
                <div>
                    <span id="deviceModalSubtitle" class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $isEditRestore ? 'Edit' : 'Registrasi' }}</span>
                    <h2 id="deviceModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">{{ $isEditRestore ? 'Edit Perangkat' : 'Daftarkan Perangkat' }}</h2>
                </div>
                <button
                    type="button"
                    data-close-device-modal
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tutup formulir perangkat"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form
                id="deviceForm"
                method="POST"
                action="{{ $formAction }}"
                data-store-url="{{ route('devices.store') }}"
                data-update-url-template="{{ route('devices.update', ['deviceId' => '__ID__']) }}"
                class="p-5 space-y-4"
                novalidate
            >
                @csrf
                <input type="hidden" name="_method" value="{{ $isEditRestore ? 'PATCH' : 'POST' }}" id="deviceFormMethod">
                <input type="hidden" name="device_form" value="{{ $restoreMode === 'edit' ? 'edit' : 'create' }}" id="deviceFormContext">
                <input type="hidden" name="device_id" value="{{ $oldDeviceId }}" id="deviceFormDeviceId">

                <div>
                    <label for="deviceFormName" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Nama Perangkat</label>
                    <input
                        id="deviceFormName"
                        name="name"
                        type="text"
                        required
                        maxlength="255"
                        value="{{ old('name') }}"
                        placeholder="mis. Kasir Depan"
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                    @error('name')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="deviceFormIdentifier" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                        Device Identifier
                    </label>
                    <input
                        id="deviceFormIdentifier"
                        name="identifier"
                        type="text"
                        required
                        maxlength="100"
                        value="{{ old('identifier') }}"
                        placeholder="mis. POS-MEDAN-01"
                        autocomplete="off"
                        @if($isEditRestore) readonly @endif
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 font-mono text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Maksimal 100 karakter. Identifier tidak dapat diubah setelah terdaftar.</p>
                    @error('identifier')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="deviceFormOutlet" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Outlet</label>
                    <select
                        id="deviceFormOutlet"
                        name="outlet_id"
                        required
                        @if($isEditRestore) disabled @endif
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <option value="">Pilih outlet</option>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet['id'] }}" @selected((string) old('outlet_id') === (string) $outlet['id'])>{{ $outlet['name'] }}</option>
                        @endforeach
                    </select>
                    {{-- Mirror keeps the immutable outlet id in the payload while the
                         visible select is disabled in edit mode. --}}
                    <input type="hidden" name="outlet_id" id="deviceFormOutletMirror" value="{{ old('outlet_id') }}" @unless($isEditRestore) disabled @endunless>
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Outlet tidak dapat dipindahkan dari dashboard.</p>
                    @error('outlet_id')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="deviceFormPlatform" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                        Platform <span class="font-normal text-slate-400">(opsional)</span>
                    </label>
                    <input
                        id="deviceFormPlatform"
                        name="platform"
                        type="text"
                        maxlength="50"
                        value="{{ old('platform') }}"
                        placeholder="mis. android, iOS"
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                    @error('platform')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="deviceFormNotes" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">
                        Catatan <span class="font-normal text-slate-400">(opsional)</span>
                    </label>
                    <textarea
                        id="deviceFormNotes"
                        name="notes"
                        rows="3"
                        maxlength="1000"
                        placeholder="mis. Tablet Samsung A9"
                        class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                    >{{ old('notes') }}</textarea>
                    @error('notes')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 p-3 space-y-1.5">
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        Ini adalah <span class="font-semibold">pra-registrasi</span> berdasarkan identifier,
                        bukan pairing aman dan bukan bukti perangkat sedang online.
                    </p>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        Status <span class="font-semibold">Aktif</span> berarti perangkat
                        <span class="font-semibold">diizinkan mengakses API</span> — bukan berarti perangkat
                        sedang online. Kolom “Akses API Terakhir” hanya terisi saat perangkat benar-benar
                        memanggil API.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row sm:justify-end gap-2 pt-1">
                    <button
                        type="button"
                        data-close-device-modal
                        class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-60 disabled:pointer-events-none"
                    >
                        <i data-lucide="save" class="w-4 h-4"></i>
                        <span data-submit-label>Simpan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
