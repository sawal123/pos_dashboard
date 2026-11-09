{{-- Universal Camera Barcode & QR Scanner Modal Component --}}
@once
<div id="cameraScannerModal" class="fixed inset-0 z-[150] hidden" role="dialog" aria-modal="true" aria-labelledby="cameraScannerTitle">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" onclick="window.CameraScanner.close()"></div>

    {{-- Modal Content --}}
    <div class="fixed inset-0 z-10 overflow-y-auto flex items-center justify-center p-4">
        <div class="relative w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden transform transition-all">
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200/60 dark:border-indigo-800/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="scan-barcode" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 id="cameraScannerTitle" class="text-sm font-bold text-slate-900 dark:text-white">Scan Barcode / QR Code</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Arahkan kamera atau pilih gambar barcode/QR produk</p>
                    </div>
                </div>
                <button type="button" onclick="window.CameraScanner.close()" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" aria-label="Tutup kamera">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            {{-- Video Viewport --}}
            <div class="relative bg-slate-950 aspect-[4/3] w-full overflow-hidden flex items-center justify-center">
                <video id="cameraScannerVideo" class="w-full h-full object-cover hidden" playsinline muted autoplay></video>
                <input type="file" id="cameraScannerFileInput" accept="image/*" class="hidden" onchange="window.CameraScanner.handleFileSelect(event)" />

                {{-- Target Reticle / Viewfinder Overlay --}}
                <div id="cameraScannerViewfinder" class="absolute inset-0 pointer-events-none flex items-center justify-center hidden">
                    <div class="relative w-56 h-40 border-2 border-indigo-500/80 rounded-2xl shadow-[0_0_0_9999px_rgba(2,6,23,0.45)]">
                        {{-- Corner Accents --}}
                        <div class="absolute -top-0.5 -left-0.5 w-6 h-6 border-t-4 border-l-4 border-indigo-400 rounded-tl-xl"></div>
                        <div class="absolute -top-0.5 -right-0.5 w-6 h-6 border-t-4 border-r-4 border-indigo-400 rounded-tr-xl"></div>
                        <div class="absolute -bottom-0.5 -left-0.5 w-6 h-6 border-b-4 border-l-4 border-indigo-400 rounded-bl-xl"></div>
                        <div class="absolute -bottom-0.5 -right-0.5 w-6 h-6 border-b-4 border-r-4 border-indigo-400 rounded-br-xl"></div>

                        {{-- Animated Laser Line --}}
                        <div class="absolute inset-x-2 top-1/2 h-0.5 bg-gradient-to-r from-transparent via-rose-500 to-transparent shadow-[0_0_8px_#f43f5e] animate-pulse"></div>
                    </div>
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

            {{-- Footer Controls --}}
            <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-900/90 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="window.CameraScanner.triggerFileSelect()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/70 dark:border-indigo-800/70 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-colors cursor-pointer">
                        <i data-lucide="image" class="w-3.5 h-3.5"></i>
                        <span>Scan dari Gambar</span>
                    </button>
                    <span id="cameraScannerFormatBadge" class="hidden sm:inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                        Aktif
                    </span>
                </div>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" id="cameraScannerSwitchBtn" onclick="window.CameraScanner.switchCamera()" class="hidden items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 transition-colors">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                        <span>Putar Kamera</span>
                    </button>
                    <button type="button" onclick="window.CameraScanner.close()" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-slate-200/80 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300/70 dark:hover:bg-slate-700 transition-colors">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script data-navigate-once>
    /**
     * Universal Camera Scanner Manager (Supports 1D Barcode & 2D QR Code)
     * Uses Native BarcodeDetector API with automatic ZXing multi-format fallback + Image File Scanning
     */
    class CameraScannerManager {
        constructor() {
            this.stream = null;
            this.isScanning = false;
            this.onDetectCallback = null;
            this.detector = null;
            this.zxingReader = null;
            this.facingMode = 'environment';
            this.scanFrameId = null;
            this.scanTimer = null;
            this.canvas = document.createElement('canvas');
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
            if (this.detector) return 'native';

            if ('BarcodeDetector' in window) {
                try {
                    const formats = await window.BarcodeDetector.getSupportedFormats();
                    const desired = ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'qr_code', 'itf', 'codabar'];
                    const activeFormats = desired.filter(f => formats.includes(f));
                    this.detector = new window.BarcodeDetector({
                        formats: activeFormats.length > 0 ? activeFormats : undefined
                    });
                    return 'native';
                } catch (e) {
                    console.warn('BarcodeDetector init error:', e);
                }
            }

            if (this.zxingReader) return 'zxing';

            try {
                if (!window.ZXingBrowser && !window.ZXing) {
                    await new Promise((resolve, reject) => {
                        const existing = document.querySelector('script[data-zxing-lib]');
                        if (existing) {
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
                if (window.ZXing && window.ZXing.BrowserMultiFormatReader) {
                    this.zxingReader = new window.ZXing.BrowserMultiFormatReader();
                    return 'zxing';
                }
            } catch (err) {
                console.warn('ZXing fallback load failed:', err);
            }

            return null;
        }

        /**
         * Open the camera scanner modal
         * @param {Function} onDetect - Callback receiving (codeString)
         */
        async open(onDetect) {
            if (!this.modal) return;

            this.onDetectCallback = onDetect;
            this.modal.classList.remove('hidden');

            if (window.lucide) {
                window.lucide.createIcons();
            }

            this.resetLoader('Menyiapkan pemindai...', 'Mengaktifkan kamera untuk membaca barcode atau QR produk.');
            if (this.video) this.video.classList.add('hidden');
            if (this.viewfinder) this.viewfinder.classList.add('hidden');
            if (this.formatBadge) this.formatBadge.classList.add('hidden');

            const engine = await this.ensureDecoder();
            if (!engine) {
                this.showError(
                    'Kamera Langsung Tidak Tersedia',
                    'Gunakan tombol "Scan dari Gambar" di bawah untuk memindai foto/file QR atau ketik kode secara manual.'
                );
                return;
            }

            await this.startStream();
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
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };

                this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                if (!this.video) {
                    this.stopStream();
                    return;
                }
                this.video.srcObject = this.stream;
                await this.video.play();

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
                this.scanLoop();
            } catch (err) {
                console.warn('Camera access error:', err);
                this.showError(
                    'Gagal Mengakses Kamera',
                    'Izin kamera ditolak atau perangkat tidak memiliki kamera. Anda tetap dapat klik "Scan dari Gambar" di bawah.'
                );
            }
        }

        async switchCamera() {
            this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
            await this.startStream();
        }

        async decodeFromSource(sourceEl) {
            if (this.detector) {
                const barcodes = await this.detector.detect(sourceEl);
                if (barcodes && barcodes.length > 0) {
                    const hit = barcodes.find(b => b.rawValue && String(b.rawValue).trim().length > 0);
                    if (hit) return String(hit.rawValue).trim();
                }
            }

            if (window.ZXing) {
                const width = sourceEl.videoWidth || sourceEl.naturalWidth || sourceEl.width || 0;
                const height = sourceEl.videoHeight || sourceEl.naturalHeight || sourceEl.height || 0;
                if (width > 0 && height > 0) {
                    this.canvas.width = width;
                    this.canvas.height = height;
                    const ctx = this.canvas.getContext('2d', { willReadFrequently: true });
                    if (ctx) {
                        ctx.drawImage(sourceEl, 0, 0, width, height);
                        if (this.zxingReader && typeof this.zxingReader.decodeFromCanvas === 'function') {
                            const res = this.zxingReader.decodeFromCanvas(this.canvas);
                            if (res && res.getText()) return String(res.getText()).trim();
                        } else if (window.ZXing.HTMLCanvasElementLuminanceSource && window.ZXing.BinaryBitmap && window.ZXing.HybridBinarizer && window.ZXing.MultiFormatReader) {
                            const luminance = new window.ZXing.HTMLCanvasElementLuminanceSource(this.canvas);
                            const bitmap = new window.ZXing.BinaryBitmap(new window.ZXing.HybridBinarizer(luminance));
                            const reader = new window.ZXing.MultiFormatReader();
                            const res = reader.decode(bitmap);
                            if (res && res.getText()) return String(res.getText()).trim();
                        }
                    }
                }
            }

            return null;
        }

        async scanLoop() {
            if (!this.isScanning || !this.video) return;

            if (this.video.readyState >= 2) {
                try {
                    const code = await this.decodeFromSource(this.video);
                    if (code) {
                        this.playBeep();
                        const callback = this.onDetectCallback;
                        this.close();
                        if (typeof callback === 'function') {
                            callback(code);
                        }
                        return;
                    }
                } catch (e) {
                    // Normal when no barcode is in current frame
                }
            }

            if (this.isScanning) {
                this.scanTimer = setTimeout(() => {
                    this.scanFrameId = requestAnimationFrame(() => this.scanLoop());
                }, 120);
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

            this.resetLoader('Memindai gambar...', 'Membaca kode barcode / QR dari gambar yang dipilih.');
            if (this.video) this.video.classList.add('hidden');
            if (this.viewfinder) this.viewfinder.classList.add('hidden');

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

                const code = await this.decodeFromSource(img);
                if (code) {
                    this.playBeep();
                    const callback = this.onDetectCallback;
                    this.close();
                    if (typeof callback === 'function') {
                        callback(code);
                    }
                    return;
                }

                this.showError(
                    'Kode Tidak Terbaca dari Gambar',
                    'Pastikan gambar QR Code atau Barcode terlihat jelas dan tidak buram, lalu coba pilih gambar lain.'
                );
            } catch (err) {
                this.showError(
                    'Gagal Memproses Gambar',
                    'File gambar tidak dapat dipindai. Silakan coba gambar lain atau ketik kode secara manual.'
                );
            }
        }

        showError(title, message) {
            if (!this.loader) return;
            this.loader.classList.remove('hidden');
            if (this.video) this.video.classList.add('hidden');
            if (this.viewfinder) this.viewfinder.classList.add('hidden');
            this.loader.innerHTML = `
                <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 mb-3">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
                <p class="text-xs font-bold text-white">${title}</p>
                <p class="text-[11px] text-slate-400 mt-1.5 max-w-xs leading-relaxed">${message}</p>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                    <button type="button" onclick="window.CameraScanner.triggerFileSelect()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white transition-colors cursor-pointer">
                        <i data-lucide="image" class="w-3.5 h-3.5"></i>
                        <span>Pilih Gambar QR / Barcode</span>
                    </button>
                    <button type="button" onclick="window.CameraScanner.startStream()" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition-colors cursor-pointer">
                        <i data-lucide="camera" class="w-3.5 h-3.5"></i>
                        <span>Coba Kamera Lagi</span>
                    </button>
                </div>
            `;
            if (window.lucide) window.lucide.createIcons();
        }

        playBeep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1200, ctx.currentTime);
                gain.gain.setValueAtTime(0.12, ctx.currentTime);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.09);
            } catch (e) {}
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
    }

    window.CameraScanner = window.CameraScanner || new CameraScannerManager();
</script>
@endonce
