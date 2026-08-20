{{-- ==================== DELETE CONFIRMATION MODAL ==================== --}}
<div id="deleteModal" class="fixed inset-0 z-[80] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
    <div class="modal-backdrop absolute inset-0 bg-black/50 backdrop-blur-sm" data-modal-close></div>
    <div class="modal-panel relative bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xl w-full max-w-sm">
        <div class="p-6 text-center">
            <div class="mx-auto w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center mb-4">
                <i data-lucide="trash-2" class="w-5 h-5 text-red-600 dark:text-red-400"></i>
            </div>
            <h2 id="deleteModalTitle" class="text-lg font-semibold text-slate-900 dark:text-white">Delete Product?</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">This action cannot be undone. The product will be permanently removed.</p>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-center gap-2">
            <button class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" data-modal-close>Cancel</button>
            <button id="confirmDeleteBtn" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition-all">Delete Product</button>
        </div>
    </div>
</div>
