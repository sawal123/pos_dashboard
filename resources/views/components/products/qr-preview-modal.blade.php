{{-- QR Code Preview, Download & Print Label Modal for Grosir/Retail Products --}}
@once
<div id="productQrModal" class="fixed inset-0 z-[140] hidden" role="dialog" aria-modal="true" aria-labelledby="qrModalTitle">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-slate-950/75 backdrop-blur-sm transition-opacity" onclick="window.closeQrModal()"></div>

    {{-- Modal Content --}}
    <div class="fixed inset-0 z-10 overflow-y-auto flex items-center justify-center p-4">
        <div class="relative w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden transform transition-all">
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="qr-code" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 id="qrModalTitle" class="text-sm font-bold text-slate-900 dark:text-white">Label QR & Kode Produk</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Unduh atau cetak stiker QR untuk ditempel pada produk</p>
                    </div>
                </div>
                <button type="button" onclick="window.closeQrModal()" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" aria-label="Tutup">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-5 flex flex-col items-center text-center">
                {{-- Sticker Preview Card --}}
                <div class="w-full rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/40 p-4 flex flex-col items-center">
                    <div class="mb-2.5">
                        <h4 id="qrModalProductName" class="text-base font-bold text-slate-900 dark:text-white leading-snug">Nama Produk</h4>
                        <div class="flex items-center justify-center gap-2 mt-1">
                            <span id="qrModalProductSku" class="text-[11px] font-mono text-slate-500 dark:text-slate-400">SKU: -</span>
                            <span class="text-slate-300 dark:text-slate-600">•</span>
                            <span id="qrModalProductPrice" class="text-xs font-extrabold text-indigo-600 dark:text-indigo-400">Rp 0</span>
                        </div>
                    </div>

                    {{-- QR Container --}}
                    <div class="p-3.5 bg-white rounded-2xl border border-slate-200 shadow-sm flex items-center justify-center w-52 h-52 relative">
                        <div id="qrModalLoader" class="flex flex-col items-center justify-center text-slate-400">
                            <i data-lucide="loader-2" class="w-7 h-7 animate-spin text-indigo-600 mb-2"></i>
                            <span class="text-xs font-medium">Membuat QR...</span>
                        </div>
                        <img id="qrModalImage" src="" alt="QR Code Produk" class="w-full h-full object-contain hidden" crossorigin="anonymous" />
                    </div>

                    {{-- Code Display --}}
                    <div class="mt-3 w-full">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Kode Barcode / QR</span>
                        <div class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-mono text-xs font-bold text-slate-800 dark:text-slate-200 select-all break-all" id="qrModalCodeText">
                            -
                        </div>
                    </div>
                </div>

                {{-- Print Copies Control --}}
                <div class="mt-4 w-full flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800 text-left">
                    <div>
                        <label for="qrModalPrintCopies" class="text-xs font-bold text-slate-700 dark:text-slate-200 block">Jumlah Stiker Cetak</label>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400">Pilih jumlah label untuk dicetak & ditempel</span>
                    </div>
                    <select id="qrModalPrintCopies" class="h-9 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 text-xs font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="1">1 Label</option>
                        <option value="4">4 Label</option>
                        <option value="8">8 Label</option>
                        <option value="12" selected>12 Label (1 Baris/Lembar)</option>
                        <option value="24">24 Label</option>
                        <option value="40">40 Label</option>
                    </select>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-900/90 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2">
                <button type="button" onclick="window.closeQrModal()" class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-slate-200/70 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300/70 dark:hover:bg-slate-700 transition-colors cursor-pointer">
                    Tutup
                </button>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" id="qrModalDownloadBtn" onclick="window.downloadQrLabelPng()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors shadow-xs cursor-pointer">
                        <i data-lucide="download" class="w-3.5 h-3.5"></i>
                        <span>Unduh PNG</span>
                    </button>
                    <button type="button" id="qrModalPrintBtn" onclick="window.printQrLabel()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition-colors shadow-sm shadow-indigo-500/20 cursor-pointer">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                        <span>Cetak Label QR</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script data-navigate-once>
    (function () {
        let currentQrProductData = null;

        function formatRupiahQr(amount) {
            const num = Number(amount) || 0;
            return 'Rp ' + num.toLocaleString('id-ID');
        }

        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }

        /**
         * Generate a crisp QR code Data URL from server SVG (BaconQrCode) or fallback API
         */
        async function buildQrCodeDataUrl(codeText, svgMarkup = null, size = 360) {
            if (svgMarkup && typeof svgMarkup === 'string' && svgMarkup.includes('<svg')) {
                return 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgMarkup)));
            }
            const qrApiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&margin=10&data=${encodeURIComponent(codeText)}`;
            try {
                const response = await fetch(qrApiUrl, { mode: 'cors' });
                if (response.ok) {
                    const blob = await response.blob();
                    return await new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(String(reader.result || qrApiUrl));
                        reader.onerror = () => reject(new Error('Failed to read QR blob'));
                        reader.readAsDataURL(blob);
                    });
                }
            } catch (e) {
                // Fallback directly to image URL if fetch CORS fails
            }
            return qrApiUrl;
        }

        function wrapCanvasText(ctx, text, maxWidth) {
            const words = String(text || '').split(/\s+/);
            const lines = [];
            let currentLine = '';

            for (const word of words) {
                const testLine = currentLine ? `${currentLine} ${word}` : word;
                if (ctx.measureText(testLine).width > maxWidth && currentLine) {
                    lines.push(currentLine);
                    currentLine = word;
                } else {
                    currentLine = testLine;
                }
            }
            if (currentLine) lines.push(currentLine);
            return lines.slice(0, 2);
        }

        /**
         * Build a complete ready-to-stick Product Label PNG Data URL
         */
        async function renderProductStickerDataUrl(product) {
            const qrSrc = product.qrDataUrl || document.getElementById('qrModalImage')?.src || '';
            const qrImg = await new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = qrSrc;
            });

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = 640;
            canvas.height = 780;

            // White sticker background + subtle border
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.strokeStyle = '#e2e8f0';
            ctx.lineWidth = 4;
            ctx.strokeRect(12, 12, canvas.width - 24, canvas.height - 24);

            // Product Name
            ctx.fillStyle = '#0f172a';
            ctx.textAlign = 'center';
            ctx.font = 'bold 28px Inter, system-ui, sans-serif';
            const nameLines = wrapCanvasText(ctx, product.name || 'Produk', 560);
            nameLines.forEach((line, idx) => {
                ctx.fillText(line, 320, 62 + idx * 34);
            });

            // Price & SKU
            const metaY = 62 + nameLines.length * 34 + 6;
            ctx.fillStyle = '#4f46e5';
            ctx.font = 'bold 24px Inter, system-ui, sans-serif';
            const priceStr = product.price ? formatRupiahQr(product.price) : '';
            const skuStr = product.sku ? `SKU: ${product.sku}` : '';
            ctx.fillText([skuStr, priceStr].filter(Boolean).join('  •  '), 320, metaY);

            // QR Image
            const qrSize = 460;
            const qrX = (canvas.width - qrSize) / 2;
            const qrY = metaY + 20;
            ctx.drawImage(qrImg, qrX, qrY, qrSize, qrSize);

            // Barcode Code Text at Bottom
            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 22px monospace';
            ctx.fillText(String(product.barcode || ''), 320, qrY + qrSize + 40);

            return canvas.toDataURL('image/png');
        }

        function updateProductDomBarcode(productId, barcode, productData = {}) {
            // Update desktop table & mobile card cells that hold the QR button for this product
            const generateBtns = document.querySelectorAll(`[data-qr-generate-btn="${productId}"], button[onclick*="generateQrCode(${productId})"]`);
            generateBtns.forEach((btn) => {
                const container = btn.parentElement;
                if (!container) return;

                container.innerHTML = `
                    <div class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 text-[10px] font-mono text-indigo-700 dark:text-indigo-300 w-fit" title="Barcode / Kode Scan">
                        <i data-lucide="scan-barcode" class="w-3 h-3 shrink-0"></i>
                        <span class="truncate max-w-[120px]">${barcode}</span>
                    </div>
                    <button
                        type="button"
                        onclick="window.openQrModal(${productId})"
                        class="inline-flex items-center gap-1 px-2 py-0.5 mt-0.5 rounded-md bg-slate-100 hover:bg-indigo-50 dark:bg-slate-800 dark:hover:bg-indigo-950/70 text-[10px] font-semibold text-slate-700 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400 border border-slate-200/80 dark:border-slate-700 transition-colors w-fit cursor-pointer"
                        title="Lihat & Cetak QR Code Produk"
                    >
                        <i data-lucide="qr-code" class="w-3 h-3"></i>
                        <span>Lihat QR</span>
                    </button>
                `;
            });

            // Also update any data-catalog-raw attributes for this product so Edit / Detail Drawer has the new barcode
            document.querySelectorAll('[data-catalog-raw]').forEach((el) => {
                try {
                    const raw = JSON.parse(el.getAttribute('data-catalog-raw') || '{}');
                    if (Number(raw.id) === Number(productId)) {
                        raw.barcode = barcode;
                        el.setAttribute('data-catalog-raw', JSON.stringify(raw));
                    }
                } catch (e) {}
            });

            document.querySelectorAll('[data-detail-product]').forEach((el) => {
                try {
                    const raw = JSON.parse(el.getAttribute('data-detail-product') || '{}');
                    if (Number(raw.id) === Number(productId)) {
                        raw.barcode = barcode;
                        el.setAttribute('data-detail-product', JSON.stringify(raw));
                    }
                } catch (e) {}
            });

            if (window.lucide) window.lucide.createIcons();
        }

        window.openQrModal = async function openQrModal(productId, preloadedData = null) {
            const modal = document.getElementById('productQrModal');
            const nameEl = document.getElementById('qrModalProductName');
            const skuEl = document.getElementById('qrModalProductSku');
            const priceEl = document.getElementById('qrModalProductPrice');
            const codeEl = document.getElementById('qrModalCodeText');
            const imgEl = document.getElementById('qrModalImage');
            const loaderEl = document.getElementById('qrModalLoader');

            if (!modal) return;

            modal.classList.remove('hidden');
            imgEl.classList.add('hidden');
            loaderEl.classList.remove('hidden');
            nameEl.textContent = 'Memuat...';
            skuEl.textContent = 'SKU: -';
            if (priceEl) priceEl.textContent = '';
            codeEl.textContent = '-';

            if (window.lucide) window.lucide.createIcons();

            try {
                let data = preloadedData;
                if (!data || !data.barcode) {
                    const response = await fetch(`/products/${productId}/qr-preview`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });
                    data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Gagal memuat data QR produk.');
                    }
                }

                const productName = data.name || data.product_name || 'Produk';
                const productSku = data.sku || '-';
                const productPrice = Number(data.price) || 0;
                const barcode = String(data.barcode || '').trim();

                nameEl.textContent = productName;
                skuEl.textContent = `SKU: ${productSku}`;
                if (priceEl) {
                    priceEl.textContent = productPrice > 0 ? formatRupiahQr(productPrice) : 'Label Fisik';
                }
                codeEl.textContent = barcode;

                const qrDataUrl = await buildQrCodeDataUrl(barcode, data.svg || null, 400);
                currentQrProductData = {
                    id: productId,
                    name: productName,
                    sku: productSku,
                    price: productPrice,
                    barcode: barcode,
                    qrDataUrl: qrDataUrl,
                    downloadUrl: data.download_url || `/products/${productId}/qr-download`,
                };

                imgEl.onload = () => {
                    loaderEl.classList.add('hidden');
                    imgEl.classList.remove('hidden');
                };
                imgEl.src = qrDataUrl;
            } catch (err) {
                window.closeQrModal();
                if (window.showToast) {
                    window.showToast('Gagal memuat QR Code', err.message || 'Terjadi kesalahan', 'error');
                } else {
                    alert(err.message || 'Gagal memuat QR Code');
                }
            }
        };

        window.closeQrModal = function closeQrModal() {
            const modal = document.getElementById('productQrModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        };

        window.downloadQrLabelPng = async function downloadQrLabelPng() {
            if (!currentQrProductData || !currentQrProductData.barcode) return;
            try {
                const labelDataUrl = await renderProductStickerDataUrl(currentQrProductData);
                const slug = String(currentQrProductData.name || 'produk').replace(/[^a-z0-9]/gi, '_').toLowerCase();
                const link = document.createElement('a');
                link.href = labelDataUrl;
                link.download = `Label_QR_${slug}_${currentQrProductData.barcode}.png`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                if (window.showToast) {
                    window.showToast('Berhasil', 'Label QR PNG berhasil diunduh.', 'success');
                }
            } catch (err) {
                // Fallback direct QR image download
                const imgEl = document.getElementById('qrModalImage');
                if (imgEl && imgEl.src) {
                    const link = document.createElement('a');
                    link.href = imgEl.src;
                    link.download = `QR_${currentQrProductData.barcode}.png`;
                    link.click();
                }
            }
        };

        window.printQrLabel = function printQrLabel() {
            if (!currentQrProductData || !currentQrProductData.barcode) return;

            const copiesSelect = document.getElementById('qrModalPrintCopies');
            const copies = Math.max(1, Math.min(100, parseInt(copiesSelect?.value || '12', 10) || 12));
            const qrSrc = currentQrProductData.qrDataUrl || document.getElementById('qrModalImage')?.src || '';
            const safeName = String(currentQrProductData.name || 'Produk')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            const safeSku = String(currentQrProductData.sku || '-')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            const safeBarcode = String(currentQrProductData.barcode || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            const priceHtml = currentQrProductData.price > 0
                ? `<div class="label-price">${formatRupiahQr(currentQrProductData.price)}</div>`
                : '';

            let cardsHtml = '';
            for (let i = 0; i < copies; i++) {
                cardsHtml += `
                    <div class="label-card">
                        <div class="label-name">${safeName}</div>
                        ${priceHtml}
                        <img class="label-qr" src="${qrSrc}" alt="QR ${safeBarcode}" />
                        <div class="label-code">${safeBarcode}</div>
                        <div class="label-sku">SKU: ${safeSku}</div>
                    </div>
                `;
            }

            const printWin = window.open('', '_blank', 'width=900,height=700');
            if (!printWin) {
                if (window.showToast) {
                    window.showToast('Pop-up Diblokir', 'Izinkan pop-up pada browser untuk mencetak label QR.', 'warning');
                } else {
                    alert('Izinkan pop-up pada browser untuk mencetak label QR.');
                }
                return;
            }

            printWin.document.open();
            printWin.document.write(`<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <title>Cetak Label QR - ${safeName}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            padding: 16px;
        }
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .toolbar-title { font-size: 14px; font-weight: 700; }
        .toolbar-sub { font-size: 12px; color: #64748b; margin-top: 2px; }
        .btn-print {
            background: #4f46e5;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
            gap: 10px;
        }
        .label-card {
            background: #ffffff;
            border: 1.5px dashed #94a3b8;
            border-radius: 10px;
            padding: 10px 8px;
            text-align: center;
            page-break-inside: avoid;
            break-inside: avoid;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }
        .label-name {
            font-size: 11px;
            font-weight: 800;
            line-height: 1.25;
            max-height: 2.5em;
            overflow: hidden;
            margin-bottom: 2px;
            color: #0f172a;
        }
        .label-price {
            font-size: 11px;
            font-weight: 800;
            color: #4f46e5;
            margin-bottom: 4px;
        }
        .label-qr {
            width: 112px;
            height: 112px;
            object-fit: contain;
            margin: 2px auto;
        }
        .label-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 9.5px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 4px;
            word-break: break-all;
        }
        .label-sku {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 1px;
        }
        @media print {
            body { background: #ffffff; padding: 0; }
            .toolbar { display: none !important; }
            .grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
            }
            .label-card {
                border: 1px dashed #cbd5e1;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <div class="toolbar-title">Cetak Stiker QR Produk: ${safeName} (${copies} Label)</div>
            <div class="toolbar-sub">Siap dicetak pada kertas stiker / label atau printer thermal lalu ditempel pada produk.</div>
        </div>
        <button class="btn-print" onclick="window.print()">Cetak Sekarang</button>
    </div>
    <div class="grid">${cardsHtml}</div>
    <script>
        window.onload = function() {
            setTimeout(function() { window.print(); }, 350);
        };
    <\/script>
</body>
</html>`);
            printWin.document.close();
        };

        window.generateQrCode = async function generateQrCode(productId, triggerBtn = null) {
            const csrfToken = getCsrfToken();
            const btn = triggerBtn || document.querySelector(`[data-qr-generate-btn="${productId}"], button[onclick*="generateQrCode(${productId})"]`);
            const originalHtml = btn ? btn.innerHTML : '';

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = `<i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i><span>Membuat...</span>`;
                if (window.lucide) window.lucide.createIcons();
            }

            try {
                const response = await fetch(`/products/${productId}/generate-qr`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Gagal membuat kode produk.');
                }

                updateProductDomBarcode(productId, data.barcode, data);

                if (window.showToast) {
                    window.showToast('Berhasil', data.message || 'Kode & QR berhasil dibuat.', 'success');
                }

                await window.openQrModal(productId, data);
            } catch (err) {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    if (window.lucide) window.lucide.createIcons();
                }
                if (window.showToast) {
                    window.showToast('Gagal Membuat Kode', err.message || 'Terjadi kesalahan', 'error');
                } else {
                    alert(err.message || 'Gagal membuat kode produk.');
                }
            }
        };
    })();
</script>
@endonce
