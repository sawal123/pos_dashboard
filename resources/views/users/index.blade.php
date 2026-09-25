<x-layouts::app :title="'Pengguna & Kasir'">
    <main id="mainContent" data-users-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
        <section class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <p class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                    Bisnis
                </p>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Pengguna &amp; Kasir
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                    Monitoring anggota bisnis aktif beserta peran dan status verifikasi email. Halaman ini hanya dapat
                    diakses pemilik bisnis dan bersifat baca-saja.
                </p>
            </div>
        </section>

        <div class="rounded-2xl border border-amber-200/80 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20 p-4 flex items-start gap-3">
            <i data-lucide="info" class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400"></i>
            <p class="text-xs text-amber-800 dark:text-amber-200 leading-relaxed">
                Peran yang ditampilkan berasal langsung dari data keanggotaan. Hanya peran
                <span class="font-semibold">Pemilik</span> yang memiliki aturan akses khusus yang berlaku saat ini.
                Peran lain ditampilkan apa adanya dan belum memiliki kontrak otorisasi tersendiri.
            </p>
        </div>

        <x-users.summary-cards :summary="$summary" />

        <x-users.filter-bar :filterOptions="$filterOptions" :currentFilters="$currentFilters" />

        @if(! $hasAnyMembers)
            <x-users.empty-state mode="no-data" />
        @elseif($users->isEmpty())
            <x-users.empty-state mode="no-results" />
        @else
            <section class="space-y-4">
                <x-users.table :users="$users" />
                <x-users.mobile-cards :users="$users" />

                @if($users->hasPages())
                    <div class="pt-2">
                        {{ $users->links() }}
                    </div>
                @endif
            </section>
        @endif

        <x-users.detail-drawer />

        <script>
            function initUsersPage() {
                const root = document.querySelector('main[data-users-page="true"]');
                if (!root || root.dataset.usersInitialized === 'true') {
                    return;
                }
                root.dataset.usersInitialized = 'true';

                if (typeof window.__usersDrawerCleanup === 'function') {
                    window.__usersDrawerCleanup();
                    window.__usersDrawerCleanup = null;
                }

                const drawerWrapper = document.getElementById('userDrawerWrapper');
                const drawerBackdrop = document.getElementById('userDrawerBackdrop');
                const drawerPanel = document.getElementById('userDrawerPanel');
                const closeButtons = document.querySelectorAll('[data-close-user-drawer]');
                const detailButtons = document.querySelectorAll('.view-user-detail-btn');
                const loadingEl = document.getElementById('userDrawerLoading');
                const contentEl = document.getElementById('userDrawerContent');
                const errorEl = document.getElementById('userDrawerError');

                const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

                // Drawer lifecycle state is scoped per initialization so wire:navigate
                // always starts from a clean slate and never reuses stale closures.
                let isOpen = false;
                let closeTimer = null;
                let activeController = null;
                let requestToken = 0;
                let lastTriggerButton = null;

                const setText = (id, value) => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.textContent = value ?? '-';
                    }
                };

                const roleBadgeClasses = (category) => {
                    if (category === 'owner') {
                        return 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800';
                    }
                    if (category === 'member') {
                        return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700';
                    }
                    return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
                };

                const verificationBadgeClasses = (verified) => verified
                    ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                    : 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800';

                // Abort the in-flight request and invalidate its response token so a
                // slower response can never paint over a newer selection.
                const abortActiveRequest = () => {
                    requestToken += 1;
                    if (activeController !== null) {
                        activeController.abort();
                        activeController = null;
                    }
                };

                const focusDrawer = () => {
                    if (!drawerPanel) {
                        return;
                    }
                    const focusables = Array.from(drawerPanel.querySelectorAll(focusableSelector));
                    if (focusables.length > 0) {
                        focusables[0].focus({ preventScroll: true });
                    } else {
                        drawerPanel.focus({ preventScroll: true });
                    }
                };

                const restoreTriggerFocus = () => {
                    const target = lastTriggerButton;
                    lastTriggerButton = null;
                    if (target && typeof target.focus === 'function' && document.contains(target)) {
                        target.focus({ preventScroll: true });
                    }
                };

                const openDrawer = (triggerButton) => {
                    if (!drawerWrapper || !drawerBackdrop || !drawerPanel) {
                        return;
                    }

                    // Cancel a pending close so a quick reopen cannot be undone by
                    // the previous close animation timer.
                    if (closeTimer !== null) {
                        window.clearTimeout(closeTimer);
                        closeTimer = null;
                    }

                    isOpen = true;
                    if (triggerButton) {
                        lastTriggerButton = triggerButton;
                    }

                    drawerWrapper.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');

                    requestAnimationFrame(() => {
                        drawerBackdrop.classList.remove('opacity-0');
                        drawerPanel.classList.remove('translate-x-full');
                        focusDrawer();
                    });
                };

                const closeDrawer = () => {
                    if (!drawerWrapper || !drawerBackdrop || !drawerPanel) {
                        return;
                    }
                    if (!isOpen) {
                        return;
                    }

                    isOpen = false;

                    // Closing cancels any pending detail request so its response
                    // cannot mutate the DOM after the drawer is gone.
                    abortActiveRequest();

                    drawerBackdrop.classList.add('opacity-0');
                    drawerPanel.classList.add('translate-x-full');
                    document.body.classList.remove('overflow-hidden');

                    if (closeTimer !== null) {
                        window.clearTimeout(closeTimer);
                    }
                    closeTimer = window.setTimeout(() => {
                        closeTimer = null;
                        drawerWrapper.classList.add('hidden');
                        restoreTriggerFocus();
                    }, 300);
                };

                const setLoading = () => {
                    loadingEl?.classList.remove('hidden');
                    contentEl?.classList.add('hidden');
                    errorEl?.classList.add('hidden');
                };

                const setError = () => {
                    loadingEl?.classList.add('hidden');
                    contentEl?.classList.add('hidden');
                    errorEl?.classList.remove('hidden');
                };

                const renderDetail = (data) => {
                    setText('userDrawerTitle', data.name);
                    setText('userDrawerSubtitle', data.email);
                    setText('userDrawerName', data.name);
                    setText('userDrawerEmail', data.email);
                    setText('userDrawerJoinedAt', data.joined_at || 'Belum tercatat');
                    setText('userDrawerAccountCreatedAt', data.account_created_at || '-');

                    const roleBadge = document.getElementById('userDrawerRole');
                    if (roleBadge) {
                        roleBadge.textContent = data.role || '-';
                        roleBadge.className = `inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold ${roleBadgeClasses(data.role_category)}`;
                    }

                    const verificationBadge = document.getElementById('userDrawerVerification');
                    if (verificationBadge) {
                        verificationBadge.textContent = data.is_verified ? 'Terverifikasi' : 'Belum Terverifikasi';
                        verificationBadge.className = `inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold ${verificationBadgeClasses(data.is_verified)}`;
                    }

                    loadingEl?.classList.add('hidden');
                    errorEl?.classList.add('hidden');
                    contentEl?.classList.remove('hidden');
                };

                const onDetailClick = async (event) => {
                    const button = event.currentTarget;
                    const url = button?.dataset.detailUrl;
                    if (!url) {
                        return;
                    }

                    abortActiveRequest();
                    const token = requestToken;

                    const controller = new AbortController();
                    activeController = controller;

                    openDrawer(button);
                    setLoading();

                    try {
                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            signal: controller.signal,
                        });

                        if (!response.ok) {
                            throw new Error('Failed to load user detail');
                        }

                        const data = await response.json();

                        // Ignore a response that lost the race (superseded or aborted).
                        if (token !== requestToken || controller.signal.aborted) {
                            return;
                        }

                        renderDetail(data);
                    } catch (error) {
                        if (error && error.name === 'AbortError') {
                            return;
                        }
                        if (token !== requestToken) {
                            return;
                        }
                        setError();
                    } finally {
                        if (activeController === controller) {
                            activeController = null;
                        }
                    }
                };

                const onKeydown = (event) => {
                    if (!isOpen) {
                        return;
                    }

                    if (event.key === 'Escape') {
                        event.preventDefault();
                        closeDrawer();
                        return;
                    }

                    if (event.key !== 'Tab' || !drawerPanel) {
                        return;
                    }

                    const focusables = Array.from(drawerPanel.querySelectorAll(focusableSelector));
                    if (focusables.length === 0) {
                        event.preventDefault();
                        drawerPanel.focus({ preventScroll: true });
                        return;
                    }

                    const first = focusables[0];
                    const last = focusables[focusables.length - 1];
                    const activeIndex = focusables.indexOf(document.activeElement);

                    if (event.shiftKey) {
                        if (activeIndex <= 0) {
                            event.preventDefault();
                            last.focus({ preventScroll: true });
                        }
                        return;
                    }

                    if (activeIndex === -1 || activeIndex === focusables.length - 1) {
                        event.preventDefault();
                        first.focus({ preventScroll: true });
                    }
                };

                detailButtons.forEach((button) => button.addEventListener('click', onDetailClick));
                closeButtons.forEach((button) => button.addEventListener('click', closeDrawer));
                drawerBackdrop?.addEventListener('click', closeDrawer);
                document.addEventListener('keydown', onKeydown);

                window.__usersDrawerCleanup = () => {
                    abortActiveRequest();

                    if (closeTimer !== null) {
                        window.clearTimeout(closeTimer);
                        closeTimer = null;
                    }

                    isOpen = false;
                    lastTriggerButton = null;

                    detailButtons.forEach((button) => button.removeEventListener('click', onDetailClick));
                    closeButtons.forEach((button) => button.removeEventListener('click', closeDrawer));
                    drawerBackdrop?.removeEventListener('click', closeDrawer);
                    document.removeEventListener('keydown', onKeydown);
                    document.body.classList.remove('overflow-hidden');
                    root.dataset.usersInitialized = 'false';
                };
            }

            if (!window.__usersListenersBound) {
                window.__usersListenersBound = true;
                document.addEventListener('livewire:navigated', initUsersPage);
                document.addEventListener('livewire:navigating', () => {
                    if (typeof window.__usersDrawerCleanup === 'function') {
                        window.__usersDrawerCleanup();
                        window.__usersDrawerCleanup = null;
                    }
                });
            }

            initUsersPage();
        </script>
    </main>
</x-layouts::app>
