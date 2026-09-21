@props(['requests' => []])

<div id="sync-mobile-container" class="md:hidden space-y-3">
    @foreach($requests as $req)
        <div 
            class="sync-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm space-y-3"
            data-id="{{ $req['id'] }}"
            data-request-id="{{ $req['request_id'] }}"
            data-device-id="{{ $req['device_id'] }}"
            data-device-name="{{ $req['device_name'] }}"
            data-device-identifier="{{ $req['device_identifier'] }}"
            data-outlet-name="{{ $req['outlet_name'] }}"
            data-processed-at="{{ $req['processed_at'] }}"
            data-processed-at-raw="{{ $req['processed_at_raw'] }}"
        >
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h3 class="font-semibold text-sm text-slate-900 dark:text-slate-100">
                        {{ $req['device_name'] }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ $req['processed_at'] }}
                    </p>
                </div>
            </div>

            <div class="space-y-1 bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-medium text-slate-400 uppercase tracking-wider block">Request ID</span>
                <span class="font-mono text-xs text-slate-800 dark:text-slate-200 break-all select-all block">
                    {{ $req['request_id'] }}
                </span>
            </div>

            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 pt-1">
                <span>{{ $req['outlet_name'] }}</span>
                <span class="font-mono text-[11px] max-w-[160px] truncate text-slate-400" title="{{ $req['device_identifier'] }}">
                    {{ $req['device_identifier'] }}
                </span>
            </div>

            <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                <button 
                    type="button" 
                    class="btn-sync-detail w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 bg-indigo-50/60 dark:bg-indigo-950/30 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 rounded-xl transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    data-id="{{ $req['id'] }}"
                    aria-label="Lihat detail request {{ $req['request_id'] }}"
                >
                    <span>Lihat Detail</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
    @endforeach
</div>
