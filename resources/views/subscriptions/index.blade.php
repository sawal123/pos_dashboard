<x-layouts::app :title="'Langganan'">
    <main id="mainContent" data-subscriptions-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <section class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <p class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                    Cloud &amp; Sistem
                </p>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Langganan
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                    Ringkasan paket langganan dan hak akses sinkronisasi Cloud untuk bisnis aktif.
                    Halaman ini hanya dapat diakses pemilik bisnis, yang juga dapat memperpanjang atau
                    meningkatkan paket dari sini.
                </p>
            </div>
        </section>

        @if (session('status'))
            <div role="status" class="rounded-2xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/20 p-4 flex items-start gap-3">
                <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
            </div>
        @endif

        <x-subscriptions.current-plan :subscription="$subscription" />

        <x-subscriptions.subscription-status :subscription="$subscription" />

        <x-subscriptions.plan-checkout
            :plans="$plans"
            :checkout-available="$checkoutAvailable"
            :readiness="$checkoutReadiness"
            :subscription="$subscription"
        />

        <x-subscriptions.plan-comparison :subscription="$subscription" />

        <x-subscriptions.cloud-access-info :subscription="$subscription" />
    </main>
</x-layouts::app>
