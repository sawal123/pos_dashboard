<div
    id="globalConfirmationModal"
    class="fixed inset-0 z-[160] hidden items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="globalConfirmationTitle"
    aria-describedby="globalConfirmationMessage"
>
    <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" data-confirmation-cancel></div>

    <div class="modal-panel relative w-full max-w-md overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-2xl shadow-slate-950/30 dark:border-slate-700 dark:bg-slate-900">
        <div class="absolute -right-16 -top-20 h-48 w-48 rounded-full bg-amber-500/10 blur-3xl"></div>

        <div class="relative p-6 sm:p-7">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400">
                <i data-lucide="triangle-alert" class="h-6 w-6"></i>
            </div>

            <h2 id="globalConfirmationTitle" class="mt-5 text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                Konfirmasi tindakan
            </h2>
            <p id="globalConfirmationMessage" class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-slate-300"></p>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    data-confirmation-cancel
                    class="inline-flex h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                >
                    Batal
                </button>
                <button
                    type="button"
                    id="globalConfirmationConfirm"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 text-sm font-semibold text-white shadow-sm shadow-rose-600/20 transition-colors hover:bg-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                >
                    <i data-lucide="check" class="h-4 w-4"></i>
                    Lanjutkan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        let pendingForm = null;
        let pendingSubmitter = null;

        function getModal() {
            return document.getElementById('globalConfirmationModal');
        }

        function openConfirmationModal(form) {
            const modal = getModal();
            if (!modal) return;

            const message = form.getAttribute('data-confirm-message') || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
            const messageEl = document.getElementById('globalConfirmationMessage');
            if (messageEl) messageEl.textContent = message;

            pendingForm = form;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';

            if (typeof window.initIcons === 'function') {
                window.initIcons();
            }

            window.setTimeout(() => {
                document.getElementById('globalConfirmationConfirm')?.focus();
            }, 0);
        }

        function closeConfirmationModal() {
            const modal = getModal();
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
            pendingForm = null;
            pendingSubmitter = null;
        }

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('form[data-confirm-message]');
            if (!form) return;

            const conditionalSelector = form.getAttribute('data-confirm-if-unchecked');
            if (conditionalSelector) {
                const checkbox = document.querySelector(conditionalSelector);
                if (checkbox?.checked) return;
            }

            event.preventDefault();
            pendingSubmitter = event.submitter || null;
            openConfirmationModal(form);
        }, true);

        document.addEventListener('click', (event) => {
            const cancelButton = event.target.closest('[data-confirmation-cancel]');
            if (cancelButton) {
                event.preventDefault();
                closeConfirmationModal();
                return;
            }

            if (event.target.closest('#globalConfirmationConfirm')) {
                event.preventDefault();

                const form = pendingForm;
                const submitter = pendingSubmitter;
                if (!form) {
                    closeConfirmationModal();
                    return;
                }

                form.removeAttribute('data-confirm-message');
                form.removeAttribute('data-confirm-if-unchecked');
                closeConfirmationModal();

                if (typeof form.requestSubmit === 'function') {
                    submitter ? form.requestSubmit(submitter) : form.requestSubmit();
                } else {
                    form.submit();
                }
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && pendingForm) {
                closeConfirmationModal();
            }
        });
    })();
</script>
