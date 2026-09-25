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
                    Halaman ini hanya dapat diakses pemilik bisnis dan bersifat baca-saja.
                </p>
            </div>
        </section>

        <x-subscriptions.current-plan :subscription="$subscription" />

        <x-subscriptions.subscription-status :subscription="$subscription" />

        <x-subscriptions.plan-comparison :subscription="$subscription" />

        <x-subscriptions.cloud-access-info :subscription="$subscription" />
    </main>
</x-layouts::app>
