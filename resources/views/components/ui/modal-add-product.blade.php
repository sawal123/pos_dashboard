{{-- ==================== ADD PRODUCT MODAL ==================== --}}
<div id="addProductModal" class="fixed inset-0 z-[80] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="addProductTitle">
    <div class="modal-backdrop absolute inset-0 bg-black/50 backdrop-blur-sm" data-modal-close></div>
    <div class="modal-panel relative bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">

        {{-- Modal Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 sticky top-0 bg-white dark:bg-slate-800 z-10">
            <h2 id="addProductTitle" class="text-lg font-semibold text-slate-900 dark:text-white">Add Product</h2>
            <button class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 transition-colors" data-modal-close aria-label="Close modal">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Modal Body --}}
        <div class="p-6 space-y-4">
            {{-- Product Name --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Product Name</label>
                <input type="text" placeholder="e.g. Espresso Single" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm text-slate-800 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-400 transition-all">
            </div>

            {{-- Category --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Category</label>
                <div class="relative" id="modalCategorySelectWrapper">
                    <button id="modalCategorySelectBtn" class="w-full flex items-center justify-between px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600/70 transition-colors" aria-haspopup="listbox" aria-expanded="false">
                        <span id="modalCategoryLabel">Beverages</span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                    </button>
                    <div id="modalCategoryDropdown" class="dropdown-panel dropdown-hidden absolute left-0 right-0 mt-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl shadow-lg overflow-hidden z-50 select-dropdown">
                        <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="All Categories">All Categories</button>
                        <button class="select-option selected w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="Beverages">Beverages</button>
                        <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="Food">Food</button>
                        <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="Snacks">Snacks</button>
                        <button class="select-option w-full text-left px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200" data-value="Services">Services</button>
                    </div>
                </div>
            </div>

            {{-- Selling Price --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Selling Price</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500 dark:text-slate-400">Rp</span>
                    <input type="text" placeholder="0" class="w-full pl-10 pr-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm text-slate-800 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-400 transition-all">
                </div>
            </div>

            {{-- SKU --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">SKU</label>
                <input type="text" placeholder="e.g. SKU-001" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-sm text-slate-800 dark:text-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-400 transition-all">
            </div>

            {{-- Status Toggle --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Status</label>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-slate-700 dark:text-slate-300">Active</span>
                    <div class="toggle-switch active" id="modalStatusToggle" role="switch" aria-checked="true" tabindex="0"></div>
                </div>
            </div>

            {{-- Upload Product Image --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Upload Product Image</label>
                <div class="border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-4 text-center hover:border-indigo-400 dark:hover:border-indigo-500 transition-all cursor-pointer bg-slate-50 dark:bg-slate-700/30" id="modalDropZone">
                    <i data-lucide="image" class="w-6 h-6 text-slate-400 dark:text-slate-500 mx-auto mb-1"></i>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Drop image or click to upload</p>
                    <input type="file" id="modalFileInput" class="hidden" accept="image/jpeg,image/png,image/webp" aria-label="Upload product image">
                </div>
                <div id="modalImagePreviewContainer" class="hidden mt-2 flex items-center gap-3">
                    <img id="modalImagePreview" src="" alt="Preview" class="w-14 h-14 object-cover rounded-lg border border-slate-200 dark:border-slate-600">
                    <div class="flex-1"><p class="text-xs font-medium text-slate-700 dark:text-slate-200" id="modalFileName">image.jpg</p></div>
                    <button id="modalRemoveImageBtn" class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors" aria-label="Remove image"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                </div>
            </div>
        </div>

        {{-- Modal Footer --}}
        <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-2 sticky bottom-0 bg-white dark:bg-slate-800">
            <button class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" data-modal-close>Cancel</button>
            <button id="saveProductBtn" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-all flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Save Product</button>
        </div>
    </div>
</div>
