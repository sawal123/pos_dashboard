@props([
    'receiptLogoUrl' => null,
    'receiptAddress' => '',
    'receiptFooter' => '',
    'outlets' => collect(),
])

<form
    method="POST"
    action="{{ route('business-settings.receipt.update') }}"
    id="receiptSettingsForm"
    enctype="multipart/form-data"
    class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-5"
>
    @csrf

    <div>
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                <i data-lucide="printer" class="w-4 h-4"></i>
            </div>
            <div>
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">Pengaturan Struk &amp; Logo</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Atur logo toko, alamat, dan catatan footer struk. Logo disimpan dinamis di database dan disinkronkan ke POS kasir.
                </p>
            </div>
        </div>
    </div>

    {{-- Logo Struk --}}
    <div class="space-y-3">
        <label class="text-xs font-bold text-slate-700 dark:text-slate-300 block">
            Logo Struk
        </label>

        @if($receiptLogoUrl)
            <div class="flex items-center gap-4 p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40">
                <div class="w-20 h-16 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center justify-center p-2 shrink-0">
                    <img src="{{ $receiptLogoUrl }}" alt="Logo Struk" class="max-h-full max-w-full object-contain">
                </div>
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">Logo aktif terpasang</p>
                    <label class="inline-flex items-center gap-2 text-xs text-rose-600 dark:text-rose-400 cursor-pointer font-medium hover:underline">
                        <input type="checkbox" name="remove_logo" value="1" class="rounded text-rose-600 focus:ring-rose-500">
                        <span>Hapus logo saat disimpan</span>
                    </label>
                </div>
            </div>
        @endif

        <div class="space-y-1">
            <input
                type="file"
                name="receipt_logo"
                id="receiptLogoInput"
                accept="image/png,image/jpeg,image/svg+xml,image/webp"
                class="block w-full text-xs text-slate-500 dark:text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-950/60 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/60 cursor-pointer"
            >
            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                Format: PNG, JPG, SVG, WebP. Maksimal 2 MB. Kosongkan jika tidak ingin mengubah logo.
            </p>
        </div>
    </div>

    {{-- Branch Addresses Overview --}}
    @if(isset($outlets) && $outlets->isNotEmpty())
        <div class="rounded-xl border border-indigo-200/80 dark:border-indigo-900/60 bg-indigo-50/50 dark:bg-indigo-950/20 p-3.5 space-y-2">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 text-indigo-700 dark:text-indigo-300 font-bold text-xs">
                    <i data-lucide="store" class="w-4 h-4"></i>
                    <span>Alamat Struk Otomatis per Cabang:</span>
                </div>
                <a href="{{ route('outlets.index') }}" class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                    Kelola di Outlet &amp; Cabang &rarr;
                </a>
            </div>
            <div class="space-y-1.5">
                @foreach($outlets as $out)
                    <div class="flex items-start justify-between gap-2 p-2 rounded-lg bg-white/80 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 text-xs">
                        <div class="min-w-0">
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $out->name }}</span>
                            @if($out->is_primary)
                                <span class="text-[9px] font-extrabold uppercase px-1 py-0.2 rounded bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 ml-1">Utama</span>
                            @endif
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">
                                📍 {{ $out->address ?: '(Alamat belum diatur)' }}{{ $out->phone ? ' • Telp: ' . $out->phone : '' }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-snug">
                Struk kasir secara otomatis mencetak alamat dan nomor telepon dari cabang tempat transaksi dibuat. Field di bawah hanya digunakan sebagai fallback/catatan umum.
            </p>
        </div>
    @endif

    {{-- Alamat Struk --}}
    <div class="space-y-1.5">
        <label for="receiptAddressInput" class="text-xs font-bold text-slate-700 dark:text-slate-300">
            Alamat Tambahan pada Struk (Opsional)
        </label>
        <textarea
            name="receipt_address"
            id="receiptAddressInput"
            rows="2"
            maxlength="300"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder:text-slate-400"
            placeholder="Jalan, Nomor, Kota (jika ingin berbeda dari data outlet)"
        >{{ old('receipt_address', $receiptAddress) }}</textarea>
    </div>

    {{-- Footer Struk --}}
    <div class="space-y-1.5">
        <label for="receiptFooterInput" class="text-xs font-bold text-slate-700 dark:text-slate-300">
            Pesan Penutup / Footer Struk (Opsional)
        </label>
        <textarea
            name="receipt_footer"
            id="receiptFooterInput"
            rows="2"
            maxlength="500"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder:text-slate-400"
            placeholder="Terima kasih atas kunjungan Anda! Simpan struk ini sebagai bukti pembayaran."
        >{{ old('receipt_footer', $receiptFooter) }}</textarea>
    </div>

    <div class="flex items-center justify-end pt-2">
        <button
            type="submit"
            id="saveReceiptSettingsBtn"
            class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold text-xs shadow-md shadow-indigo-500/25 transition-all flex items-center gap-1.5"
        >
            <i data-lucide="save" class="w-4 h-4"></i>
            <span>Simpan Pengaturan Struk</span>
        </button>
    </div>
</form>

