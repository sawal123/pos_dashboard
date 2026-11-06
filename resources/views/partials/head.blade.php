@include('partials.seo', [
    'title' => filled($title ?? null)
        ? $title.' - '.config('seo.site_name', config('app.name', 'Laravel'))
        : config('seo.site_name', config('app.name', 'Laravel')),
    'description' => config('seo.description'),
    'indexable' => false,
])

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

{{-- NexaPOS Synchronous Anti-Flicker Theme Script --}}
<script data-navigate-once>
    (function () {
        try {
            const stored = localStorage.getItem('nexa-theme') || localStorage.getItem('flux.appearance') || 'light';
            const isDark = stored === 'dark';
            if (isDark) {
                document.documentElement.classList.add('dark');
                document.documentElement.classList.remove('light');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.classList.add('light');
            }
            localStorage.setItem('nexa-theme', isDark ? 'dark' : 'light');
            localStorage.setItem('flux.appearance', isDark ? 'dark' : 'light');
        } catch (e) {}
    })();
</script>

@fluxAppearance

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- ==================== PWA META TAGS & MANIFEST ==================== --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#4f46e5" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="NexaPOS">
<meta name="application-name" content="NexaPOS">
<meta name="msapplication-TileColor" content="#4f46e5">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/icons/icon-512.png">

{{-- ==================== PWA SERVICE WORKER REGISTRATION ==================== --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                .then(function(registration) {
                    registration.addEventListener('updatefound', function() {
                        const installingWorker = registration.installing;
                        if (installingWorker) {
                            installingWorker.addEventListener('statechange', function() {
                                if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    window.dispatchEvent(new CustomEvent('pwa-update-available'));
                                }
                            });
                        }
                    });
                })
                .catch(function(err) {
                    console.debug('[PWA] Service Worker registration failed:', err);
                });
        });
    }
</script>

{{-- Inter Font --}}
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

{{-- TailwindCSS CDN --}}
<script src="https://cdn.tailwindcss.com" data-navigate-once></script>
<script data-navigate-once>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    primary: { 50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca', 800: '#3730a3', 900: '#312e81', 950: '#1e1b4b' }
                },
                fontFamily: { sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'] },
                animation: { 'fade-in': 'fadeIn 0.3s ease-out', 'slide-up': 'slideUp 0.3s ease-out', 'scale-in': 'scaleIn 0.25s ease-out' },
                keyframes: {
                    fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                    slideUp: { '0%': { opacity: '0', transform: 'translateY(12px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
                    scaleIn: { '0%': { opacity: '0', transform: 'scale(0.95)' }, '100%': { opacity: '1', transform: 'scale(1)' } }
                }
            }
        }
    }
</script>

