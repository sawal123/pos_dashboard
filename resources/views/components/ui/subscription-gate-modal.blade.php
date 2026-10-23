@props([
    'autoOpen' => false,
])

@php
    $permissions = $dashboardPermissions ?? [];
    $canManageSubscription = in_array('*', $permissions, true)
        || in_array(\App\Services\Authorization\BusinessPermission::SUBSCRIPTION_MANAGE, $permissions, true);
    $benefits = config('premium.plan.benefits', [
        'Web dashboard',
        'Cloud sync',
        'Cloud devices',
    ]);
    $benefits = array_map(static fn (string $benefit): string => match ($benefit) {
        'Web dashboard' => 'Dashboard web',
        'Cloud sync' => 'Sinkronisasi Cloud',
        'Cloud devices' => 'Perangkat Cloud',
        default => $benefit,
    }, $benefits);
@endphp

<div
    id="subscriptionGateModal"
    class="fixed inset-0 z-[120] hidden items-center justify-center p-4 modal-hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="subscriptionGateTitle"
    data-auto-open="{{ $autoOpen ? 'true' : 'false' }}"
>
    <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" data-modal-close></div>

    <div class="relative w-full max-w-lg overflow-hidden rounded-3xl border border-slate-200/90 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-2xl shadow-slate-950/30">
        <div class="absolute -right-16 -top-20 h-48 w-48 rounded-full bg-indigo-500/15 blur-3xl"></div>
        <div class="absolute -bottom-20 -left-16 h-48 w-48 rounded-full bg-cyan-400/10 blur-3xl"></div>

        <div class="relative p-6 sm:p-8">
            <button
                type="button"
                data-modal-close
                class="absolute right-4 top-4 rounded-xl p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Tutup"
            >
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>

            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/25">
                <i data-lucide="cloud-lock" class="h-7 w-7"></i>
            </div>

            <div class="mt-5 space-y-2 pr-8">
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200/70 bg-indigo-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-indigo-700 dark:border-indigo-800/70 dark:bg-indigo-950/60 dark:text-indigo-300">
                    <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                    Upgrade ke Cloud
                </span>
                <h2 id="subscriptionGateTitle" class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Buka semua fitur NexaPOS
                </h2>
                <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Dashboard dan menu operasional dikunci karena bisnis Anda belum memiliki langganan Cloud aktif.
                    Aktifkan paket Cloud untuk melanjutkan.
                </p>
            </div>

            <div class="mt-6 rounded-2xl border border-indigo-200/80 bg-indigo-50/70 p-4 dark:border-indigo-900/70 dark:bg-indigo-950/30">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">Paket Cloud</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">Siap untuk operasional yang lebih lancar</p>
                    </div>
                    <i data-lucide="cloud" class="h-6 w-6 text-indigo-600 dark:text-indigo-400"></i>
                </div>

                <ul class="mt-4 grid gap-2 sm:grid-cols-3">
                    @foreach($benefits as $benefit)
                        <li class="flex items-center gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-300">
                            <i data-lucide="check" class="h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
                            <span>{{ $benefit }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if($canManageSubscription)
                <a
                    href="{{ route('subscriptions.index') }}"
                    wire:navigate
                    class="mt-6 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm shadow-indigo-600/20 transition-all hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                >
                    <i data-lucide="badge-dollar-sign" class="h-4 w-4"></i>
                    Lihat paket &amp; berlangganan
                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>
            @else
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-200">
                    Hubungi pemilik bisnis untuk mengaktifkan langganan Cloud.
                </div>
            @endif

            <button
                type="button"
                data-modal-close
                class="mt-3 inline-flex h-10 w-full items-center justify-center rounded-xl px-4 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            >
                Lihat menu nanti
            </button>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            function openSubscriptionGate() {
                const modal = document.getElementById('subscriptionGateModal');
                if (!modal) return;

                if (typeof window.openModal === 'function') {
                    window.openModal(modal);
                } else {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }

                if (typeof window.initIcons === 'function') {
                    window.initIcons();
                }
            }

            window.openSubscriptionGate = openSubscriptionGate;

            function autoOpenSubscriptionGate() {
                const modal = document.getElementById('subscriptionGateModal');
                if (modal && modal.dataset.autoOpen === 'true') {
                    window.setTimeout(openSubscriptionGate, 120);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', autoOpenSubscriptionGate);
            } else {
                autoOpenSubscriptionGate();
            }

            document.addEventListener('livewire:navigated', autoOpenSubscriptionGate);
        })();
    </script>
@endpush
