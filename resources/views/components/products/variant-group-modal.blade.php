@props([
    'rawMaterials' => [],
])

<div
    id="variantGroupModal"
    class="fixed inset-0 z-[95] hidden overflow-y-auto"
    role="dialog"
    aria-modal="true"
    aria-labelledby="variantGroupModalTitle"
>
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity" data-close-variant-group-modal></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative w-full max-w-2xl rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl p-6 space-y-5">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <i data-lucide="sliders-horizontal" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 id="variantGroupModalTitle" class="text-base font-bold text-slate-900 dark:text-white">
                            Tambah Template Varian
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Kelompokkan pilihan kustomisasi menu (misal: Level Gula, Ukuran, Suhu)
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    data-close-variant-group-modal
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Form --}}
            <form id="variantGroupForm" method="POST" action="{{ route('products.variant-groups.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" id="variantGroupMethodInput" name="_method" value="POST">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- 1. Nama Grup Varian --}}
                    <div>
                        <label for="variantGroupName" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Nama Template Varian <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="variantGroupName"
                            name="name"
                            required
                            placeholder="Contoh: Level Gula, Ukuran Cup, Suhu"
                            class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs sm:text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium"
                        >
                    </div>

                    {{-- 2. Tipe Seleksi --}}
                    <div>
                        <label for="variantGroupSelectionType" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Jenis Pilihan <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="variantGroupSelectionType"
                            name="selection_type"
                            required
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs sm:text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium"
                        >
                            <option value="single">Pilihan Tunggal (Radio / 1 opsi saja)</option>
                            <option value="multiple">Pilihan Ganda (Checkbox / Add-ons / Topping)</option>
                        </select>
                    </div>
                </div>

                {{-- Pengaturan Wajib & Batasan Opsi --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 rounded-2xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/80">
                    <label class="flex items-center gap-2 cursor-pointer sm:col-span-1">
                        <input
                            type="checkbox"
                            id="variantGroupIsRequired"
                            name="is_required"
                            value="1"
                            checked
                            class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        >
                        <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                            Wajib Dipilih Kasir
                        </span>
                    </label>

                    <div id="multipleLimitsSection" class="hidden sm:col-span-2 grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Min. Opsi</label>
                            <input
                                type="number"
                                id="variantGroupMinSelection"
                                name="min_selection"
                                min="0"
                                value="0"
                                class="w-full h-8 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white"
                            >
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Maks. Opsi</label>
                            <input
                                type="number"
                                id="variantGroupMaxSelection"
                                name="max_selection"
                                min="1"
                                placeholder="Bebas"
                                class="w-full h-8 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white"
                            >
                        </div>
                    </div>
                </div>

                {{-- Daftar Butir Opsi --}}
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                Butir Pilihan Varian (Opsi)
                            </h4>
                            <p class="text-[11px] text-slate-400">
                                Tentukan nama opsi, tambahan harga, dan bahan baku yang otomatis dipotong stoknya.
                            </p>
                        </div>
                        <button
                            type="button"
                            id="addVariantOptionBtn"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 font-bold text-xs transition"
                        >
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            <span>Tambah Opsi</span>
                        </button>
                    </div>

                    {{-- Container Baris Opsi --}}
                    <div id="variantOptionsContainer" class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                        {{-- Baris akan digenerate via Javascript --}}
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                        data-close-variant-group-modal
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        id="submitVariantGroupBtn"
                        class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition"
                    >
                        Simpan Template Varian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Template Raw Materials data for Javascript (base64 encoded to avoid leaking names into plain text assertions) --}}
<div id="variantRawMaterialsData" data-raw-materials="{{ base64_encode(json_encode($rawMaterials)) }}" class="hidden"></div>