{{-- Lucide Icons --}}
<script src="https://unpkg.com/lucide@latest" data-navigate-once></script>
<script data-navigate-once>
    document.addEventListener('livewire:navigated', () => {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" data-navigate-once></script>

{{-- jQuery & Select2 --}}
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" data-navigate-once></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" data-navigate-once></script>

{{-- Sidebar Collapsed Floating Tooltip --}}
<script data-navigate-once>
    (function() {
        let tooltipEl = null;

        function getOrCreateTooltip() {
            if (!tooltipEl) {
                tooltipEl = document.getElementById('sidebarFloatingTooltip');
                if (!tooltipEl) {
                    tooltipEl = document.createElement('div');
                    tooltipEl.id = 'sidebarFloatingTooltip';
                    tooltipEl.className = 'fixed z-[9999] pointer-events-none px-2.5 py-1.5 rounded-xl text-xs font-semibold text-white bg-slate-900/95 dark:bg-slate-800/95 border border-slate-700/80 shadow-2xl backdrop-blur-sm opacity-0 transition-all duration-150 whitespace-nowrap scale-95';
                    document.body.appendChild(tooltipEl);
                }
            }
            return tooltipEl;
        }

        function showSidebarTooltip(target) {
            if (window.innerWidth < 768) return;
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;
            const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
            // Only show floating tooltip when sidebar is collapsed
            if (!isCollapsed) return;

            const text = target.getAttribute('data-tooltip-right');
            if (!text) return;

            const el = getOrCreateTooltip();
            const rect = target.getBoundingClientRect();
            el.textContent = text;
            el.style.left = `${rect.right + 12}px`;
            el.style.top = `${rect.top + rect.height / 2}px`;
            el.style.transform = 'translateY(-50%)';
            el.classList.remove('opacity-0', 'scale-95');
            el.classList.add('opacity-100', 'scale-100');
        }

        function hideSidebarTooltip() {
            if (tooltipEl) {
                tooltipEl.classList.remove('opacity-100', 'scale-100');
                tooltipEl.classList.add('opacity-0', 'scale-95');
            }
        }

        function initSidebarTooltipListeners() {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar || sidebar.dataset.tooltipBound === 'true') return;
            sidebar.dataset.tooltipBound = 'true';

            sidebar.addEventListener('mouseover', (e) => {
                const item = e.target.closest('[data-tooltip-right]');
                if (item && sidebar.contains(item)) {
                    showSidebarTooltip(item);
                }
            });

            sidebar.addEventListener('mouseout', (e) => {
                const item = e.target.closest('[data-tooltip-right]');
                if (item && sidebar.contains(item)) {
                    hideSidebarTooltip();
                }
            });

            sidebar.addEventListener('focusin', (e) => {
                const item = e.target.closest('[data-tooltip-right]');
                if (item && sidebar.contains(item)) {
                    showSidebarTooltip(item);
                }
            });

            sidebar.addEventListener('focusout', (e) => {
                const item = e.target.closest('[data-tooltip-right]');
                if (item && sidebar.contains(item)) {
                    hideSidebarTooltip();
                }
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSidebarTooltipListeners);
        } else {
            initSidebarTooltipListeners();
        }

        document.addEventListener('livewire:navigated', () => {
            hideSidebarTooltip();
            initSidebarTooltipListeners();
        });
        window.addEventListener('scroll', hideSidebarTooltip, true);
        window.addEventListener('resize', hideSidebarTooltip);
    })();
</script>

{{-- NexaPOS Custom CSS --}}
<style>
    * { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark ::-webkit-scrollbar-thumb { background: #475569; }

    /* Tooltip Base */
    [data-tooltip], [data-tooltip-bottom] { position: relative; }
    
    /* Default: Top Tooltip */
    [data-tooltip]:hover::after, [data-tooltip]:focus-visible::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: calc(100% + 8px);
        left: 50%;
        transform: translateX(-50%);
        padding: 0.4rem 0.7rem;
        background: #0f172a;
        color: #fff;
        border-radius: 0.5rem;
        font-size: 0.7rem;
        font-weight: 500;
        white-space: nowrap;
        z-index: 200;
        pointer-events: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        animation: tooltipFadeInTop 0.15s ease-out forwards;
    }

    /* Bottom Tooltips (Header, Navbar, Topbar, or explicit bottom placement) */
    header [data-tooltip]:hover::after,
    header [data-tooltip]:focus-visible::after,
    #navbar [data-tooltip]:hover::after,
    #navbar [data-tooltip]:focus-visible::after,
    .topbar [data-tooltip]:hover::after,
    .topbar [data-tooltip]:focus-visible::after,
    [data-tooltip-placement="bottom"]:hover::after,
    [data-tooltip-placement="bottom"]:focus-visible::after,
    [data-tooltip-bottom]:hover::after,
    [data-tooltip-bottom]:focus-visible::after {
        content: attr(data-tooltip);
        top: calc(100% + 8px);
        bottom: auto;
        left: 50%;
        transform: translateX(-50%);
        animation: tooltipFadeInBottom 0.15s ease-out forwards;
    }
    [data-tooltip-bottom]:hover::after,
    [data-tooltip-bottom]:focus-visible::after {
        content: attr(data-tooltip-bottom);
    }

    .dark [data-tooltip]:hover::after,
    .dark [data-tooltip]:focus-visible::after,
    .dark [data-tooltip-bottom]:hover::after,
    .dark [data-tooltip-bottom]:focus-visible::after {
        background: #1e293b;
        border: 1px solid #334155;
    }

    @keyframes tooltipFadeInTop {
        from { opacity: 0; transform: translate(-50%, 4px); }
        to { opacity: 1; transform: translate(-50%, 0); }
    }
    @keyframes tooltipFadeInBottom {
        from { opacity: 0; transform: translate(-50%, -4px); }
        to { opacity: 1; transform: translate(-50%, 0); }
    }

    .modal-backdrop { transition: opacity 0.25s ease; }
    .modal-panel { transition: transform 0.25s ease, opacity 0.25s ease; }
    .modal-hidden .modal-panel { transform: scale(0.95) translateY(12px); opacity: 0; }
    .modal-hidden .modal-backdrop { opacity: 0; pointer-events: none; }

    .toast-enter { animation: slideInRight 0.35s ease-out forwards; }
    .toast-exit { animation: slideOutRight 0.3s ease-in forwards; }
    @keyframes slideInRight { from { opacity: 0; transform: translateX(32px); } to { opacity: 1; transform: translateX(0); } }
    @keyframes slideOutRight { from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(32px); } }

    .dropdown-panel { transition: opacity 0.15s ease, transform 0.15s ease; }
    .dropdown-hidden { opacity: 0; transform: scale(0.97); pointer-events: none; }

    .skeleton { background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
    .dark .skeleton { background: linear-gradient(90deg, #334155 25%, #475569 50%, #334155 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    .sidebar-transition { transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1), transform 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
    .sidebar-collapsed .sidebar-label,
    .sidebar-collapsed .sidebar-section-label,
    .sidebar-collapsed .sidebar-cloud-card,
    .sidebar-collapsed .sidebar-business-card,
    .sidebar-collapsed .sidebar-logo-text,
    .sidebar-collapsed .sidebar-item-label,
    .sidebar-collapsed .sidebar-item-badge,
    .sidebar-collapsed .sidebar-active-indicator { display: none !important; }
    .sidebar-collapsed .sidebar-cloud-mini,
    .sidebar-collapsed .sidebar-business-mini { display: flex !important; }
    .sidebar-collapsed .sidebar-item { justify-content: center; padding: 0.625rem; width: 2.75rem; height: 2.75rem; margin-left: auto; margin-right: auto; }

    .table-row:hover { background: #f8fafc; }
    .dark .table-row:hover { background: #1e293b; }

    .custom-checkbox { appearance: none; width: 1.1rem; height: 1.1rem; border: 2px solid #cbd5e1; border-radius: 0.3rem; cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; justify-content: center; }
    .custom-checkbox:checked { background: #4f46e5; border-color: #4f46e5; }
    .custom-checkbox:checked::after { content: ''; width: 0.55rem; height: 0.3rem; border-left: 2px solid #fff; border-bottom: 2px solid #fff; transform: rotate(-45deg) translateY(-1px); }
    .dark .custom-checkbox { border-color: #64748b; }
    .dark .custom-checkbox:checked { background: #6366f1; border-color: #6366f1; }

    .custom-radio { appearance: none; width: 1.1rem; height: 1.1rem; border: 2px solid #cbd5e1; border-radius: 50%; cursor: pointer; transition: all 0.15s ease; position: relative; }
    .custom-radio:checked { border-color: #4f46e5; }
    .custom-radio:checked::after { content: ''; position: absolute; inset: 3px; background: #4f46e5; border-radius: 50%; }
    .dark .custom-radio { border-color: #64748b; }
    .dark .custom-radio:checked { border-color: #6366f1; }
    .dark .custom-radio:checked::after { background: #6366f1; }

    .toggle-switch { width: 2.6rem; height: 1.5rem; border-radius: 9999px; background: #cbd5e1; position: relative; cursor: pointer; transition: background 0.2s ease; flex-shrink: 0; }
    .toggle-switch.active { background: #4f46e5; }
    .toggle-switch::after { content: ''; position: absolute; top: 3px; left: 3px; width: 1.1rem; height: 1.1rem; border-radius: 50%; background: #fff; transition: transform 0.2s ease; }
    .toggle-switch.active::after { transform: translateX(1.1rem); }
    .dark .toggle-switch { background: #475569; }
    .dark .toggle-switch.active { background: #6366f1; }

    .select-dropdown { max-height: 240px; overflow-y: auto; }
    .select-option:hover { background: #f1f5f9; }
    .dark .select-option:hover { background: #334155; }
    .select-option.selected { background: #eef2ff; color: #4f46e5; }
    .dark .select-option.selected { background: #1e1b4b; color: #818cf8; }

    /* ==================== Select2 NexaPOS Styles ==================== */
    .select2-container { width: 100% !important; }

    /* Single Select styling */
    .select2-container--default .select2-selection--single {
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        height: 38px;
        display: flex;
        align-items: center;
        transition: all 0.15s ease-in-out;
    }
    .dark .select2-container--default .select2-selection--single {
        background-color: #0f172a;
        border-color: #334155;
        color: #f8fafc;
    }
    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #6366f1;
        outline: none;
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.35);
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #0f172a;
        font-size: 0.75rem;
        font-weight: 500;
        padding-left: 10px;
        padding-right: 28px;
        line-height: normal;
        display: flex;
        align-items: center;
        width: 100%;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #f8fafc;
    }
    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #94a3b8;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 100%;
        top: 0;
        right: 8px;
        display: flex;
        align-items: center;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #64748b transparent transparent transparent;
    }
    .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-color: transparent transparent #64748b transparent;
    }
    .select2-container--default .select2-selection--single .select2-selection__clear {
        color: #94a3b8;
        font-size: 14px;
        margin-right: 8px;
        font-weight: bold;
    }
    .select2-container--default .select2-selection--single .select2-selection__clear:hover {
        color: #ef4444;
    }

    .select2-container--default .select2-selection--multiple {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        min-height: 42px;
        padding: 3px 6px;
        transition: all 0.15s ease-in-out;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
    }
    .dark .select2-container--default .select2-selection--multiple {
        background-color: #1e293b;
        border-color: #334155;
        color: #f8fafc;
    }
    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #6366f1;
        outline: none;
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.35);
    }
    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
        width: 100%;
        padding: 0;
        margin: 0;
    }
    /* Choices (Pills) */
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #eef2ff;
        border: 1px solid #c7d2fe;
        color: #4338ca;
        border-radius: 0.5rem;
        padding: 3px 8px;
        font-size: 0.75rem;
        font-weight: 600;
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .dark .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #312e81 !important;
        border-color: #6366f1 !important;
        color: #ffffff !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__display {
        color: inherit !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #6366f1 !important;
        border: none !important;
        background: transparent !important;
        font-size: 14px !important;
        font-weight: bold !important;
        cursor: pointer !important;
        padding: 0 !important;
        margin-right: 4px !important;
        line-height: 1 !important;
        display: inline-flex !important;
        align-items: center !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #ef4444 !important;
        background: transparent !important;
    }
    .dark .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #c7d2fe !important;
    }
    .dark .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #f87171 !important;
    }
    .select2-container--default .select2-search--inline {
        margin: 0;
        flex-grow: 1;
    }
    .select2-container--default .select2-search--inline .select2-search__field {
        margin: 0;
        padding: 4px;
        font-family: inherit;
        font-size: 0.8125rem;
        color: #0f172a;
        background: transparent;
        border: none;
        outline: none;
        width: 100% !important;
    }
    .dark .select2-container--default .select2-search--inline .select2-search__field {
        color: #f8fafc;
    }
    .select2-container--default .select2-search--inline .select2-search__field::placeholder {
        color: #94a3b8;
    }
    .select2-dropdown {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        z-index: 99999 !important;
        overflow: hidden;
        margin-top: 4px;
    }
    .dark .select2-dropdown {
        background-color: #0f172a;
        border-color: #334155;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
    }
    .select2-container--default .select2-search--dropdown {
        padding: 8px;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
        border: 1px solid #cbd5e1;
        border-radius: 0.5rem;
        padding: 6px 10px;
        background-color: #f8fafc;
        color: #0f172a;
        font-size: 0.8125rem;
        outline: none;
        width: 100%;
    }
    .dark .select2-container--default .select2-search--dropdown .select2-search__field {
        border-color: #334155;
        background-color: #1e293b;
        color: #f8fafc;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 1px #6366f1;
    }
    .select2-results__options {
        max-height: 220px;
        overflow-y: auto;
        padding: 6px;
    }
    .select2-container--default .select2-results__option {
        padding: 8px 12px;
        border-radius: 0.5rem;
        font-size: 0.8125rem;
        font-weight: 500;
        color: #1e293b;
        cursor: pointer;
        transition: background-color 0.1s ease, color 0.1s ease;
    }
    .dark .select2-container--default .select2-results__option {
        color: #cbd5e1;
    }

    /* Selected Option (Light Mode) */
    .select2-container--default .select2-results__option--selected,
    .select2-container--default .select2-results__option[aria-selected="true"] {
        background-color: #e0e7ff !important;
        color: #3730a3 !important;
        font-weight: 600 !important;
    }

    /* Selected Option (Dark Mode) - override Select2 default #ddd completely */
    .dark .select2-container--default .select2-results__option--selected,
    .dark .select2-container--default .select2-results__option[aria-selected="true"] {
        background-color: rgba(99, 102, 241, 0.25) !important;
        color: #e0e7ff !important;
        font-weight: 600 !important;
    }

    /* Checkmark indicator for selected items */
    .select2-container--default .select2-results__option--selected::after,
    .select2-container--default .select2-results__option[aria-selected="true"]::after {
        content: '✓';
        float: right;
        font-weight: 700;
        margin-left: 8px;
        color: currentColor;
    }

    /* Hovered / Highlighted Option (Light Mode) */
    .select2-container--default .select2-results__option--highlighted,
    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable,
    .select2-container--default .select2-results__option--highlighted[aria-selected],
    .select2-container--default .select2-results__option--selected.select2-results__option--highlighted {
        background-color: #4f46e5 !important;
        color: #ffffff !important;
    }

    /* Hovered / Highlighted Option (Dark Mode) */
    .dark .select2-container--default .select2-results__option--highlighted,
    .dark .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable,
    .dark .select2-container--default .select2-results__option--highlighted[aria-selected],
    .dark .select2-container--default .select2-results__option--selected.select2-results__option--highlighted {
        background-color: #6366f1 !important;
        color: #ffffff !important;
    }
    .select2-results__message {
        color: #94a3b8;
        font-size: 0.8125rem;
        padding: 8px 12px;
        text-align: center;
    }
</style>
