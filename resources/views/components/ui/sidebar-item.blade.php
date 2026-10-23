@props([
    'icon' => 'circle',
    'label' => '',
    'route' => null,
    'url' => '#',
    'badge' => null,
    'badgeColor' => 'indigo',
    'active' => null,
    'disabled' => false,
    'requiresSubscription' => false,
    'subscription' => 'unknown',
])

@php
    $subscriptionGatedRoutes = [
        'transactions.index',
        'products.index',
        'stock.index',
        'cash.index',
        'shifts.index',
        'customers.index',
        'laundry-orders.index',
        'reports.index',
        'outlets.index',
        'users.index',
        'devices.index',
        'sync.index',
    ];
    $requiresSubscription = $requiresSubscription || in_array($route, $subscriptionGatedRoutes, true);
    $isCloudSubscriber = isset($dashboardCloudAccess)
        ? (bool) $dashboardCloudAccess
        : $subscription === 'subscriber';
    $isSubscriptionLocked = $requiresSubscription && ! $isCloudSubscriber;
    $hasRealRoute = !$disabled && $route && Route::has($route);
    $isActive = !$disabled && ! $isSubscriptionLocked && ($active !== null ? (bool) $active : ($hasRealRoute && request()->routeIs($route)));
    $href = $disabled || $isSubscriptionLocked ? '#' : ($hasRealRoute ? route($route) : $url);

    $baseClasses = 'sidebar-item group relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs md:text-sm transition-all duration-150 select-none focus:outline-none';

    if ($disabled) {
        $stateClasses = 'opacity-50 cursor-not-allowed text-slate-400 dark:text-slate-600 pointer-events-none';
    } elseif ($isSubscriptionLocked) {
        $stateClasses = 'text-slate-500 dark:text-slate-500 hover:text-indigo-700 dark:hover:text-indigo-300 hover:bg-indigo-50/70 dark:hover:bg-indigo-950/40 font-medium focus-visible:ring-2 focus-visible:ring-indigo-500';
    } elseif ($isActive) {
        $stateClasses = 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-semibold border border-indigo-100/70 dark:border-indigo-900/50 shadow-xs focus-visible:ring-2 focus-visible:ring-indigo-500';
    } else {
        $stateClasses = 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-slate-100/80 dark:hover:bg-slate-800/60 font-medium focus-visible:ring-2 focus-visible:ring-indigo-500';
    }
@endphp

<a
    href="{{ $href }}"
    @if($hasRealRoute && !$isSubscriptionLocked) wire:navigate @endif
    class="{{ $baseClasses }} {{ $stateClasses }}"
    data-tooltip-right="{{ $label }}"
    data-nav-label="{{ $label }}"
    data-has-route="{{ $hasRealRoute && !$isSubscriptionLocked ? 'true' : 'false' }}"
    data-subscription-locked="{{ $isSubscriptionLocked ? 'true' : 'false' }}"
    @if($disabled)
        aria-disabled="true"
        tabindex="-1"
    @elseif($isSubscriptionLocked)
        aria-label="{{ $label }} — berlangganan Cloud untuk membuka"
    @elseif($isActive)
        aria-current="page"
    @endif
>
    {{-- Left active indicator bar --}}
    @if($isActive)
        <span class="sidebar-active-indicator absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 rounded-r-full bg-indigo-600 dark:bg-indigo-400"></span>
    @endif

    <i data-lucide="{{ $icon }}" class="w-5 h-5 shrink-0 transition-transform duration-150 group-hover:scale-105 {{ $isActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 dark:text-slate-400 group-hover:text-slate-800 dark:group-hover:text-slate-200' }}"></i>

    <span class="sidebar-item-label truncate flex-1 tracking-tight">{{ $label }}</span>

    @if($isSubscriptionLocked)
        <i data-lucide="lock-keyhole" class="sidebar-item-badge h-3.5 w-3.5 shrink-0 text-slate-400 dark:text-slate-500" aria-hidden="true"></i>
    @endif

    @if($badge)
        <span class="sidebar-item-badge text-[10px] font-semibold px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 group-hover:bg-indigo-100 dark:group-hover:bg-indigo-950 group-hover:text-indigo-600 dark:group-hover:text-indigo-300 transition-colors">
            {{ $badge }}
        </span>
    @endif
</a>
