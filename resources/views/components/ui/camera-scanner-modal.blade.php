{{-- Universal Camera Barcode & QR Scanner Modal / Flexible POS Dock Component --}}
<div id="cameraScannerModal" class="fixed inset-0 z-[150] hidden" role="dialog" aria-modal="true" aria-labelledby="cameraScannerTitle">
    {{-- Backdrop (Hidden automatically in POS Flexible Docked Mode so Cart & Catalog stay visible) --}}
    <div id="cameraScannerBackdrop" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" onclick="window.CameraScanner && window.CameraScanner.close()"></div>

    {{-- Modal / Floating Dock Wrapper --}}
    <div id="cameraScannerWrapper" class="fixed inset-0 z-10 overflow-y-auto flex items-center justify-center p-4">
        <div id="cameraScannerCard" class="relative w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden transform transition-all pointer-events-auto">
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                        <i data-lucide="scan-barcode" class="w-4 h-4"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <h3 id="cameraScannerTitle" class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white truncate">Scanner Kasir Aktif</h3>
                            <span id="cameraScannerLivePill" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                LIVE
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">Kamera & Alat Scanner (USB/Bluetooth) siap • Scan ulang = tambah Qty (N+1)</p>
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <button
                        type="button"
                        id="cameraScannerDockToggleBtn"
                        onclick="window.CameraScanner && window.CameraScanner.toggleDockedLayout()"
                        class="hidden px-2 py-1 rounded-lg text-[10px] font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 hover:text-indigo-600 dark:hover:text-indigo-400 border border-slate-200 dark:border-slate-700 transition-colors cursor-pointer"
                        title="Ubah posisi jendela kamera (Pojok Fleksibel / Tengah)"
                    >
                        <span id="cameraScannerDockToggleLabel">Mode Tengah</span>
                    </button>
                    <button
                        type="button"
                        id="cameraScannerMinimizeBtn"
                        onclick="window.CameraScanner && window.CameraScanner.toggleMinimizeVideo()"
                        class="hidden w-7 h-7 rounded-lg items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                        title="Kecilkan / Tampilkan Video Kamera"
                    >
                        <i data-lucide="minimize-2" class="w-3.5 h-3.5"></i>
                    </button>
                    <button type="button" onclick="window.CameraScanner && window.CameraScanner.close()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors cursor-pointer" aria-label="Tutup kamera">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            {{-- Live Status & (N+1) Feedback Card (Always visible at top of scanner so cashier immediately sees scan result) --}}
            <div id="cameraScannerContinuousBanner" class="hidden px-3.5 py-2.5 bg-slate-900 border-b border-slate-800 transition-all duration-200">
                <div id="cameraScannerContinuousCard" class="px-3 py-2 rounded-xl bg-slate-800/90 border border-slate-700 text-slate-200 flex items-center justify-between gap-2 transition-all duration-200">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span id="cameraScannerStatusIcon" class="w-6 h-6 rounded-lg bg-indigo-500/20 border border-indigo-400/40 text-indigo-300 flex items-center justify-center text-xs font-black shrink-0">
                            ⋯
                        </span>
                        <div class="min-w-0">
                            <p id="cameraScannerLastScanTitle" class="text-xs font-bold text-white truncate">Menunggu Scan Produk...</p>
                            <p id="cameraScannerLastScanSub" class="text-[11px] text-slate-400 truncate">Arahkan barcode/QR ke kamera atau tembak dengan Alat Scanner</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button
                            type="button"
                            id="cameraScannerRepeatPlusBtn"
                            onclick="window.CameraScanner && window.CameraScanner.repeatLastScan()"
                            class="hidden items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-500 hover:bg-emerald-400 active:scale-95 text-slate-950 text-[11px] font-extrabold shadow-sm transition-all cursor-pointer"
                            title="Tambah +1 lagi untuk produk yang baru saja di-scan (N+1)"
                        >
                            <span>+1 Lagi</span>
                        </button>
                        <span id="cameraScannerScanCountBadge" class="px-2 py-1 rounded-lg bg-slate-700/80 border border-slate-600 text-[10px] font-mono font-bold text-slate-200 shrink-0">
                            0 scan
                        </span>
                    </div>
                </div>
            </div>

            {{-- Video Viewport --}}
            <div id="cameraScannerViewport" class="relative bg-slate-950 aspect-[16/10] w-full overflow-hidden flex items-center justify-center">
                <video id="cameraScannerVideo" class="w-full h-full object-cover hidden transition-transform duration-150 origin-center" playsinline muted autoplay></video>
                <input type="file" id="cameraScannerFileInput" accept="image/*" class="hidden" onchange="window.CameraScanner && window.CameraScanner.handleFileSelect(event)" />

                {{-- Flash Success Pop Overlay on Video --}}
                <div id="cameraScannerSuccessPop" class="hidden absolute top-2.5 inset-x-3 z-30 pointer-events-none flex justify-center">
                    <div class="px-3.5 py-1.5 rounded-full bg-emerald-500 text-slate-950 font-extrabold text-xs shadow-lg flex items-center gap-1.5 animate-bounce">
                        <span>✓</span>
                        <span id="cameraScannerSuccessPopText">BERHASIL DISCAN (+1)</span>
                    </div>
                </div>

                {{-- Target Reticle / Viewfinder Overlay --}}
                <div id="cameraScannerViewfinder" class="absolute inset-0 pointer-events-none flex flex-col items-center justify-center hidden">
                    <div id="cameraScannerReticle" class="relative w-60 h-36 border-2 border-indigo-400/85 rounded-2xl shadow-[0_0_0_9999px_rgba(2,6,23,0.38)] transition-all duration-200">
                        {{-- Corner Accents --}}
                        <div class="absolute -top-0.5 -left-0.5 w-6 h-6 border-t-4 border-l-4 border-indigo-400 rounded-tl-xl"></div>
                        <div class="absolute -top-0.5 -right-0.5 w-6 h-6 border-t-4 border-r-4 border-indigo-400 rounded-tr-xl"></div>
                        <div class="absolute -bottom-0.5 -left-0.5 w-6 h-6 border-b-4 border-l-4 border-indigo-400 rounded-bl-xl"></div>
                        <div class="absolute -bottom-0.5 -right-0.5 w-6 h-6 border-b-4 border-r-4 border-indigo-400 rounded-br-xl"></div>

                        {{-- Multi-angle guide lines (subtle) --}}
                        <div class="absolute inset-x-4 top-1/3 h-px bg-indigo-400/20"></div>
                        <div class="absolute inset-x-4 bottom-1/3 h-px bg-indigo-400/20"></div>

                        {{-- Animated Laser Line --}}
                        <div class="absolute inset-x-2 top-1/2 h-0.5 bg-gradient-to-r from-transparent via-rose-500 to-transparent shadow-[0_0_8px_#f43f5e] animate-pulse"></div>
                    </div>
                    <span id="cameraScannerHintPill" class="mt-2 px-2.5 py-0.5 rounded-full bg-slate-950/75 text-[10px] font-medium text-slate-200 backdrop-blur-xs">
                        Scan produk yang sama berulang kali otomatis menambah Qty (N+1)
                    </span>
                </div>

                {{-- Loading / Permission / Fallback State --}}
                <div id="cameraScannerLoader" class="flex flex-col items-center justify-center p-6 text-center z-10">
                    <div class="w-12 h-12 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-indigo-400 mb-3 animate-pulse">
                        <i data-lucide="camera" class="w-6 h-6"></i>
                    </div>
                    <p id="cameraScannerStatusText" class="text-xs font-semibold text-slate-200">Menyiapkan kamera...</p>
                    <p id="cameraScannerSubText" class="text-[11px] text-slate-400 mt-1 max-w-xs">Pastikan izin akses kamera diberikan pada browser Anda.</p>
                </div>
            </div>

            {{-- Direct Alat Scanner / Manual Code Input Inside Scanner Card --}}
            <div class="px-4 py-2 bg-slate-900/95 border-t border-slate-800 flex items-center gap-2">
                <div class="relative flex-1">
                    <i data-lucide="barcode" class="w-3.5 h-3.5 text-indigo-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input
                        type="text"
                        id="cameraScannerManualInput"
                        placeholder="Alat Scanner (USB/Bluetooth) / ketik barcode lalu Enter..."
                        autocomplete="off"
                        onkeydown="if(event.key === 'Enter'){ event.preventDefault(); window.CameraScanner && window.CameraScanner.submitManualInput(); }"
                        class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-slate-800 border border-slate-700 text-xs text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <button
                    type="button"
                    onclick="window.CameraScanner && window.CameraScanner.submitManualInput()"
                    class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shrink-0 cursor-pointer transition-colors"
                >
                    + Tambah
                </button>
            </div>

            {{-- Camera Enhancement Controls Bar (Zoom, Anti-Glare, Torch) --}}
            <div id="cameraScannerEnhanceBar" class="px-5 py-2.5 bg-slate-900 text-slate-200 border-t border-slate-800 flex items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2 flex-1">
                    <i data-lucide="zoom-in" class="w-3.5 h-3.5 text-indigo-400 shrink-0"></i>
                    <span class="text-[11px] font-semibold text-slate-300 shrink-0">Zoom:</span>
                    <input
                        id="cameraScannerZoomRange"
                        type="range"
                        min="1"
                        max="2.6"
                        step="0.1"
                        value="1.2"
                        oninput="window.CameraScanner && window.CameraScanner.setZoom(this.value)"
                        class="w-full h-1.5 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                        title="Perbesar area barcode agar lebih fokus pada kemasan plastik"
                    />
                    <span id="cameraScannerZoomLabel" class="text-[11px] font-mono font-bold text-indigo-300 w-9 text-right">1.2x</span>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                    <button
                        type="button"
                        id="cameraScannerContinuousBtn"
                        onclick="window.CameraScanner && window.CameraScanner.toggleContinuousMode()"
                        class="hidden items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 hover:bg-emerald-500/30 transition-colors cursor-pointer"
                        title="Mode Scan Beruntun: Kamera tetap aktif setelah produk masuk keranjang"
                    >
                        <i data-lucide="repeat" class="w-3 h-3"></i>
                        <span id="cameraScannerContinuousLabel">Beruntun ON</span>
                    </button>

                    <button
                        type="button"
                        id="cameraScannerEnhanceBtn"
                        onclick="window.CameraScanner && window.CameraScanner.toggleEnhanceMode()"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 hover:bg-indigo-500/30 transition-colors cursor-pointer"
                        title="Mode Anti-Silau & Kompensasi Lekukan Plastik Aktif"
                    >
                        <i data-lucide="sparkles" class="w-3 h-3"></i>
                        <span id="cameraScannerEnhanceLabel">Anti-Silau ON</span>
                    </button>

                    <button
                        type="button"
                        id="cameraScannerTorchBtn"
                        onclick="window.CameraScanner && window.CameraScanner.toggleTorch()"
                        class="hidden items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-slate-800 text-slate-300 border border-slate-700 hover:bg-slate-700 transition-colors cursor-pointer"
                        title="Nyalakan Lampu Senter Kamera"
                    >
                        <i data-lucide="flashlight" class="w-3 h-3"></i>
                        <span>Senter</span>
                    </button>
                </div>
            </div>

            {{-- Footer Controls --}}
            <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-900/90 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="window.CameraScanner && window.CameraScanner.triggerFileSelect()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/70 dark:border-indigo-800/70 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-colors cursor-pointer">
                        <i data-lucide="image" class="w-3.5 h-3.5"></i>
                        <span>Scan dari Gambar</span>
                    </button>
                    <span id="cameraScannerFormatBadge" class="hidden sm:inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                        Multi-Sudut Aktif
                    </span>
                </div>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" id="cameraScannerSwitchBtn" onclick="window.CameraScanner && window.CameraScanner.switchCamera()" class="hidden items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 transition-colors cursor-pointer">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                        <span>Putar Kamera</span>
                    </button>
                    <button type="button" id="cameraScannerCloseFooterBtn" onclick="window.CameraScanner && window.CameraScanner.close()" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-slate-200/80 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300/70 dark:hover:bg-slate-700 transition-colors cursor-pointer">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        /**
         * Universal Multi-Pass Camera Scanner Manager
         * Built with tolerance for:
         * 1. Lighting / Glare on plastic packaging (Adaptive Contrast & GlobalHistogramBinarizer + HybridBinarizer)
         * 2. Curved surfaces / bottles / sachets (Center ROI magnification + horizontal aspect unwarping 1.22x)
         * 3. Tilted / skewed angles (Multi-angle rotation passes: 0°, ±18°, ±35°, 90°)
         * 4. Continuous POS scanning (stays open after adding items with smart duplicate cooldown)
         */
        class CameraScannerManager {
            constructor() {
                this.stream = null;
                this.isScanning = false;
                this.onDetectCallback = null;
                this.continuous = false;
                this.docked = false;
                this.videoMinimized = false;
                this.lastScannedCode = '';
                this.lastRepeatableCode = '';
                this.lastScannedAt = 0;
                this.sessionScanCount = 0;
                this.cooldownResetTimer = null;
                this.popTimer = null;
                this.detector = null;
                this.zxingReader = null;
                this.facingMode = 'environment';
                this.scanFrameId = null;
                this.scanTimer = null;
                this.frameCounter = 0;
                this.zoomLevel = 1.2;
                this.hardwareZoomSupported = false;
                this.torchSupported = false;
                this.torchOn = false;
                this.enhanceMode = true;
                this.canvas = document.createElement('canvas');
                this.workCanvas = document.createElement('canvas');
            }

            get modal() { return document.getElementById('cameraScannerModal'); }
            get video() { return document.getElementById('cameraScannerVideo'); }
            get loader() { return document.getElementById('cameraScannerLoader'); }
            get viewfinder() { return document.getElementById('cameraScannerViewfinder'); }
            get formatBadge() { return document.getElementById('cameraScannerFormatBadge'); }
            get fileInput() { return document.getElementById('cameraScannerFileInput'); }

            resetLoader(title = 'Menyiapkan kamera...', subtitle = 'Pastikan izin akses kamera diberikan pada browser Anda.') {
                if (!this.loader) return;
                this.loader.classList.remove('hidden');
                this.loader.innerHTML = `
                    <div class="w-12 h-12 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-indigo-400 mb-3 animate-pulse">
                        <i data-lucide="camera" class="w-6 h-6"></i>
                    </div>
                    <p id="cameraScannerStatusText" class="text-xs font-semibold text-slate-200">${title}</p>
                    <p id="cameraScannerSubText" class="text-[11px] text-slate-400 mt-1 max-w-xs">${subtitle}</p>
                `;
                if (window.lucide) window.lucide.createIcons();
            }

            async ensureDecoder() {
                // 1. Initialize Native BarcodeDetector if available
                if (!this.detector && ('BarcodeDetector' in window)) {
                    try {
                        const formats = await window.BarcodeDetector.getSupportedFormats();
                        const desired = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'code_93', 'qr_code', 'itf', 'codabar', 'data_matrix'];
                        const activeFormats = desired.filter(f => formats.includes(f));
                        this.detector = new window.BarcodeDetector({
                            formats: activeFormats.length > 0 ? activeFormats : undefined
                        });
                    } catch (e) {
                        console.warn('BarcodeDetector init error:', e);
                    }
                }

                // 2. Always also load ZXing MultiFormatReader with TRY_HARDER + ALSO_INVERTED for difficult/tilted/glare frames
                if (!this.zxingReader) {
                    try {
                        if (!window.ZXing) {
                            await new Promise((resolve, reject) => {
                                const existing = document.querySelector('script[data-zxing-lib]');
                                if (existing) {
                                    if (window.ZXing) { resolve(); return; }
                                    existing.addEventListener('load', resolve, { once: true });
                                    existing.addEventListener('error', reject, { once: true });
                                    return;
                                }
                                const s = document.createElement('script');
                                s.src = 'https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js';
                                s.async = true;
                                s.setAttribute('data-zxing-lib', '1');
                                s.onload = resolve;
                                s.onerror = reject;
                                document.head.appendChild(s);
                            });
                        }
                        if (window.ZXing && window.ZXing.MultiFormatReader) {
                            const Z = window.ZXing;
                            const hints = new Map();
                            if (Z.DecodeHintType && Z.BarcodeFormat) {
                                hints.set(Z.DecodeHintType.TRY_HARDER, true);
                                if (Z.DecodeHintType.ALSO_INVERTED !== undefined) {
                                    hints.set(Z.DecodeHintType.ALSO_INVERTED, true);
                                }
                                const fmts = [
                                    Z.BarcodeFormat.EAN_13,
                                    Z.BarcodeFormat.EAN_8,
                                    Z.BarcodeFormat.UPC_A,
                                    Z.BarcodeFormat.UPC_E,
                                    Z.BarcodeFormat.CODE_128,
                                    Z.BarcodeFormat.CODE_39,
                                    Z.BarcodeFormat.CODE_93,
                                    Z.BarcodeFormat.ITF,
                                    Z.BarcodeFormat.CODABAR,
                                    Z.BarcodeFormat.QR_CODE,
                                    Z.BarcodeFormat.DATA_MATRIX,
                                ].filter(Boolean);
                                hints.set(Z.DecodeHintType.POSSIBLE_FORMATS, fmts);
                            }
                            this.zxingReader = new Z.MultiFormatReader();
                            this.zxingReader.setHints(hints);
                        }
                    } catch (err) {
                        console.warn('ZXing fallback load failed:', err);
                    }
                }

                return (this.detector || this.zxingReader) ? 'ready' : null;
            }

            async open(onDetect, options = {}) {
                if (!this.modal) return;

                this.onDetectCallback = onDetect;
                this.continuous = Boolean(options && options.continuous);
                this.docked = Boolean(options && options.docked);
                this.videoMinimized = false;
                this.lastScannedCode = '';
                this.lastRepeatableCode = '';
                this.lastScannedAt = 0;
                this.sessionScanCount = 0;
                this.modal.classList.remove('hidden');

                this.applyLayoutMode();
                this.syncContinuousUi(options && options.allowContinuousToggle !== undefined ? Boolean(options.allowContinuousToggle) : this.continuous);

                const viewport = document.getElementById('cameraScannerViewport');
                const enhanceBar = document.getElementById('cameraScannerEnhanceBar');
                if (viewport) viewport.classList.remove('hidden');
                if (enhanceBar) enhanceBar.classList.remove('hidden');

                if (window.lucide) {
                    window.lucide.createIcons();
                }

                this.resetLoader('Menyiapkan pemindai pintar...', 'Mengaktifkan kamera dengan mode toleransi cahaya & kemasan plastik.');
                if (this.video) this.video.classList.add('hidden');
                if (this.viewfinder) this.viewfinder.classList.add('hidden');
                if (this.formatBadge) this.formatBadge.classList.add('hidden');

                // Start camera immediately while decoder initializes in parallel
                const [, engine] = await Promise.all([
                    this.startStream(),
                    this.ensureDecoder(),
                ]);

                if (!engine && !this.stream) {
                    this.showError(
                        'Kamera Langsung Tidak Tersedia',
                        'Gunakan Alat Scanner (USB/Bluetooth), ketik barcode di kolom bawah, atau klik "Scan dari Gambar".'
                    );
                }
            }

            applyLayoutMode() {
                const modal = this.modal;
                const backdrop = document.getElementById('cameraScannerBackdrop');
                const wrapper = document.getElementById('cameraScannerWrapper');
                const card = document.getElementById('cameraScannerCard');
                const dockBtn = document.getElementById('cameraScannerDockToggleBtn');
                const dockLbl = document.getElementById('cameraScannerDockToggleLabel');
                const minBtn = document.getElementById('cameraScannerMinimizeBtn');

                if (!modal || !wrapper || !card) return;

                if (this.continuous) {
                    if (dockBtn) dockBtn.classList.remove('hidden');
                    if (minBtn) {
                        minBtn.classList.remove('hidden');
                        minBtn.classList.add('flex');
                    }
                } else {
                    if (dockBtn) dockBtn.classList.add('hidden');
                    if (minBtn) {
                        minBtn.classList.add('hidden');
                        minBtn.classList.remove('flex');
                    }
                }

                if (this.docked) {
                    // Non-blocking floating panel at bottom-left so Cart (Pesanan Kasir) on the right is 100% visible & interactive!
                    modal.className = 'fixed inset-0 z-[140] pointer-events-none';
                    if (backdrop) backdrop.classList.add('hidden');
                    wrapper.className = 'fixed inset-0 z-10 pointer-events-none flex items-end justify-start p-3 sm:p-4';
                    card.className = 'relative w-full max-w-[380px] sm:max-w-[420px] rounded-2xl bg-white dark:bg-slate-900 border-2 border-indigo-500/60 dark:border-indigo-500/50 shadow-[0_20px_50px_rgba(0,0,0,0.55)] overflow-hidden transform transition-all pointer-events-auto';
                    if (dockLbl) dockLbl.textContent = 'Mode Tengah';
                } else {
                    modal.className = 'fixed inset-0 z-[150]';
                    if (backdrop) backdrop.classList.remove('hidden');
                    wrapper.className = 'fixed inset-0 z-10 overflow-y-auto flex items-center justify-center p-4';
                    card.className = 'relative w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden transform transition-all pointer-events-auto';
                    if (dockLbl) dockLbl.textContent = 'Mode Pojok (Lihat Keranjang)';
                }
            }

            toggleDockedLayout() {
                this.docked = !this.docked;
                this.applyLayoutMode();
            }

            toggleMinimizeVideo() {
                this.videoMinimized = !this.videoMinimized;
                const viewport = document.getElementById('cameraScannerViewport');
                const enhanceBar = document.getElementById('cameraScannerEnhanceBar');
                if (viewport) viewport.classList.toggle('hidden', this.videoMinimized);
                if (enhanceBar) enhanceBar.classList.toggle('hidden', this.videoMinimized);
            }

            syncContinuousUi(showToggleBtn = false) {
                const btn = document.getElementById('cameraScannerContinuousBtn');
                const lbl = document.getElementById('cameraScannerContinuousLabel');
                const banner = document.getElementById('cameraScannerContinuousBanner');
                const closeBtn = document.getElementById('cameraScannerCloseFooterBtn');
                const cardEl = document.getElementById('cameraScannerContinuousCard');
                const iconEl = document.getElementById('cameraScannerStatusIcon');
                const titleEl = document.getElementById('cameraScannerLastScanTitle');
                const subEl = document.getElementById('cameraScannerLastScanSub');
                const countEl = document.getElementById('cameraScannerScanCountBadge');
                const repeatBtn = document.getElementById('cameraScannerRepeatPlusBtn');

                if (btn) {
                    if (showToggleBtn || this.continuous) {
                        btn.classList.remove('hidden');
                        btn.classList.add('inline-flex');
                    } else {
                        btn.classList.add('hidden');
                        btn.classList.remove('inline-flex');
                    }
                    btn.className = this.continuous
                        ? 'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 hover:bg-emerald-500/30 transition-colors cursor-pointer'
                        : ((showToggleBtn ? 'inline-flex ' : 'hidden ') + 'items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 transition-colors cursor-pointer');
                }
                if (lbl) {
                    lbl.textContent = this.continuous ? 'Beruntun ON' : 'Beruntun OFF';
                }
                if (banner) {
                    banner.classList.toggle('hidden', !this.continuous);
                }
                if (closeBtn) {
                    closeBtn.textContent = this.continuous ? 'Selesai Scan' : 'Batal';
                }
                if (this.sessionScanCount === 0) {
                    if (cardEl) {
                        cardEl.className = 'px-3 py-2 rounded-xl bg-slate-800/90 border border-slate-700 text-slate-200 flex items-center justify-between gap-2 transition-all duration-200';
                    }
                    if (iconEl) {
                        iconEl.className = 'w-6 h-6 rounded-lg bg-indigo-500/20 border border-indigo-400/40 text-indigo-300 flex items-center justify-center text-xs font-black shrink-0';
                        iconEl.textContent = '⋯';
                    }
                    if (titleEl) titleEl.textContent = 'Menunggu Scan Produk...';
                    if (subEl) subEl.textContent = 'Arahkan barcode/QR ke kamera atau tembak dengan Alat Scanner';
                    if (countEl) countEl.textContent = 'Siap Scan';
                    if (repeatBtn) {
                        repeatBtn.classList.add('hidden');
                        repeatBtn.classList.remove('inline-flex');
                    }
                }
            }

            toggleContinuousMode() {
                this.continuous = !this.continuous;
                this.syncContinuousUi(true);
            }

            updateContinuousFeedback(code, resultMeta = null) {
                const banner = document.getElementById('cameraScannerContinuousBanner');
                const cardEl = document.getElementById('cameraScannerContinuousCard');
                const iconEl = document.getElementById('cameraScannerStatusIcon');
                const titleEl = document.getElementById('cameraScannerLastScanTitle');
                const subEl = document.getElementById('cameraScannerLastScanSub');
                const countEl = document.getElementById('cameraScannerScanCountBadge');
                const repeatBtn = document.getElementById('cameraScannerRepeatPlusBtn');
                const popEl = document.getElementById('cameraScannerSuccessPop');
                const popText = document.getElementById('cameraScannerSuccessPopText');

                if (banner) {
                    banner.classList.remove('hidden');
                }

                const isSuccess = !resultMeta || resultMeta.found !== false;
                const label = (resultMeta && resultMeta.label) ? resultMeta.label : `✓ Kode: ${code}`;
                const sub = (resultMeta && resultMeta.subtitle) ? resultMeta.subtitle : 'Berhasil • Scan lagi untuk tambah Qty (N+1)';

                if (cardEl) {
                    cardEl.className = isSuccess
                        ? 'px-3 py-2 rounded-xl bg-emerald-950/95 border-2 border-emerald-400 text-emerald-100 shadow-lg flex items-center justify-between gap-2 transition-all duration-150 scale-[1.01]'
                        : 'px-3 py-2 rounded-xl bg-rose-950/95 border-2 border-rose-500 text-rose-100 shadow-lg flex items-center justify-between gap-2 transition-all duration-150';
                    setTimeout(() => {
                        if (cardEl) cardEl.classList.remove('scale-[1.01]');
                    }, 220);
                }

                if (iconEl) {
                    iconEl.className = isSuccess
                        ? 'w-6 h-6 rounded-lg bg-emerald-400 text-slate-950 flex items-center justify-center text-xs font-black shrink-0'
                        : 'w-6 h-6 rounded-lg bg-rose-500 text-white flex items-center justify-center text-xs font-black shrink-0';
                    iconEl.textContent = isSuccess ? '✓' : '✕';
                }

                if (titleEl) titleEl.textContent = label;
                if (subEl) subEl.textContent = sub;
                if (countEl) {
                    countEl.textContent = resultMeta && resultMeta.badgeText
                        ? resultMeta.badgeText
                        : `${this.sessionScanCount}x scan`;
                }

                if (repeatBtn) {
                    if (isSuccess && this.lastRepeatableCode) {
                        repeatBtn.classList.remove('hidden');
                        repeatBtn.classList.add('inline-flex');
                    } else {
                        repeatBtn.classList.add('hidden');
                        repeatBtn.classList.remove('inline-flex');
                    }
                }

                if (popEl && popText && isSuccess) {
                    popText.textContent = resultMeta && resultMeta.popText
                        ? resultMeta.popText
                        : `BERHASIL (+1) • ${label}`;
                    popEl.classList.remove('hidden');
                    if (this.popTimer) clearTimeout(this.popTimer);
                    this.popTimer = setTimeout(() => {
                        popEl.classList.add('hidden');
                    }, 1100);
                }
            }

            processDetectedCode(rawCode, { bypassCooldown = false } = {}) {
                const code = String(rawCode || '').trim();
                if (!code) return;

                const now = Date.now();
                // For live video frames, apply an 850ms debounce for the exact same code so 1 hold = +1,
                // and holding/presenting again after 0.85s increments N+1 (1 -> 2 -> 3...)!
                if (!bypassCooldown && this.continuous && code === this.lastScannedCode && (now - this.lastScannedAt) < 850) {
                    return;
                }

                this.lastScannedCode = code;
                this.lastRepeatableCode = code;
                this.lastScannedAt = now;
                this.sessionScanCount += 1;

                if (this.cooldownResetTimer) clearTimeout(this.cooldownResetTimer);
                this.cooldownResetTimer = setTimeout(() => {
                    this.lastScannedCode = '';
                }, 850);

                this.flashReticleSuccess();
                this.playBeep();
                const callback = this.onDetectCallback;

                if (this.continuous) {
                    let resultMeta = null;
                    if (typeof callback === 'function') {
                        resultMeta = callback(code);
                    }
                    if (resultMeta && resultMeta.closeScanner) {
                        this.close();
                        return;
                    }
                    this.updateContinuousFeedback(code, resultMeta);
                } else {
                    setTimeout(() => {
                        this.close();
                        if (typeof callback === 'function') {
                            callback(code);
                        }
                    }, 120);
                }
            }

            repeatLastScan() {
                if (!this.lastRepeatableCode) return;
                this.processDetectedCode(this.lastRepeatableCode, { bypassCooldown: true });
            }

            submitManualInput() {
                const input = document.getElementById('cameraScannerManualInput');
                if (!input) return;
                const val = String(input.value || '').trim();
                if (!val) return;
                input.value = '';
                this.processDetectedCode(val, { bypassCooldown: true });
                input.focus();
            }

            async startStream() {
                this.stopStream();

                try {
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        throw new Error('MediaDevices API not supported');
                    }

                    const constraints = {
                        audio: false,
                        video: {
                            facingMode: { ideal: this.facingMode },
                            width: { ideal: 1920, min: 640 },
                            height: { ideal: 1080, min: 480 },
                        }
                    };

                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                    } catch (firstErr) {
                        this.stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: true });
                    }

                    if (!this.video) {
                        this.stopStream();
                        return;
                    }

                    // Apply continuous autofocus / exposure / whitebalance + inspect zoom & torch capabilities
                    const track = this.stream.getVideoTracks?.()?.[0];
                    this.hardwareZoomSupported = false;
                    this.torchSupported = false;
                    this.torchOn = false;

                    if (track && typeof track.getCapabilities === 'function') {
                        try {
                            const caps = track.getCapabilities();
                            const adv = {};
                            if (Array.isArray(caps.focusMode) && caps.focusMode.includes('continuous')) {
                                adv.focusMode = 'continuous';
                            }
                            if (Array.isArray(caps.exposureMode) && caps.exposureMode.includes('continuous')) {
                                adv.exposureMode = 'continuous';
                            }
                            if (Array.isArray(caps.whiteBalanceMode) && caps.whiteBalanceMode.includes('continuous')) {
                                adv.whiteBalanceMode = 'continuous';
                            }
                            if (caps.zoom) {
                                this.hardwareZoomSupported = true;
                            }
                            if (caps.torch) {
                                this.torchSupported = true;
                            }
                            if (Object.keys(adv).length > 0 && typeof track.applyConstraints === 'function') {
                                await track.applyConstraints({ advanced: [adv] }).catch(() => {});
                            }
                        } catch (e) {}
                    }

                    const torchBtn = document.getElementById('cameraScannerTorchBtn');
                    if (torchBtn) {
                        torchBtn.classList.toggle('hidden', !this.torchSupported);
                        torchBtn.classList.toggle('inline-flex', this.torchSupported);
                    }

                    this.video.srcObject = this.stream;
                    await this.video.play();

                    // Apply initial zoom
                    const zoomRange = document.getElementById('cameraScannerZoomRange');
                    this.setZoom(zoomRange ? zoomRange.value : 1.2);
                    this.applyVideoFilterStyle();

                    if (this.loader) this.loader.classList.add('hidden');
                    this.video.classList.remove('hidden');
                    if (this.viewfinder) this.viewfinder.classList.remove('hidden');
                    if (this.formatBadge) {
                        this.formatBadge.classList.remove('hidden');
                        this.formatBadge.classList.add('inline-flex');
                    }

                    const switchBtn = document.getElementById('cameraScannerSwitchBtn');
                    if (switchBtn) {
                        switchBtn.classList.remove('hidden');
                        switchBtn.classList.add('inline-flex');
                    }

                    this.isScanning = true;
                    this.frameCounter = 0;
                    this.scanLoop();
                } catch (err) {
                    console.warn('Camera access error:', err);
                    this.showError(
                        'Gagal Mengakses Kamera',
                        'Izin kamera ditolak atau perangkat tidak memiliki kamera. Anda tetap dapat klik "Scan dari Gambar" di bawah.'
                    );
                }
            }

            setZoom(value) {
                const z = Math.max(1, Math.min(3, parseFloat(value) || 1));
                this.zoomLevel = z;
                const label = document.getElementById('cameraScannerZoomLabel');
                if (label) label.textContent = `${z.toFixed(1)}x`;

                const track = this.stream?.getVideoTracks?.()?.[0];
                if (this.hardwareZoomSupported && track && typeof track.applyConstraints === 'function') {
                    try {
                        const caps = track.getCapabilities();
                        const minZ = caps.zoom?.min ?? 1;
                        const maxZ = caps.zoom?.max ?? 3;
                        const targetZ = Math.min(maxZ, Math.max(minZ, z));
                        track.applyConstraints({ advanced: [{ zoom: targetZ }] }).catch(() => {});
                        if (this.video) this.video.style.transform = 'scale(1)';
                        return;
                    } catch (e) {}
                }

                if (this.video) {
                    this.video.style.transform = `scale(${z})`;
                }
            }

            toggleEnhanceMode() {
                this.enhanceMode = !this.enhanceMode;
                const btn = document.getElementById('cameraScannerEnhanceBtn');
                const lbl = document.getElementById('cameraScannerEnhanceLabel');
                if (lbl) lbl.textContent = this.enhanceMode ? 'Anti-Silau ON' : 'Anti-Silau OFF';
                if (btn) {
                    btn.className = this.enhanceMode
                        ? 'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 hover:bg-indigo-500/30 transition-colors cursor-pointer'
                        : 'inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-slate-800 text-slate-400 border border-slate-700 hover:bg-slate-700 transition-colors cursor-pointer';
                }
                this.applyVideoFilterStyle();
            }

            applyVideoFilterStyle() {
                if (!this.video) return;
                this.video.style.filter = this.enhanceMode
                    ? 'contrast(1.18) brightness(1.04) saturate(0.85)'
                    : 'none';
            }

            async toggleTorch() {
                const track = this.stream?.getVideoTracks?.()?.[0];
                if (!track || !this.torchSupported) return;
                this.torchOn = !this.torchOn;
                try {
                    await track.applyConstraints({ advanced: [{ torch: this.torchOn }] });
                } catch (e) {
                    this.torchOn = false;
                }
            }

            async switchCamera() {
                this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
                await this.startStream();
            }

            /**
             * Decode a single prepared canvas using both Native BarcodeDetector and ZXing (Hybrid + GlobalHistogram)
             */
            async decodeCanvasElement(targetCanvas) {
                if (!targetCanvas || targetCanvas.width < 16 || targetCanvas.height < 16) return null;

                // 1. Native BarcodeDetector
                if (this.detector) {
                    try {
                        const barcodes = await this.detector.detect(targetCanvas);
                        if (barcodes && barcodes.length > 0) {
                            const hit = barcodes.find(b => b.rawValue && String(b.rawValue).trim().length > 0);
                            if (hit) return String(hit.rawValue).trim();
                        }
                    } catch (e) {}
                }

                // 2. ZXing MultiFormatReader (TRY_HARDER + HybridBinarizer & GlobalHistogramBinarizer)
                if (this.zxingReader && window.ZXing) {
                    const Z = window.ZXing;
                    try {
                        const luminance = new Z.HTMLCanvasElementLuminanceSource(targetCanvas);
                        // Try HybridBinarizer first (handles uneven shadows/glare on packaging)
                        try {
                            const bitmapHybrid = new Z.BinaryBitmap(new Z.HybridBinarizer(luminance));
                            const res = this.zxingReader.decodeWithState(bitmapHybrid);
                            if (res && res.getText()) return String(res.getText()).trim();
                        } catch (e1) {}

                        // Try GlobalHistogramBinarizer second (handles soft focus / plastic curvature / low contrast)
                        if (Z.GlobalHistogramBinarizer) {
                            try {
                                const bitmapGlobal = new Z.BinaryBitmap(new Z.GlobalHistogramBinarizer(luminance));
                                const res2 = this.zxingReader.decodeWithState(bitmapGlobal);
                                if (res2 && res2.getText()) return String(res2.getText()).trim();
                            } catch (e2) {}
                        }
                    } catch (e) {}
                }

                return null;
            }

            /**
             * Render a transformed pass into this.workCanvas:
             * - cropRatio: 1.0 (full) or 0.68 (center ROI zoom)
             * - filterCss: CSS canvas filter for glare / contrast compensation
             * - angleDeg: rotation in degrees for tilted barcodes (-35, -18, 0, 18, 35, 90)
             * - stretchX: horizontal stretch factor (e.g. 1.24) to unwarp barcodes on curved bottles/sachets
             */
            preparePassCanvas(sourceEl, { cropRatio = 1.0, filterCss = 'none', angleDeg = 0, stretchX = 1.0 } = {}) {
                const srcW = sourceEl.videoWidth || sourceEl.naturalWidth || sourceEl.width || 0;
                const srcH = sourceEl.videoHeight || sourceEl.naturalHeight || sourceEl.height || 0;
                if (srcW <= 0 || srcH <= 0) return null;

                const effectiveCrop = Math.min(1.0, Math.max(0.35, cropRatio / Math.max(1, this.zoomLevel * 0.85)));
                const cropW = Math.round(srcW * effectiveCrop);
                const cropH = Math.round(srcH * effectiveCrop);
                const sx = Math.round((srcW - cropW) / 2);
                const sy = Math.round((srcH - cropH) / 2);

                const targetW = Math.min(960, Math.max(480, Math.round(cropW * stretchX)));
                const targetH = Math.min(720, Math.max(360, cropH));

                const c = this.workCanvas;
                c.width = targetW;
                c.height = targetH;
                const ctx = c.getContext('2d', { willReadFrequently: true });
                if (!ctx) return null;

                ctx.save();
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, targetW, targetH);

                if (filterCss && filterCss !== 'none' && 'filter' in ctx) {
                    ctx.filter = filterCss;
                }

                ctx.translate(targetW / 2, targetH / 2);
                if (angleDeg !== 0) {
                    ctx.rotate((angleDeg * Math.PI) / 180);
                }
                if (stretchX !== 1.0) {
                    ctx.scale(stretchX, 1.0);
                }
                ctx.drawImage(sourceEl, sx, sy, cropW, cropH, -targetW / 2, -targetH / 2, targetW, targetH);
                ctx.restore();

                return c;
            }

            /**
             * Multi-pass decoding:
             * - Live video mode runs 3 complementary passes per frame (cycling through tilt angles & curvature stretch)
             *   so each frame stays fast (~25ms) while covering glare, curved plastic, and tilted angles within 0.3s!
             * - Image upload mode runs all 10 passes exhaustively.
             */
            async decodeFromSource(sourceEl, exhaustive = false) {
                const allPasses = [
                    // 1. Center ROI with moderate anti-glare contrast
                    { cropRatio: 0.78, filterCss: 'grayscale(100%) contrast(145%) brightness(104%)', angleDeg: 0, stretchX: 1.0 },
                    // 2. Raw full frame
                    { cropRatio: 1.0, filterCss: 'none', angleDeg: 0, stretchX: 1.0 },
                    // 3. Curved plastic bottle/sachet unwarp (horizontal stretch 1.24x + high contrast)
                    { cropRatio: 0.72, filterCss: 'grayscale(100%) contrast(185%) brightness(106%)', angleDeg: 0, stretchX: 1.24 },
                    // 4. Tilted +18 deg with glare suppression
                    { cropRatio: 0.80, filterCss: 'grayscale(100%) contrast(165%)', angleDeg: 18, stretchX: 1.12 },
                    // 5. Tilted -18 deg with glare suppression
                    { cropRatio: 0.80, filterCss: 'grayscale(100%) contrast(165%)', angleDeg: -18, stretchX: 1.12 },
                    // 6. Strong glare / shiny plastic wrapper suppression (darkened highlights + strong contrast)
                    { cropRatio: 0.72, filterCss: 'grayscale(100%) contrast(230%) brightness(90%)', angleDeg: 0, stretchX: 1.15 },
                    // 7. Tilted +34 deg
                    { cropRatio: 0.85, filterCss: 'grayscale(100%) contrast(160%)', angleDeg: 34, stretchX: 1.0 },
                    // 8. Tilted -34 deg
                    { cropRatio: 0.85, filterCss: 'grayscale(100%) contrast(160%)', angleDeg: -34, stretchX: 1.0 },
                    // 9. Vertical barcode (90 deg)
                    { cropRatio: 0.85, filterCss: 'grayscale(100%) contrast(155%)', angleDeg: 90, stretchX: 1.0 },
                    // 10. Tight macro zoom for tiny sachet barcodes + strong curvature stretch
                    { cropRatio: 0.52, filterCss: 'grayscale(100%) contrast(195%) brightness(105%)', angleDeg: 0, stretchX: 1.32 },
                ];

                let passesToRun = allPasses;
                if (!exhaustive) {
                    const idx = this.frameCounter++;
                    // Always run Pass 1 (Center ROI + Anti-Glare), plus 2 rotating specialized passes per tick
                    const rotatingA = allPasses[1 + (idx % 4)];
                    const rotatingB = allPasses[5 + (idx % 5)];
                    passesToRun = [allPasses[0], rotatingA, rotatingB];
                }

                for (const pass of passesToRun) {
                    const prepared = this.preparePassCanvas(sourceEl, pass);
                    if (!prepared) continue;
                    const code = await this.decodeCanvasElement(prepared);
                    if (code) return code;
                }

                return null;
            }

            async scanLoop() {
                if (!this.isScanning || !this.video) return;

                if (this.video.readyState >= 2) {
                    try {
                        const code = await this.decodeFromSource(this.video, false);
                        if (code) {
                            this.processDetectedCode(code, { bypassCooldown: false });
                            if (!this.continuous) {
                                return;
                            }
                        }
                    } catch (e) {
                        // Continue scanning next frame
                    }
                }

                if (this.isScanning) {
                    this.scanTimer = setTimeout(() => {
                        this.scanFrameId = requestAnimationFrame(() => this.scanLoop());
                    }, 90);
                }
            }

            flashReticleSuccess() {
                const reticle = document.getElementById('cameraScannerReticle');
                if (reticle) {
                    reticle.classList.remove('border-indigo-400/85');
                    reticle.classList.add('border-emerald-400', 'scale-105');
                    if (this.continuous) {
                        setTimeout(() => {
                            reticle.classList.remove('border-emerald-400', 'scale-105');
                            reticle.classList.add('border-indigo-400/85');
                        }, 360);
                    }
                }
            }

            triggerFileSelect() {
                if (this.fileInput) {
                    this.fileInput.value = '';
                    this.fileInput.click();
                }
            }

            async handleFileSelect(event) {
                const file = event?.target?.files?.[0];
                if (!file) return;

                const hadLiveStream = Boolean(this.stream);
                if (!hadLiveStream) {
                    this.resetLoader('Memindai gambar...', 'Menganalisis kode dengan koreksi cahaya, kemiringan & lekukan.');
                }

                try {
                    await this.ensureDecoder();
                    const dataUrl = await new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = () => resolve(String(reader.result || ''));
                        reader.onerror = () => reject(new Error('Gagal membaca file gambar'));
                        reader.readAsDataURL(file);
                    });

                    const img = await new Promise((resolve, reject) => {
                        const image = new Image();
                        image.onload = () => resolve(image);
                        image.onerror = () => reject(new Error('Format gambar tidak valid'));
                        image.src = dataUrl;
                    });

                    const code = await this.decodeFromSource(img, true);
                    if (code) {
                        this.processDetectedCode(code, { bypassCooldown: true });
                        if (this.continuous && !this.stream && this.loader) {
                            this.loader.innerHTML = `
                                <div class="w-12 h-12 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-400 mb-3">
                                    <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                                </div>
                                <p class="text-xs font-bold text-white">Gambar Berhasil Discan (+1)</p>
                                <p class="text-[11px] text-slate-400 mt-1 max-w-xs">Pilih gambar lagi atau klik "+1 Lagi" di atas untuk menambah jumlah (N+1).</p>
                                <button type="button" onclick="window.CameraScanner && window.CameraScanner.triggerFileSelect()" class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition-colors cursor-pointer">
                                    <i data-lucide="image" class="w-3.5 h-3.5"></i>
                                    <span>Scan Gambar Berikutnya</span>
                                </button>
                            `;
                            if (window.lucide) window.lucide.createIcons();
                        }
                        return;
                    }

                    if (this.continuous) {
                        this.updateContinuousFeedback('-', {
                            found: false,
                            label: 'Kode Tidak Terbaca dari Gambar',
                            subtitle: 'Pastikan seluruh garis barcode / kotak QR terlihat jelas di foto',
                            badgeText: 'Gagal Baca',
                        });
                    }
                    if (!hadLiveStream) {
                        this.showError(
                            'Kode Tidak Terbaca dari Gambar',
                            'Pastikan seluruh garis barcode atau kotak QR terlihat di dalam foto, lalu pilih gambar lain.'
                        );
                    }
                } catch (err) {
                    if (!hadLiveStream) {
                        this.showError(
                            'Gagal Memindai Gambar',
                            err?.message || 'Tidak ditemukan barcode atau QR Code pada gambar tersebut.'
                        );
                    }
                }
            }

            stopStream() {
                this.isScanning = false;
                if (this.scanTimer) {
                    clearTimeout(this.scanTimer);
                    this.scanTimer = null;
                }
                if (this.scanFrameId) {
                    cancelAnimationFrame(this.scanFrameId);
                    this.scanFrameId = null;
                }
                if (this.cooldownResetTimer) {
                    clearTimeout(this.cooldownResetTimer);
                    this.cooldownResetTimer = null;
                }
                if (this.stream) {
                    this.stream.getTracks().forEach(track => track.stop());
                    this.stream = null;
                }
                if (this.video) {
                    this.video.srcObject = null;
                }
            }

            close() {
                this.stopStream();
                if (this.modal) {
                    this.modal.classList.add('hidden');
                }
                this.onDetectCallback = null;
            }

            showError(title, subtitle) {
                if (!this.loader) return;
                this.loader.classList.remove('hidden');
                if (this.video) this.video.classList.add('hidden');
                if (this.viewfinder) this.viewfinder.classList.add('hidden');

                this.loader.innerHTML = `
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 mb-3">
                        <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                    </div>
                    <p class="text-xs font-bold text-white">${title}</p>
                    <p class="text-[11px] text-slate-400 mt-1 max-w-xs leading-relaxed">${subtitle}</p>
                    <button type="button" onclick="window.CameraScanner && window.CameraScanner.triggerFileSelect()" class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition-colors cursor-pointer">
                        <i data-lucide="image" class="w-3.5 h-3.5"></i>
                        <span>Pilih Foto Barcode / QR</span>
                    </button>
                `;
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }

            playBeep() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(1046.5, ctx.currentTime);
                    gain.gain.setValueAtTime(0.08, ctx.currentTime);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.09);
                } catch (e) {}
            }
        }

        window.CameraScanner = window.CameraScanner || new CameraScannerManager();
    })();
</script>
