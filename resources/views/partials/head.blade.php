<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Inter Font --}}
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

{{-- TailwindCSS CDN --}}
<script src="https://cdn.tailwindcss.com"></script>
<script>
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
<script src="https://unpkg.com/lucide@latest"></script>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

{{-- NexaPOS Custom CSS --}}
<style>
    * { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark ::-webkit-scrollbar-thumb { background: #475569; }

    [data-tooltip] { position: relative; }
    [data-tooltip]:hover::after, [data-tooltip]:focus-visible::after { content: attr(data-tooltip); position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%); padding: 0.4rem 0.7rem; background: #0f172a; color: #fff; border-radius: 0.5rem; font-size: 0.7rem; font-weight: 500; white-space: nowrap; z-index: 200; pointer-events: none; box-shadow: 0 4px 12px rgba(0,0,0,0.25); }
    .dark [data-tooltip]:hover::after, .dark [data-tooltip]:focus-visible::after { background: #1e293b; border: 1px solid #334155; }

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
    .sidebar-collapsed .sidebar-logo-text,
    .sidebar-collapsed .sidebar-item-label,
    .sidebar-collapsed .sidebar-item-badge,
    .sidebar-collapsed .sidebar-active-indicator { display: none !important; }
    .sidebar-collapsed .sidebar-cloud-mini { display: flex !important; }
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
</style>