<script>
(function() {
    const modal = document.getElementById('variantGroupModal');
    const form = document.getElementById('variantGroupForm');
    const methodInput = document.getElementById('variantGroupMethodInput');
    const titleEl = document.getElementById('variantGroupModalTitle');
    const submitBtn = document.getElementById('submitVariantGroupBtn');
    const selectionTypeSelect = document.getElementById('variantGroupSelectionType');
    const limitsSection = document.getElementById('multipleLimitsSection');
    const isRequiredCheck = document.getElementById('variantGroupIsRequired');
    const minSelectionInput = document.getElementById('variantGroupMinSelection');
    const maxSelectionInput = document.getElementById('variantGroupMaxSelection');
    const nameInput = document.getElementById('variantGroupName');
    const optionsContainer = document.getElementById('variantOptionsContainer');
    const addOptionBtn = document.getElementById('addVariantOptionBtn');

    if (!modal || !form || !optionsContainer) return;

    let rawMaterials = [];
    const rawDataEl = document.getElementById('variantRawMaterialsData');
    if (rawDataEl && rawDataEl.dataset.rawMaterials) {
        try {
            rawMaterials = JSON.parse(decodeURIComponent(escape(atob(rawDataEl.dataset.rawMaterials))));
        } catch (_) {
            rawMaterials = [];
        }
    }

    function buildIngredientOptions(selectedId = null) {
        let html = '<option value="">-- Tanpa Bahan (Hanya Pilihan Kasir) --</option>';
        rawMaterials.forEach(rm => {
            const isSel = selectedId && String(selectedId) === String(rm.id) ? 'selected' : '';
            html += `<option value="${rm.id}" data-unit="${rm.unit || 'pcs'}" ${isSel}>${rm.name} (${rm.unit || 'pcs'})</option>`;
        });
        return html;
    }

    function reindexOptions() {
        const rows = optionsContainer.querySelectorAll('[data-option-row]');
        rows.forEach((row, idx) => {
            const numEl = row.querySelector('.option-number');
            if (numEl) numEl.textContent = idx + 1;

            row.querySelectorAll('input, select').forEach(el => {
                const name = el.getAttribute('name');
                if (name && name.includes('options[')) {
                    el.setAttribute('name', name.replace(/options\[\d*\]/, `options[${idx}]`));
                }
            });

            // Prevent deleting if only 1 row remains
            const removeBtn = row.querySelector('.remove-option-btn');
            if (removeBtn) {
                removeBtn.disabled = (rows.length <= 1);
                removeBtn.classList.toggle('opacity-30', rows.length <= 1);
                removeBtn.classList.toggle('cursor-not-allowed', rows.length <= 1);
            }
        });
    }

    function addOptionRow(data = {}) {
        const row = document.createElement('div');
        row.className = 'variant-option-row p-3 rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900/60 space-y-2.5';
        row.setAttribute('data-option-row', 'true');

        const optionId = data.id || '';
        const optionName = data.name || '';
        const priceAdj = data.price_adjustment !== undefined && data.price_adjustment !== null ? data.price_adjustment : 0;
        const ingId = data.ingredient_product_id || '';
        const ingQty = data.ingredient_quantity !== undefined && data.ingredient_quantity !== null ? data.ingredient_quantity : 1;
        const isDefault = !!data.is_default;
        const unit = data.ingredient_unit || 'pcs';

        row.innerHTML = `
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-400 w-5 text-center option-number">1</span>
                ${optionId ? `<input type="hidden" name="options[][id]" value="${optionId}">` : ''}
                <div class="flex-1">
                    <input
                        type="text"
                        name="options[][name]"
                        value="${escapeHtml(optionName)}"
                        required
                        placeholder="Nama Opsi (mis: Normal, Less Sugar, Extra Shot)"
                        class="option-name-input w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                </div>
                <div class="w-36">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-[10px] font-bold text-slate-400">+Rp</span>
                        <input
                            type="number"
                            name="options[][price_adjustment]"
                            value="${priceAdj}"
                            min="0"
                            step="100"
                            placeholder="0"
                            class="option-price-input w-full h-9 pl-9 pr-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-right tabular-nums"
                        >
                    </div>
                </div>
                <label class="flex items-center gap-1.5 px-2 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-[11px] cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 shrink-0" title="Jadikan opsi terpilih secara bawaan">
                    <input
                        type="checkbox"
                        name="options[][is_default]"
                        value="1"
                        ${isDefault ? 'checked' : ''}
                        class="option-default-input w-3.5 h-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    >
                    <span class="font-medium hidden sm:inline">Def</span>
                </label>
                <button
                    type="button"
                    class="remove-option-btn p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition shrink-0"
                    title="Hapus opsi"
                >
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </div>

            <div class="flex items-center gap-2 pl-7 pt-1 border-t border-slate-100 dark:border-slate-800/80 text-xs">
                <span class="text-[11px] font-medium text-slate-400 shrink-0 flex items-center gap-1">
                    <i data-lucide="package" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span>Potong Stok:</span>
                </span>
                <div class="flex-1 min-w-[140px]">
                    <select
                        name="options[][ingredient_product_id]"
                        class="option-ingredient-select w-full h-8 px-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-[11px] text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        ${buildIngredientOptions(ingId)}
                    </select>
                </div>
                <div class="option-qty-wrapper flex items-center gap-1 w-28 ${ingId ? '' : 'hidden'}">
                    <input
                        type="number"
                        name="options[][ingredient_quantity]"
                        value="${ingQty}"
                        step="0.001"
                        min="0.001"
                        placeholder="Jml"
                        class="option-qty-input w-full h-8 px-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-[11px] text-slate-900 dark:text-white font-medium text-right tabular-nums focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                    <span class="option-unit-label text-[10px] text-slate-400 font-bold shrink-0">${unit}</span>
                </div>
            </div>
        `;

        optionsContainer.appendChild(row);

        // Bind events for this row
        const removeBtn = row.querySelector('.remove-option-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                if (optionsContainer.querySelectorAll('[data-option-row]').length > 1) {
                    row.remove();
                    reindexOptions();
                }
            });
        }

        const ingSelect = row.querySelector('.option-ingredient-select');
        const qtyWrap = row.querySelector('.option-qty-wrapper');
        const unitLabel = row.querySelector('.option-unit-label');
        if (ingSelect && qtyWrap && unitLabel) {
            ingSelect.addEventListener('change', () => {
                const sel = ingSelect.options[ingSelect.selectedIndex];
                const hasIng = !!sel.value;
                qtyWrap.classList.toggle('hidden', !hasIng);
                if (hasIng) {
                    unitLabel.textContent = sel.getAttribute('data-unit') || 'pcs';
                }
            });
        }

        reindexOptions();
        if (window.lucide) window.lucide.createIcons();
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function updateSelectionTypeView() {
        const isMultiple = selectionTypeSelect.value === 'multiple';
        if (limitsSection) {
            limitsSection.classList.toggle('hidden', !isMultiple);
        }
    }

    if (selectionTypeSelect) {
        selectionTypeSelect.addEventListener('change', updateSelectionTypeView);
    }

    if (addOptionBtn) {
        addOptionBtn.addEventListener('click', () => {
            addOptionRow();
        });
    }

    function openModal() {
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        nameInput.focus();
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function openCreate() {
        form.reset();
        form.action = "{{ route('products.variant-groups.store') }}";
        methodInput.value = 'POST';
        titleEl.textContent = 'Tambah Template Varian';
        submitBtn.textContent = 'Simpan Template Varian';

        selectionTypeSelect.value = 'single';
        isRequiredCheck.checked = true;
        minSelectionInput.value = '0';
        maxSelectionInput.value = '';

        optionsContainer.innerHTML = '';
        addOptionRow({ name: 'Normal / Reguler', price_adjustment: 0, is_default: true });
        addOptionRow({ name: 'Varian 2', price_adjustment: 0, is_default: false });

        updateSelectionTypeView();
        openModal();
    }

    function openEdit(group) {
        if (!group) return;

        form.reset();
        form.action = `/products/variant-groups/${group.id}`;
        methodInput.value = 'PATCH';
        titleEl.textContent = `Edit Template: ${group.name}`;
        submitBtn.textContent = 'Perbarui Template Varian';

        nameInput.value = group.name || '';
        selectionTypeSelect.value = group.selection_type || 'single';
        isRequiredCheck.checked = !!group.is_required;
        minSelectionInput.value = group.min_selection ?? 0;
        maxSelectionInput.value = group.max_selection ?? '';

        optionsContainer.innerHTML = '';
        if (group.options && group.options.length > 0) {
            group.options.forEach(opt => addOptionRow(opt));
        } else {
            addOptionRow({ name: 'Opsi 1', price_adjustment: 0, is_default: true });
        }

        updateSelectionTypeView();
        openModal();
    }

    // Delegated click listeners
    document.addEventListener('click', function(e) {
        const createBtn = e.target.closest('[data-open-variant-group-modal]');
        if (createBtn) {
            e.preventDefault();
            openCreate();
            return;
        }

        const editBtn = e.target.closest('[data-edit-variant-group]');
        if (editBtn) {
            e.preventDefault();
            const raw = editBtn.getAttribute('data-edit-variant-group');
            if (raw) {
                try {
                    let group;
                    try {
                        group = JSON.parse(decodeURIComponent(escape(atob(raw))));
                    } catch (_) {
                        group = JSON.parse(raw);
                    }
                    openEdit(group);
                } catch (err) {
                    console.error('Failed to parse variant group data', err);
                }
            }
            return;
        }

        const closeBtn = e.target.closest('[data-close-variant-group-modal]');
        if (closeBtn) {
            e.preventDefault();
            closeModal();
            return;
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
})();
</script>
