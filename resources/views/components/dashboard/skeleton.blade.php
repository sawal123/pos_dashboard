@props([
    'type' => 'card', // 'card', 'table-row', 'kpi'
    'count' => 1,
])

@if($type === 'kpi')
    @for($i = 0; $i < $count; $i++)
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-xs animate-pulse">
            <div class="flex items-center justify-between mb-4">
                <div class="skeleton h-3.5 w-24 rounded"></div>
                <div class="skeleton h-10 w-10 rounded-xl"></div>
            </div>
            <div class="skeleton h-7 w-36 rounded mb-2"></div>
            <div class="skeleton h-3.5 w-20 rounded"></div>
        </div>
    @endfor
@elseif($type === 'table-row')
    @for($i = 0; $i < $count; $i++)
        <tr class="border-b border-slate-100 dark:border-slate-800 animate-pulse">
            <td class="px-4 py-3.5"><div class="skeleton h-4 w-4 rounded"></div></td>
            <td class="px-4 py-3.5"><div class="skeleton h-4 w-28 rounded"></div></td>
            <td class="px-4 py-3.5"><div class="skeleton h-4 w-24 rounded"></div></td>
            <td class="px-4 py-3.5"><div class="skeleton h-4 w-20 rounded"></div></td>
            <td class="px-4 py-3.5"><div class="skeleton h-4 w-16 rounded-full"></div></td>
            <td class="px-4 py-3.5 text-right"><div class="skeleton h-4 w-24 rounded ml-auto"></div></td>
            <td class="px-4 py-3.5"><div class="skeleton h-5 w-20 rounded-full"></div></td>
            <td class="px-4 py-3.5"><div class="skeleton h-4 w-24 rounded"></div></td>
            <td class="px-4 py-3.5 text-right"><div class="skeleton h-6 w-6 rounded-lg ml-auto"></div></td>
        </tr>
    @endfor
@else
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-xs animate-pulse">
        <div class="skeleton h-5 w-32 rounded mb-3"></div>
        <div class="skeleton h-4 w-48 rounded mb-6"></div>
        <div class="skeleton h-48 w-full rounded-xl"></div>
    </div>
@endif
