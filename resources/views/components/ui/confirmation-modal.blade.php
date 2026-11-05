<div
    id="globalConfirmationModal"
    class="fixed inset-0 z-[160] hidden items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="globalConfirmationTitle"
    aria-describedby="globalConfirmationMessage"
>
    <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity" data-confirmation-cancel></div>

    <div class="modal-panel relative w-full max-w-md overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-2xl shadow-slate-950/30 transition-all dark:border-slate-800 dark:bg-slate-900">
        <div id="globalConfirmationGlow" class="absolute -right-16 -top-20 h-48 w-48 rounded-full bg-rose-500/10 blur-3xl transition-colors"></div>

        <div class="relative p-6 sm:p-7">
            <div id="globalConfirmationIconContainer" class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-100 text-rose-600 transition-colors dark:bg-rose-950/60 dark:text-rose-400">
                <i id="globalConfirmationIcon" data-lucide="triangle-alert" class="h-6 w-6"></i>
            </div>

            <h2 id="globalConfirmationTitle" class="mt-5 text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                Konfirmasi tindakan
            </h2>
            <p id="globalConfirmationMessage" class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-slate-300"></p>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    data-confirmation-cancel
                    id="globalConfirmationCancel"
                    class="inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="button"
                    id="globalConfirmationConfirm"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 text-sm font-semibold text-white shadow-sm shadow-rose-600/20 transition-all hover:bg-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 cursor-pointer active:scale-95"
                >
                    <i id="globalConfirmationConfirmIcon" data-lucide="check" class="h-4 w-4"></i>
                    <span id="globalConfirmationConfirmText">Lanjutkan</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        let pendingForm = null;
        let pendingSubmitter = null;
        let activePromiseResolve = null;
        let activeOnConfirm = null;
        let activeOnCancel = null;

        const VARIANTS = {
            danger: {
                glowClass: 'bg-rose-500/10',
                iconContainerClass: 'bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400',
                defaultIcon: 'triangle-alert',
                btnClass: 'bg-rose-600 hover:bg-rose-700 text-white shadow-rose-600/20 focus-visible:ring-rose-500'
            },
            warning: {
                glowClass: 'bg-amber-500/10',
                iconContainerClass: 'bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400',
                defaultIcon: 'triangle-alert',
                btnClass: 'bg-amber-600 hover:bg-amber-700 text-white shadow-amber-600/20 focus-visible:ring-amber-500'
            },
            primary: {
                glowClass: 'bg-indigo-500/10',
                iconContainerClass: 'bg-indigo-100 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400',
                defaultIcon: 'help-circle',
                btnClass: 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-600/20 focus-visible:ring-indigo-500'
            }
        };

        function getModal() {
            return document.getElementById('globalConfirmationModal');
        }

        function renderIcon(iconName) {
            const iconContainer = document.getElementById('globalConfirmationIconContainer');
            if (!iconContainer) return;

            iconContainer.innerHTML = `<i id="globalConfirmationIcon" data-lucide="${iconName || 'triangle-alert'}" class="h-6 w-6"></i>`;
            if (typeof lucide !== 'undefined') {
                try { lucide.createIcons(); } catch (e) {}
            } else if (typeof window.initIcons === 'function') {
                try { window.initIcons(); } catch (e) {}
            }
        }

        function openModalWithOptions(opts) {
            const modal = getModal();
            if (!modal) return;

            const variantKey = (opts.variant && VARIANTS[opts.variant]) ? opts.variant : 'danger';
            const variantConfig = VARIANTS[variantKey];

            // Title & message
            const titleEl = document.getElementById('globalConfirmationTitle');
            const messageEl = document.getElementById('globalConfirmationMessage');
            if (titleEl) titleEl.textContent = opts.title || 'Konfirmasi tindakan';
            if (messageEl) messageEl.textContent = opts.message || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';

            // Button texts
            const confirmTextEl = document.getElementById('globalConfirmationConfirmText');
            const cancelBtn = document.getElementById('globalConfirmationCancel');
            if (confirmTextEl) confirmTextEl.textContent = opts.confirmText || 'Lanjutkan';
            if (cancelBtn) cancelBtn.textContent = opts.cancelText || 'Batal';

            // Glow & Icon container styles
            const glowEl = document.getElementById('globalConfirmationGlow');
            if (glowEl) {
                glowEl.className = `absolute -right-16 -top-20 h-48 w-48 rounded-full blur-3xl transition-colors ${variantConfig.glowClass}`;
            }

            const iconContainer = document.getElementById('globalConfirmationIconContainer');
            if (iconContainer) {
                iconContainer.className = `flex h-12 w-12 items-center justify-center rounded-2xl transition-colors ${variantConfig.iconContainerClass}`;
            }

            // Confirm button classes
            const confirmBtn = document.getElementById('globalConfirmationConfirm');
            if (confirmBtn) {
                confirmBtn.className = `inline-flex h-10 items-center justify-center gap-2 rounded-xl px-4 text-sm font-semibold shadow-sm transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900 cursor-pointer active:scale-95 ${variantConfig.btnClass}`;
            }

            renderIcon(opts.icon || variantConfig.defaultIcon);

            // Stored state
            pendingForm = opts.form || null;
            pendingSubmitter = opts.submitter || null;
            activePromiseResolve = opts.resolve || null;
            activeOnConfirm = opts.onConfirm || null;
            activeOnCancel = opts.onCancel || null;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';

            window.setTimeout(() => {
                confirmBtn?.focus();
            }, 50);
        }

        function closeModal(isConfirmed) {
            const modal = getModal();
            if (!modal || modal.classList.contains('hidden')) return;

            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';

            const form = pendingForm;
            const submitter = pendingSubmitter;
            const resolve = activePromiseResolve;
            const onConfirm = activeOnConfirm;
            const onCancel = activeOnCancel;

            pendingForm = null;
            pendingSubmitter = null;
            activePromiseResolve = null;
            activeOnConfirm = null;
            activeOnCancel = null;

            if (isConfirmed) {
                if (form) {
                    form.removeAttribute('data-confirm-message');
                    form.removeAttribute('data-confirm-if-unchecked');
                    if (typeof form.requestSubmit === 'function') {
                        submitter ? form.requestSubmit(submitter) : form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }
                if (typeof onConfirm === 'function') onConfirm();
                if (typeof resolve === 'function') resolve(true);
            } else {
                if (typeof onCancel === 'function') onCancel();
                if (typeof resolve === 'function') resolve(false);
            }
        }

        // Global Programmatic Helper (Promise-based & Callback-based)
        window.confirmAction = function (options) {
            if (typeof options === 'string') {
                options = { message: options };
            } else if (!options || typeof options !== 'object') {
                options = {};
            }

            return new Promise((resolve) => {
                openModalWithOptions({
                    title: options.title || 'Konfirmasi tindakan',
                    message: options.message || 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
                    confirmText: options.confirmText || 'Lanjutkan',
                    cancelText: options.cancelText || 'Batal',
                    variant: options.variant || 'danger',
                    icon: options.icon || null,
                    onConfirm: options.onConfirm,
                    onCancel: options.onCancel,
                    resolve: resolve
                });
            });
        };

        window.confirmModal = window.confirmAction;
        window.showConfirmModal = window.confirmAction;

        // Form Submit Interception for [data-confirm-message]
        document.addEventListener('submit', (event) => {
            const form = event.target.closest('form[data-confirm-message]');
            if (!form) return;

            const conditionalSelector = form.getAttribute('data-confirm-if-unchecked');
            if (conditionalSelector) {
                const checkbox = document.querySelector(conditionalSelector);
                if (checkbox?.checked) return;
            }

            event.preventDefault();
            const submitter = event.submitter || null;

            openModalWithOptions({
                title: form.getAttribute('data-confirm-title') || 'Konfirmasi tindakan',
                message: form.getAttribute('data-confirm-message') || 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
                confirmText: form.getAttribute('data-confirm-button') || 'Lanjutkan',
                cancelText: form.getAttribute('data-confirm-cancel-text') || 'Batal',
                variant: form.getAttribute('data-confirm-variant') || 'danger',
                icon: form.getAttribute('data-confirm-icon') || null,
                form: form,
                submitter: submitter
            });
        }, true);

        // Click Handler for Cancel & Confirm buttons
        document.addEventListener('click', (event) => {
            const cancelButton = event.target.closest('[data-confirmation-cancel]');
            if (cancelButton) {
                event.preventDefault();
                closeModal(false);
                return;
            }

            if (event.target.closest('#globalConfirmationConfirm')) {
                event.preventDefault();
                closeModal(true);
            }
        });

        // Keydown Handler for Escape Key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                const modal = getModal();
                if (modal && !modal.classList.contains('hidden')) {
                    closeModal(false);
                }
            }
        });
    })();
</script>
