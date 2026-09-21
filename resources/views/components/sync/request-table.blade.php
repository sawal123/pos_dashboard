@props(['requests' => []])

<div class="hidden md:block bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 font-semibold border-b border-slate-200 dark:border-slate-800">
                <tr>
                    <th scope="col" class="py-3.5 px-4 lg:px-6">Diproses</th>
                    <th scope="col" class="py-3.5 px-4 lg:px-6">Request ID</th>
                    <th scope="col" class="py-3.5 px-4 lg:px-6">Perangkat</th>
                    <th scope="col" class="py-3.5 px-4 lg:px-6">Identifier</th>
                    <th scope="col" class="py-3.5 px-4 lg:px-6">Outlet</th>
                    <th scope="col" class="py-3.5 px-4 lg:px-6 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody id="sync-table-body" class="divide-y divide-slate-200 dark:divide-slate-800">
                @foreach($requests as $req)
                    <tr 
                        class="sync-row hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
                        data-id="{{ $req['id'] }}"
                        data-request-id="{{ $req['request_id'] }}"
                        data-device-id="{{ $req['device_id'] }}"
                        data-device-name="{{ $req['device_name'] }}"
                        data-device-identifier="{{ $req['device_identifier'] }}"
                        data-outlet-name="{{ $req['outlet_name'] }}"
                        data-processed-at="{{ $req['processed_at'] }}"
                        data-processed-at-raw="{{ $req['processed_at_raw'] }}"
                    >
                        <td class="py-4 px-4 lg:px-6 whitespace-nowrap text-slate-700 dark:text-slate-200 text-xs font-medium">
                            {{ $req['processed_at'] }}
                        </td>
                        <td class="py-4 px-4 lg:px-6">
                            <span class="font-mono text-xs text-slate-800 dark:text-slate-200 font-semibold break-all select-all">
                                {{ $req['request_id'] }}
                            </span>
                        </td>
                        <td class="py-4 px-4 lg:px-6 font-medium text-slate-900 dark:text-slate-100 whitespace-nowrap">
                            {{ $req['device_name'] }}
                        </td>
                        <td class="py-4 px-4 lg:px-6">
                            <span class="font-mono text-xs text-slate-500 dark:text-slate-400 break-all select-all">
                                {{ $req['device_identifier'] }}
                            </span>
                        </td>
                        <td class="py-4 px-4 lg:px-6 whitespace-nowrap text-slate-600 dark:text-slate-400">
                            {{ $req['outlet_name'] }}
                        </td>
                        <td class="py-4 px-4 lg:px-6 text-right whitespace-nowrap">
                            <button 
                                type="button" 
                                class="btn-sync-detail inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                data-id="{{ $req['id'] }}"
                                aria-label="Lihat detail request {{ $req['request_id'] }}"
                            >
                                <span>Lihat Detail</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
