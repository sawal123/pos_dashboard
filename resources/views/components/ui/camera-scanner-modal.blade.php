{{-- ==================== REUSABLE CAMERA BARCODE / QR SCANNER MODAL ==================== --}}
<div
    id="cameraScannerModal"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="cameraScannerTitle"
>
    {{-- Backdrop --}}
    <div
        id="cameraScannerBackdrop"
        class="fixed inset-0 bg-slate-900/70 dark:bg-slate-950/85 backdrop-blur-xs transition-opacity duration-300"
        onclick="window.CameraScanner?.close()"
    ></div>

    {{-- Modal Dialog --}}
    <div class="fixed inset-0 z-10 overflow-y-auto flex items-center justify-center p-3 sm:p-6">
        <div
            class="relative w-full max-w-sm bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden p-5 transform transition-all animate-in fade-in zoom-in-95 duration-200"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i data-lucide="scan" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h2 id="cameraScannerTitle" class="text-sm font-bold text-slate-900 dark:text-white">
                            Pindai Barcode / QR
                        </h2>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500">Kamera atau input manual</p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        id="cameraScannerSwitchBtn"
                        onclick="window.CameraScanner?.switchCamera()"
                        class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors"
                        title="Ganti Kamera"
                    >
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                    </button>
                    <button
                        type="button"
                        onclick="window.CameraScanner?.close()"
                        class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                        aria-label="Tutup"
                    >
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            {{-- Viewfinder Container --}}
            <div class="relative w-full aspect-square max-w-[280px] mx-auto rounded-2xl overflow-hidden bg-slate-950 flex items-center justify-center border border-slate-800 shadow-inner">
                <video
                    id="cameraScannerVideo"
                    playsinline
                    autoplay
                    muted
                    class="w-full h-full object-cover"
                ></video>

                {{-- Targeting Reticle Overlay --}}
                <div class="absolute inset-6 border-2 border-indigo-500/80 rounded-xl pointer-events-none flex flex-col justify-between p-2 shadow-[0_0_0_9999px_rgba(0,0,0,0.55)]">
                    <div class="flex justify-between">
                        <span class="w-3 h-3 border-t-2 border-l-2 border-white -mt-1 -ml-1"></span>
                        <span class="w-3 h-3 border-t-2 border-r-2 border-white -mt-1 -mr-1"></span>
                    </div>
                    {{-- Laser Scan Line --}}
                    <div class="w-full h-0.5 bg-indigo-400 shadow-[0_0_10px_#818cf8] animate-pulse"></div>
                    <div class="flex justify-between">
                        <span class="w-3 h-3 border-b-2 border-l-2 border-white -mb-1 -ml-1"></span>
                        <span class="w-3 h-3 border-b-2 border-r-2 border-white -mb-1 -mr-1"></span>
                    </div>
                </div>

                {{-- Loading Indicator / Error Message on Top of Video --}}
                <div id="cameraScannerLoader" class="absolute inset-0 bg-slate-950/80 flex flex-col items-center justify-center p-4 text-center">
                    <i data-lucide="loader-2" class="w-6 h-6 text-indigo-400 animate-spin mb-2"></i>
                    <p class="text-xs text-slate-300 font-medium">Mempersiapkan kamera...</p>
                </div>
            </div>

            {{-- Status Guide Text --}}
            <p id="cameraScannerStatus" class="text-[11px] text-center text-slate-500 dark:text-slate-400 mt-2.5 px-2">
                Arahkan kamera ke Barcode (EAN/UPC/Code128) atau QR Code produk
            </p>

            {{-- Fallback Manual Input & Keystroke Input (Barcode Gun) --}}
            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <form id="cameraScannerManualForm" onsubmit="event.preventDefault(); window.CameraScanner?.submitManual();" class="flex items-center gap-1.5">
                    <div class="relative flex-1">
                        <input
                            type="text"
                            id="cameraScannerManualInput"
                            placeholder="Atau ketik barcode..."
                            autocomplete="off"
                            class="w-full pl-3 pr-2 py-2 text-xs font-mono rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <button
                        type="submit"
                        class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shrink-0 transition-colors"
                    >
                        Pilih
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    class CameraScannerManager {
        constructor() {
            this.modal = null;
            this.video = null;
            this.loader = null;
            this.statusEl = null;
            this.manualInput = null;
            this.stream = null;
            this.detector = null;
            this.scanTimer = null;
            this.callback = null;
            this.facingMode = 'environment';
            this.isDetecting = false;
            this.isClosed = true;

            this.init();
        }

        init() {
            this.modal = document.getElementById('cameraScannerModal');
            this.video = document.getElementById('cameraScannerVideo');
            this.loader = document.getElementById('cameraScannerLoader');
            this.statusEl = document.getElementById('cameraScannerStatus');
            this.manualInput = document.getElementById('cameraScannerManualInput');

            // BarcodeDetector support
            if ('BarcodeDetector' in window) {
                try {
                    this.detector = new BarcodeDetector({
                        formats: ['qr_code', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39']
                    });
                } catch (e) {
                    try {
                        this.detector = new BarcodeDetector();
                    } catch (err) {
                        this.detector = null;
                    }
                }
            }

            // Safety listeners: shut down camera immediately on tab hidden or unload
            document.addEventListener('visibilitychange', () => {
                if (document.hidden && !this.isClosed) {
                    this.close();
                }
            });

            window.addEventListener('beforeunload', () => {
                this.close();
            });

            window.addEventListener('pagehide', () => {
                this.close();
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !this.isClosed) {
                    this.close();
                }
            });
        }

        open(callback) {
            this.callback = callback;
            this.isClosed = false;

            if (this.modal) {
                this.modal.classList.remove('hidden');
            }
            if (this.manualInput) {
                this.manualInput.value = '';
                setTimeout(() => this.manualInput.focus(), 300);
            }
            if (this.loader) {
                this.loader.classList.remove('hidden');
            }
            if (this.statusEl) {
                this.statusEl.textContent = 'Mempersiapkan kamera...';
                this.statusEl.className = 'text-[11px] text-center text-slate-500 dark:text-slate-400 mt-2.5 px-2';
            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            this.startStream();
        }

        close() {
            this.isClosed = true;
            this.stopStream();

            if (this.modal) {
                this.modal.classList.add('hidden');
            }
        }

        async startStream() {
            this.stopStream();

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.showError('Browser atau koneksi tidak mendukung akses kamera (membutuhkan HTTPS).');
                return;
            }

            try {
                const constraints = {
                    video: {
                        facingMode: { ideal: this.facingMode },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                };

                const stream = await navigator.mediaDevices.getUserMedia(constraints);
                if (this.isClosed) {
                    // Closed while permission dialog was active
                    stream.getTracks().forEach(t => t.stop());
                    return;
                }

                this.stream = stream;
                if (this.video) {
                    this.video.srcObject = stream;
                    await this.video.play().catch(() => {});
                }

                if (this.loader) {
                    this.loader.classList.add('hidden');
                }

                if (this.statusEl) {
                    if (this.detector) {
                        this.statusEl.textContent = 'Arahkan kamera ke Barcode atau QR Code produk';
                    } else {
                        this.statusEl.textContent = 'Deteksi otomatis tidak didukung browser ini. Gunakan input kode di bawah.';
                    }
                }

                this.startDetectionLoop();
            } catch (err) {
                console.warn('Camera error:', err);
                let message = 'Tidak dapat mengakses kamera.';
                if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                    message = 'Izin kamera ditolak. Silakan izinkan akses kamera di pengaturan browser.';
                } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                    message = 'Perangkat kamera tidak ditemukan.';
                }
                this.showError(message);
            }
        }

        stopStream() {
            if (this.scanTimer) {
                clearInterval(this.scanTimer);
                this.scanTimer = null;
            }
            this.isDetecting = false;

            if (this.stream) {
                this.stream.getTracks().forEach(track => {
                    try {
                        track.stop();
                    } catch (e) {}
                });
                this.stream = null;
            }

            if (this.video) {
                this.video.srcObject = null;
            }
        }

        switchCamera() {
            this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
            if (this.loader) {
                this.loader.classList.remove('hidden');
            }
            this.startStream();
        }

        showError(message) {
            if (this.loader) {
                this.loader.classList.remove('hidden');
                this.loader.innerHTML = `
                    <div class="text-rose-400 text-xs px-3 leading-relaxed">
                        <i data-lucide="alert-triangle" class="w-6 h-6 mx-auto mb-1 text-rose-500"></i>
                        <p class="font-semibold">${message}</p>
                    </div>
                `;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
            if (this.statusEl) {
                this.statusEl.textContent = 'Gunakan form input kode di bawah.';
                this.statusEl.className = 'text-[11px] text-center text-amber-500 mt-2.5 px-2';
            }
            if (this.manualInput) {
                this.manualInput.focus();
            }
        }

        startDetectionLoop() {
            if (!this.detector || !this.video) return;

            this.isDetecting = true;
            this.scanTimer = setInterval(async () => {
                if (this.isClosed || !this.isDetecting || !this.video) return;

                if (this.video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
                    try {
                        const barcodes = await this.detector.detect(this.video);
                        if (barcodes && barcodes.length > 0) {
                            const rawValue = barcodes[0].rawValue;
                            if (rawValue && rawValue.trim()) {
                                this.handleSuccess(rawValue.trim());
                            }
                        }
                    } catch (err) {
                        // ignore frame decode hiccups
                    }
                }
            }, 180);
        }

        handleSuccess(code) {
            this.close();
            if (typeof this.callback === 'function') {
                this.callback(code);
            }
        }

        submitManual() {
            const val = this.manualInput?.value?.trim();
            if (!val) return;
            this.handleSuccess(val);
        }
    }

    window.CameraScanner = new CameraScannerManager();
})();
</script>

