{{-- ==================== PRODUCT QR CODE PREVIEW MODAL ==================== --}}
<div
    id="productQrModal"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="qrModalTitle"
>
    {{-- Backdrop --}}
    <div
        id="productQrModalBackdrop"
        class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs transition-opacity duration-300"
        onclick="closeQrModal()"
    ></div>

    {{-- Modal Dialog --}}
    <div class="fixed inset-0 z-10 overflow-y-auto flex items-center justify-center p-4 sm:p-6">
        <div
            class="relative w-full max-w-sm bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden p-6 text-center transform transition-all animate-in fade-in zoom-in-95 duration-200"
        >
            {{-- Close Button --}}
            <button
                type="button"
                onclick="closeQrModal()"
                class="absolute right-4 top-4 p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                aria-label="Tutup"
            >
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>

            {{-- Header --}}
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto mb-3 shadow-inner">
                <i data-lucide="qr-code" class="w-6 h-6"></i>
            </div>
            <h2 id="qrModalTitle" class="text-base font-bold text-slate-900 dark:text-white">
                QR Code Produk
            </h2>
            <h3 id="qrModalProductName" class="text-sm font-semibold text-slate-700 dark:text-slate-300 mt-1 truncate px-2">
                -
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                <span id="qrModalProductSku">-</span> · <span id="qrModalProductBarcode" class="font-bold text-indigo-600 dark:text-indigo-400">-</span>
            </p>

            {{-- QR SVG Container with White Margin for Clean Scanning --}}
            <div class="my-5 p-5 bg-white rounded-2xl border border-slate-200 shadow-xs max-w-[240px] mx-auto flex items-center justify-center">
                <div id="qrModalSvgContainer" class="w-full flex items-center justify-center [&>svg]:w-full [&>svg]:h-auto [&>svg]:max-w-[200px]">
                    <div class="py-12 text-slate-400 text-xs">Memuat QR...</div>
                </div>
            </div>

            <p class="text-[11px] text-slate-400 dark:text-slate-500 mb-5 leading-relaxed">
                Label QR dapat dipindai oleh kamera POS Web atau aplikasi POS Mobile untuk transaksi kasir.
            </p>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    onclick="closeQrModal()"
                    class="flex-1 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors"
                >
                    Tutup
                </button>
                <a
                    id="qrModalDownloadBtn"
                    href="#"
                    class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs transition-colors flex items-center justify-center gap-1.5 shadow-sm shadow-indigo-500/20"
                >
                    <i data-lucide="download" class="w-3.5 h-3.5"></i>
                    <span>Unduh PNG</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function openQrModal(productId) {
        const modal = document.getElementById('productQrModal');
        if (!modal) return;

        const nameEl = document.getElementById('qrModalProductName');
        const skuEl = document.getElementById('qrModalProductSku');
        const barcodeEl = document.getElementById('qrModalProductBarcode');
        const svgContainer = document.getElementById('qrModalSvgContainer');
        const downloadBtn = document.getElementById('qrModalDownloadBtn');

        nameEl.textContent = 'Memuat...';
        skuEl.textContent = '-';
        barcodeEl.textContent = '-';
        svgContainer.innerHTML = '<div class="py-10 text-slate-400 text-xs flex items-center gap-2 justify-center"><i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i><span>Memuat QR...</span></div>';
        if (typeof lucide !== 'undefined') lucide.createIcons();

        modal.classList.remove('hidden');

        fetch(`/products/${productId}/qr-preview`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error('Gagal memuat pratinjau QR');
            return res.json();
        })
        .then(data => {
            nameEl.textContent = data.name || '-';
            skuEl.textContent = data.sku ? `SKU: ${data.sku}` : '';
            barcodeEl.textContent = data.barcode || '';
            svgContainer.innerHTML = data.svg || '<span class="text-xs text-rose-500">QR tidak tersedia</span>';
            downloadBtn.href = data.download_url || `/products/${productId}/qr-download`;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })
        .catch(err => {
            svgContainer.innerHTML = `<span class="text-xs text-rose-500">${err.message || 'Terjadi kesalahan'}</span>`;
        });
    }

    function closeQrModal() {
        const modal = document.getElementById('productQrModal');
        if (modal) modal.classList.add('hidden');
    }

    function generateQrCode(productId) {
        if (!confirm('Buat kode barcode dan QR untuk produk ini sekarang?')) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        fetch(`/products/${productId}/generate-qr`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => {
            if (!res.ok) throw new Error('Gagal membuat kode QR');
            return res.json();
        })
        .then(data => {
            if (typeof showToast === 'function') {
                showToast('success', data.message || 'Kode QR berhasil dibuat!');
            }
            openQrModal(productId);
            // Optional: reload after short delay or update table row
            setTimeout(() => {
                window.location.reload();
            }, 1200);
        })
        .catch(err => {
            if (typeof showToast === 'function') {
                showToast('error', err.message || 'Gagal membuat kode QR');
            } else {
                alert(err.message || 'Gagal membuat kode QR');
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeQrModal();
        }
    });
</script>

