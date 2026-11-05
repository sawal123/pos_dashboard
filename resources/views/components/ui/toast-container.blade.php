{{-- ==================== TOAST CONTAINER ==================== --}}
<div id="toastContainer" class="fixed top-4 right-4 z-[170] space-y-3 w-[calc(100vw-2rem)] max-w-sm pointer-events-none"></div>

<script>
    (function () {
        if (typeof window.showToast !== 'function') {
            window.showToast = function (type, message) {
                const container = document.getElementById('toastContainer');
                if (!container) return;

                const toast = document.createElement('div');
                const icons = {
                    success: 'check-circle',
                    error: 'x-circle',
                    warning: 'alert-triangle',
                    info: 'info'
                };
                const colors = {
                    success: 'bg-emerald-50 dark:bg-emerald-950/80 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200',
                    error: 'bg-rose-50 dark:bg-rose-950/80 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200',
                    warning: 'bg-amber-50 dark:bg-amber-950/80 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200',
                    info: 'bg-blue-50 dark:bg-blue-950/80 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200'
                };
                const iconColors = {
                    success: 'text-emerald-600 dark:text-emerald-400',
                    error: 'text-rose-600 dark:text-rose-400',
                    warning: 'text-amber-600 dark:text-amber-400',
                    info: 'text-blue-600 dark:text-blue-400'
                };

                const iconName = icons[type] || 'info';
                const colorClasses = colors[type] || colors.info;
                const iconColorClass = iconColors[type] || iconColors.info;

                toast.className = `pointer-events-auto flex items-start gap-3 p-3.5 rounded-2xl border shadow-xl shadow-slate-900/10 ${colorClasses} transition-all duration-300`;
                toast.innerHTML = `
                    <i data-lucide="${iconName}" class="w-4 h-4 mt-0.5 shrink-0 ${iconColorClass}"></i>
                    <p class="text-xs font-semibold flex-1 leading-snug">${message}</p>
                    <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors shrink-0" aria-label="Tutup">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </button>
                `;

                container.appendChild(toast);

                if (typeof lucide !== 'undefined') {
                    try { lucide.createIcons(); } catch (e) {}
                } else if (typeof window.initIcons === 'function') {
                    try { window.initIcons(); } catch (e) {}
                }

                const dismissBtn = toast.querySelector('button');
                if (dismissBtn) {
                    dismissBtn.addEventListener('click', () => {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateY(-10px)';
                        setTimeout(() => toast.remove(), 250);
                    });
                }

                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(-10px)';
                    setTimeout(() => toast.remove(), 250);
                }, 4000);
            };
        }
    })();
</script>
