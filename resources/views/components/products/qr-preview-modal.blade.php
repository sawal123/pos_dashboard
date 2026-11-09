{{-- QR Code & 1D Barcode Preview, Download & Print Label Modal for Products --}}
<div id="productQrModal" class="fixed inset-0 z-[140] hidden" role="dialog" aria-modal="true" aria-labelledby="qrModalTitle">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-slate-950/75 backdrop-blur-sm transition-opacity" onclick="window.closeQrModal && window.closeQrModal()"></div>

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
                        <h3 id="qrModalTitle" class="text-sm font-bold text-slate-900 dark:text-white">Label Barcode & QR Produk</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Unduh atau cetak stiker kode untuk ditempel pada produk</p>
                    </div>
                </div>
                <button type="button" onclick="window.closeQrModal && window.closeQrModal()" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer" aria-label="Tutup">
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
                    <div id="qrModalQrBox" class="p-3 bg-white rounded-2xl border border-slate-200 shadow-sm flex items-center justify-center w-44 h-44 relative">
                        <div id="qrModalLoader" class="flex flex-col items-center justify-center text-slate-400">
                            <i data-lucide="loader-2" class="w-7 h-7 animate-spin text-indigo-600 mb-2"></i>
                            <span class="text-xs font-medium">Membuat QR...</span>
                        </div>
                        <img id="qrModalImage" src="" alt="QR Code Produk" class="w-full h-full object-contain hidden" />
                    </div>

                    {{-- 1D Barcode Graphic (Code-128) --}}
                    <div id="qrModalBarcodeBox" class="mt-2.5 w-full bg-white rounded-xl border border-slate-200 px-3 py-2 flex flex-col items-center shadow-2xs">
                        <canvas id="qrModalBarcodeCanvas" class="max-w-full h-14 object-contain"></canvas>
                    </div>

                    {{-- Code Display --}}
                    <div class="mt-2.5 w-full">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block mb-1">Kode Barcode / QR</span>
                        <div class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-mono text-xs font-bold text-slate-800 dark:text-slate-200 select-all break-all" id="qrModalCodeText">
                            -
                        </div>
                    </div>
                </div>

                {{-- Print Options Control --}}
                <div class="mt-3.5 w-full grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-left">
                    <div class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800">
                        <label for="qrModalStickerType" class="text-[11px] font-bold text-slate-700 dark:text-slate-200 block mb-1">Tampilan Label</label>
                        <select id="qrModalStickerType" onchange="window.updateQrStickerMode && window.updateQrStickerMode(this.value)" class="w-full h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 text-xs font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="both" selected>QR + Barcode Garis</option>
                            <option value="qr">QR Code Saja</option>
                            <option value="barcode">Barcode Garis Saja</option>
                        </select>
                    </div>
                    <div class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800">
                        <label for="qrModalPrintCopies" class="text-[11px] font-bold text-slate-700 dark:text-slate-200 block mb-1">Jumlah Stiker Cetak</label>
                        <select id="qrModalPrintCopies" class="w-full h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 text-xs font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="1">1 Label</option>
                            <option value="4">4 Label</option>
                            <option value="8">8 Label</option>
                            <option value="12" selected>12 Label (1 Baris)</option>
                            <option value="24">24 Label (1 Lembar)</option>
                            <option value="40">40 Label</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-900/90 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2">
                <button type="button" onclick="window.closeQrModal && window.closeQrModal()" class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-slate-200/70 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300/70 dark:hover:bg-slate-700 transition-colors cursor-pointer">
                    Tutup
                </button>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" id="qrModalDownloadBtn" onclick="window.downloadQrLabelPng && window.downloadQrLabelPng()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors shadow-xs cursor-pointer">
                        <i data-lucide="download" class="w-3.5 h-3.5"></i>
                        <span>Unduh PNG</span>
                    </button>
                    <button type="button" id="qrModalPrintBtn" onclick="window.printQrLabel && window.printQrLabel()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition-colors shadow-sm shadow-indigo-500/20 cursor-pointer">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                        <span>Cetak Label</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        let currentQrProductData = null;

        // Standard Code-128B pattern table (values 0..106) for offline 1D barcode rendering
        const CODE128_PATTERNS = [
            '11011001100','11001101100','11001100110','10010011000','10010001100','10001001100','10011001000','10011000100','10001100100','11001001000',
            '11001000100','11000100100','10110011100','10011011100','10011001110','10111001100','10011101100','10011100110','11001110010','11001011100',
            '11001001110','11011100100','11001110100','11101101110','11101001100','11100101100','11100100110','11101100100','11100110100','11100110010',
            '11011011000','11011000110','11000110110','10100011000','10001011000','10001000110','10110001000','10001101000','10001100010','11010001000',
            '11000101000','11000100010','10110111000','10110001110','10001101110','10111011000','10111000110','10001110110','11101110110','11010001110',
            '11000101110','11011101000','11011100010','11011101110','11101011000','11101000110','11100010110','11101101000','11101100010','11100011010',
            '11101111010','11001000010','11110001010','10100110000','10100001100','10010110000','10010000110','10000101100','10000100110','10110010000',
            '10110000100','10011010000','10011000010','10000110100','10000110010','11000010010','11001010000','11110111010','11000010100','10001111010',
            '10100111100','10010111100','10010011110','10111100100','10011110100','10011110010','11110100100','11110010100','11110010010','11011011110',
            '11011110110','11110110110','10101111000','10100011110','10001011110','10111101000','10111100010','11110101000','11110100010','10111011110',
            '10111101110','11101011110','11110101110','11010000100','11010010000','11010011100','1100011101011'
        ];

        function drawCode128Barcode(canvas, text, height = 56) {
            if (!canvas) return '';
            const clean = String(text || '').replace(/[^\x20-\x7E]/g, '').trim() || '00000000';
            const codes = [104]; // Start Code B
            let checksum = 104;
            for (let i = 0; i < clean.length; i++) {
                const val = clean.charCodeAt(i) - 32;
                codes.push(val);
                checksum += val * (i + 1);
            }
            codes.push(checksum % 103);
            codes.push(106); // Stop

            const bits = codes.map(c => CODE128_PATTERNS[c] || '').join('');
            const barWidth = clean.length > 20 ? 1.5 : 2;
            const quietZone = 16;
            const totalWidth = Math.max(180, Math.ceil(bits.length * barWidth + quietZone * 2));
            canvas.width = totalWidth;
            canvas.height = height;

            const ctx = canvas.getContext('2d');
            if (!ctx) return '';
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, totalWidth, height);

            ctx.fillStyle = '#0f172a';
            let x = quietZone;
            for (let i = 0; i < bits.length; i++) {
                if (bits[i] === '1') {
                    ctx.fillRect(x, 4, barWidth, height - 8);
                }
                x += barWidth;
            }
            return canvas.toDataURL('image/png');
        }

        function formatRupiahQr(amount) {
            const num = Number(amount) || 0;
            return 'Rp ' + num.toLocaleString('id-ID');
        }

        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }

        function findLocalProductData(productId) {
            const row = document.querySelector(`.product-row[data-id="${productId}"], .product-card[data-id="${productId}"]`);
            if (!row) return null;
            try {
                const raw = JSON.parse(row.getAttribute('data-raw') || '{}');
                if (raw && raw.id) {
                    return {
                        id: raw.id,
                        name: raw.name || row.getAttribute('data-name') || 'Produk',
                        sku: raw.sku || row.getAttribute('data-sku') || '-',
                        price: Number(raw.price) || 0,
                        barcode: raw.barcode || row.getAttribute('data-barcode') || '',
                    };
                }
            } catch (e) {}
            return {
                id: productId,
                name: row.getAttribute('data-name') || 'Produk',
                sku: row.getAttribute('data-sku') || '-',
                price: 0,
                barcode: row.getAttribute('data-barcode') || '',
            };
        }

        async function buildQrCodeDataUrl(codeText, svgMarkup = null, size = 360) {
            if (svgMarkup && typeof svgMarkup === 'string' && svgMarkup.includes('<svg')) {
                return 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgMarkup)));
            }
            return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&margin=10&data=${encodeURIComponent(codeText)}`;
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

        window.updateQrStickerMode = function updateQrStickerMode(mode) {
            const qrBox = document.getElementById('qrModalQrBox');
            const barBox = document.getElementById('qrModalBarcodeBox');
            if (!qrBox || !barBox) return;
            if (mode === 'qr') {
                qrBox.classList.remove('hidden');
                barBox.classList.add('hidden');
            } else if (mode === 'barcode') {
                qrBox.classList.add('hidden');
                barBox.classList.remove('hidden');
            } else {
                qrBox.classList.remove('hidden');
                barBox.classList.remove('hidden');
            }
        };

        async function renderProductStickerDataUrl(product) {
            const mode = document.getElementById('qrModalStickerType')?.value || 'both';
            const qrSrc = product.qrDataUrl || document.getElementById('qrModalImage')?.src || '';
            const barcodeCanvas = document.getElementById('qrModalBarcodeCanvas');

            let qrImg = null;
            if (mode !== 'barcode' && qrSrc) {
                qrImg = await new Promise((resolve) => {
                    const img = new Image();
                    img.onload = () => resolve(img);
                    img.onerror = () => resolve(null);
                    img.src = qrSrc;
                });
            }

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = 640;
            canvas.height = mode === 'barcode' ? 420 : (mode === 'qr' ? 760 : 880);

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.strokeStyle = '#cbd5e1';
            ctx.lineWidth = 4;
            ctx.strokeRect(12, 12, canvas.width - 24, canvas.height - 24);

            ctx.fillStyle = '#0f172a';
            ctx.textAlign = 'center';
            ctx.font = 'bold 28px Inter, system-ui, sans-serif';
            const nameLines = wrapCanvasText(ctx, product.name || 'Produk', 560);
            nameLines.forEach((line, idx) => {
                ctx.fillText(line, 320, 60 + idx * 34);
            });

            let cursorY = 60 + nameLines.length * 34 + 6;
            ctx.fillStyle = '#4f46e5';
            ctx.font = 'bold 24px Inter, system-ui, sans-serif';
            const priceStr = product.price ? formatRupiahQr(product.price) : '';
            const skuStr = product.sku ? `SKU: ${product.sku}` : '';
            ctx.fillText([skuStr, priceStr].filter(Boolean).join('  •  '), 320, cursorY);
            cursorY += 24;

            if (mode !== 'barcode' && qrImg) {
                const qrSize = 420;
                const qrX = (canvas.width - qrSize) / 2;
                ctx.drawImage(qrImg, qrX, cursorY, qrSize, qrSize);
                cursorY += qrSize + 16;
            }

            if (mode !== 'qr' && barcodeCanvas) {
                const bw = Math.min(540, barcodeCanvas.width * 1.4);
                const bh = mode === 'barcode' ? 140 : 95;
                ctx.drawImage(barcodeCanvas, (canvas.width - bw) / 2, cursorY, bw, bh);
                cursorY += bh + 28;
            } else {
                cursorY += 24;
            }

            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 22px monospace';
            ctx.fillText(String(product.barcode || ''), 320, cursorY);

            return canvas.toDataURL('image/png');
        }

        function updateProductDomBarcode(productId, barcode) {
            const cells = document.querySelectorAll(`[data-qr-cell="${productId}"]`);
            cells.forEach((cell) => {
                const row = cell.closest('.product-row');
                const sku = row?.getAttribute('data-sku') || '';
                if (row) row.setAttribute('data-barcode', barcode);
                cell.innerHTML = `
                    <div class="font-mono text-[11px] text-slate-700 dark:text-slate-300">${sku}</div>
                    <button
                        type="button"
                        data-open-qr-modal="${productId}"
                        onclick="window.openQrModal && window.openQrModal(${productId})"
                        class="font-mono text-[10px] text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 mt-0.5 inline-flex items-center gap-1 cursor-pointer transition-colors"
                        title="Klik untuk lihat & cetak Barcode / QR"
                    >
                        <i data-lucide="barcode" class="w-3 h-3 text-slate-400"></i>
                        <span class="underline decoration-dotted underline-offset-2">${barcode}</span>
                    </button>
                    <div class="mt-1 flex items-center gap-1">
                        <button
                            type="button"
                            data-open-qr-modal="${productId}"
                            onclick="window.openQrModal && window.openQrModal(${productId})"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 border border-indigo-200/70 dark:border-indigo-800/70 transition-colors cursor-pointer"
                            title="Lihat & Cetak QR / Barcode"
                        >
                            <i data-lucide="qr-code" class="w-3 h-3"></i>
                            <span>Lihat QR & Barcode</span>
                        </button>
                    </div>
                `;
            });

            const generateBtns = document.querySelectorAll(`[data-qr-generate-btn="${productId}"]`);
            generateBtns.forEach((btn) => {
                btn.removeAttribute('data-qr-generate-btn');
                btn.setAttribute('data-open-qr-modal', String(productId));
                btn.setAttribute('onclick', `window.openQrModal && window.openQrModal(${productId})`);
                btn.className = 'px-2.5 py-1.5 rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 font-semibold text-xs transition-colors flex items-center gap-1 cursor-pointer';
                btn.innerHTML = `<i data-lucide="qr-code" class="w-3.5 h-3.5"></i><span>Lihat QR</span>`;
            });

            document.querySelectorAll('.product-row, .product-card, [data-catalog-raw]').forEach((el) => {
                const attrName = el.hasAttribute('data-catalog-raw') ? 'data-catalog-raw' : (el.hasAttribute('data-raw') ? 'data-raw' : null);
                if (!attrName) return;
                try {
                    const raw = JSON.parse(el.getAttribute(attrName) || '{}');
                    if (Number(raw.id) === Number(productId)) {
                        raw.barcode = barcode;
                        el.setAttribute(attrName, JSON.stringify(raw));
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
            const barcodeCanvas = document.getElementById('qrModalBarcodeCanvas');

            if (!modal) return;

            modal.classList.remove('hidden');
            imgEl.classList.add('hidden');
            loaderEl.classList.remove('hidden');

            // Immediately populate from DOM row if available so UI responds in 0ms
            const localData = (preloadedData && typeof preloadedData === 'object' && preloadedData.barcode)
                ? preloadedData
                : findLocalProductData(productId);

            if (localData && localData.barcode) {
                nameEl.textContent = localData.name || 'Produk';
                skuEl.textContent = `SKU: ${localData.sku || '-'}`;
                if (priceEl) priceEl.textContent = localData.price > 0 ? formatRupiahQr(localData.price) : 'Label Fisik';
                codeEl.textContent = localData.barcode;
                const barcodeDataUrl = drawCode128Barcode(barcodeCanvas, localData.barcode, 56);
                currentQrProductData = {
                    id: productId,
                    name: localData.name || 'Produk',
                    sku: localData.sku || '-',
                    price: Number(localData.price) || 0,
                    barcode: String(localData.barcode).trim(),
                    barcodeDataUrl: barcodeDataUrl,
                    qrDataUrl: '',
                    downloadUrl: `/products/${productId}/qr-download`,
                };
            } else {
                nameEl.textContent = 'Memuat...';
                skuEl.textContent = 'SKU: -';
                if (priceEl) priceEl.textContent = '';
                codeEl.textContent = '-';
            }

            if (window.lucide) window.lucide.createIcons();

            try {
                let data = (preloadedData && typeof preloadedData === 'object' && preloadedData.svg) ? preloadedData : null;
                if (!data) {
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

                const productName = data.name || data.product_name || localData?.name || 'Produk';
                const productSku = data.sku || localData?.sku || '-';
                const productPrice = Number(data.price ?? localData?.price) || 0;
                const barcode = String(data.barcode || localData?.barcode || '').trim();

                nameEl.textContent = productName;
                skuEl.textContent = `SKU: ${productSku}`;
                if (priceEl) {
                    priceEl.textContent = productPrice > 0 ? formatRupiahQr(productPrice) : 'Label Fisik';
                }
                codeEl.textContent = barcode;

                const barcodeDataUrl = drawCode128Barcode(barcodeCanvas, barcode, 56);
                const qrDataUrl = await buildQrCodeDataUrl(barcode, data.svg || null, 400);

                currentQrProductData = {
                    id: productId,
                    name: productName,
                    sku: productSku,
                    price: productPrice,
                    barcode: barcode,
                    barcodeDataUrl: barcodeDataUrl,
                    qrDataUrl: qrDataUrl,
                    downloadUrl: data.download_url || `/products/${productId}/qr-download`,
                };

                imgEl.onload = () => {
                    loaderEl.classList.add('hidden');
                    imgEl.classList.remove('hidden');
                };
                imgEl.src = qrDataUrl;
            } catch (err) {
                if (currentQrProductData && currentQrProductData.barcode) {
                    // Fallback: still show QR via fallback URL + offline 1D Barcode even if AJAX failed
                    const fallbackQr = `https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=10&data=${encodeURIComponent(currentQrProductData.barcode)}`;
                    currentQrProductData.qrDataUrl = fallbackQr;
                    imgEl.onload = () => {
                        loaderEl.classList.add('hidden');
                        imgEl.classList.remove('hidden');
                    };
                    imgEl.src = fallbackQr;
                } else {
                    window.closeQrModal();
                    if (window.showToast) {
                        window.showToast('Gagal memuat QR Code', err.message || 'Terjadi kesalahan', 'error');
                    }
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
                link.download = `Label_${slug}_${currentQrProductData.barcode}.png`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                if (window.showToast) {
                    window.showToast('Berhasil', 'Label PNG berhasil diunduh.', 'success');
                }
            } catch (err) {
                if (currentQrProductData.downloadUrl) {
                    window.open(currentQrProductData.downloadUrl, '_blank');
                }
            }
        };

        window.printQrLabel = function printQrLabel() {
            if (!currentQrProductData || !currentQrProductData.barcode) return;

            const copiesSelect = document.getElementById('qrModalPrintCopies');
            const mode = document.getElementById('qrModalStickerType')?.value || 'both';
            const copies = Math.max(1, Math.min(100, parseInt(copiesSelect?.value || '12', 10) || 12));
            const qrSrc = currentQrProductData.qrDataUrl || document.getElementById('qrModalImage')?.src || '';
            const barcodeCanvas = document.getElementById('qrModalBarcodeCanvas');
            const barcodeSrc = currentQrProductData.barcodeDataUrl || (barcodeCanvas ? barcodeCanvas.toDataURL('image/png') : '');

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
            const qrHtml = (mode !== 'barcode' && qrSrc)
                ? `<img class="label-qr" src="${qrSrc}" alt="QR ${safeBarcode}" />`
                : '';
            const barcodeHtml = (mode !== 'qr' && barcodeSrc)
                ? `<img class="label-barcode" src="${barcodeSrc}" alt="Barcode ${safeBarcode}" />`
                : '';

            let cardsHtml = '';
            for (let i = 0; i < copies; i++) {
                cardsHtml += `
                    <div class="label-card">
                        <div class="label-name">${safeName}</div>
                        ${priceHtml}
                        ${qrHtml}
                        ${barcodeHtml}
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
    <title>Cetak Label Produk - ${safeName}</title>
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
            grid-template-columns: repeat(auto-fill, minmax(165px, 1fr));
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
            width: 108px;
            height: 108px;
            object-fit: contain;
            margin: 2px auto;
        }
        .label-barcode {
            width: 142px;
            height: 42px;
            object-fit: contain;
            margin: 3px auto 1px;
        }
        .label-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 9.5px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 3px;
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
            <div class="toolbar-title">Cetak Stiker Produk: ${safeName} (${copies} Label)</div>
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
            const btn = triggerBtn || document.querySelector(`[data-qr-generate-btn="${productId}"]`);
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

                updateProductDomBarcode(productId, data.barcode);

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

        // Delegated click listener so buttons always work across Livewire wire:navigate
        if (!window.__productQrDelegatedBound) {
            window.__productQrDelegatedBound = true;
            document.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof Element)) return;

                const openBtn = target.closest('[data-open-qr-modal]');
                if (openBtn) {
                    event.preventDefault();
                    const pid = openBtn.getAttribute('data-open-qr-modal');
                    if (pid && window.openQrModal) {
                        window.openQrModal(pid);
                    }
                    return;
                }

                const genBtn = target.closest('[data-qr-generate-btn]');
                if (genBtn) {
                    event.preventDefault();
                    const pid = genBtn.getAttribute('data-qr-generate-btn');
                    if (pid && window.generateQrCode) {
                        window.generateQrCode(pid, genBtn);
                    }
                }
            });
        }
    })();
</script>
