<x-layouts::auth :title="'Undangan Anggota'">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="'Undangan Anggota'"
            :description="'Terima undangan untuk bergabung dengan bisnis.'"
        />

        @php
            $statusClass = match ($invitation->effectiveStatus()) {
                'pending' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                'accepted' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                'revoked' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                default => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            };
        @endphp

        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ $invitation->business?->name ?? 'Bisnis' }}
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $statusClass }}">
                    {{ $invitation->statusLabel() }}
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                    {{ $invitation->roleLabel() }}
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Ditujukan untuk <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $invitation->email }}</span>
                · berlaku sampai {{ $invitation->expires_at?->translatedFormat('d M Y - H:i') }}
            </p>
        </div>

        @if($state === 'ready')
            <form method="POST" action="{{ route('invitations.accept', ['token' => $token]) }}" class="space-y-3">
                @csrf
                <button
                    type="submit"
                    class="w-full inline-flex items-center justify-center h-11 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    Terima Undangan
                </button>
            </form>
        @elseif($state === 'guest')
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Masuk atau daftar dengan alamat email yang diundang, lalu buka kembali tautan undangan ini.
            </p>
            <div class="flex flex-col sm:flex-row gap-2">
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center justify-center h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    Masuk
                </a>
                <a
                    href="{{ route('register') }}"
                    class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    Daftar Akun Baru
                </a>
            </div>
        @elseif($state === 'unusable')
            <p class="text-sm text-rose-700 dark:text-rose-300">
                Undangan ini sudah tidak berlaku ({{ $invitation->statusLabel() }}).
                Minta pemilik bisnis mengirim undangan baru.
            </p>
        @elseif($state === 'email_mismatch')
            <p class="text-sm text-rose-700 dark:text-rose-300">
                Undangan ini ditujukan untuk alamat email yang berbeda.
                Masuk dengan akun {{ $invitation->email }} untuk menerimanya.
            </p>
        @endif

        @error('invitation')
            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
    </div>
</x-layouts::auth>
