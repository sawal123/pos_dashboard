<x-layouts::platform :title="'Detail Audit #' . $auditLog->id">
    <div class="space-y-6">

        {{-- Top Navigation & Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('platform.audit-logs.index') }}"
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 transition-colors"
                    title="Kembali ke Daftar Audit Log"
                >
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                            Catatan Audit #{{ $auditLog->id }}
                        </h1>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-bold border {{ \App\Support\PlatformAuditAction::badgeClass($auditLog->action) }}">
                            {{ \App\Support\PlatformAuditAction::label($auditLog->action) }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Tercatat pada {{ $auditLog->created_at?->format('d M Y, H:i:s T') }} ({{ $auditLog->created_at?->diffForHumans() }})
                    </p>
                </div>
            </div>

            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-mono text-slate-600 dark:text-slate-300 shrink-0">
                <i data-lucide="lock" class="w-3.5 h-3.5 text-slate-400"></i>
                <span>IMMUTABLE &bull; APPEND-ONLY</span>
            </div>
        </div>

        {{-- Immutability & Audit Guarantee Notice --}}
        <div class="p-4 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200/70 dark:border-indigo-800/50 flex items-start gap-3">
            <i data-lucide="shield-check" class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5"></i>
            <div class="text-xs text-indigo-900 dark:text-indigo-200 leading-relaxed">
                <span class="font-bold">Jaminan Jejak Audit Permanen:</span>
                Catatan ini merekam mutasi administratif Platform Admin secara atomik bersamaan dengan eksekusi database.
                Data snapshot disimpan secara permanen dan tidak dapat diubah (immutable) ataupun dihapus oleh operator.
            </div>
        </div>

        {{-- Overview Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- Actor Information --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="user-check" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                        Snapshot Aktor Platform
                    </h2>
                </div>

                <div class="grid grid-cols-3 gap-2 text-xs">
                    <span class="text-slate-500 dark:text-slate-400 font-medium">Nama Aktor</span>
                    <span class="col-span-2 font-bold text-slate-900 dark:text-white">{{ $auditLog->actor_name }}</span>

                    <span class="text-slate-500 dark:text-slate-400 font-medium">Email Aktor</span>
                    <span class="col-span-2 font-mono text-slate-800 dark:text-slate-200">{{ $auditLog->actor_email }}</span>

                    <span class="text-slate-500 dark:text-slate-400 font-medium">ID Pengguna</span>
                    <span class="col-span-2 text-slate-700 dark:text-slate-300">
                        @if($auditLog->actor_user_id)
                            #{{ $auditLog->actor_user_id }}
                            @if($auditLog->actor)
                                <a
                                    href="{{ route('platform.users.show', $auditLog->actor) }}"
                                    class="ml-1 text-indigo-600 dark:text-indigo-400 hover:underline font-bold inline-flex items-center gap-0.5"
                                >
                                    <span>Lihat Profil Pengguna</span>
                                    <i data-lucide="external-link" class="w-3 h-3"></i>
                                </a>
                            @else
                                <span class="ml-1 text-slate-400 italic">(Akun telah dihapus)</span>
                            @endif
                        @else
                            <span class="text-slate-400 italic">Tidak tersedia</span>
                        @endif
                    </span>
                </div>
            </div>

            {{-- Target & Business Context --}}
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="layers" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                        Target &amp; Konteks Bisnis
                    </h2>
                </div>

                <div class="grid grid-cols-3 gap-2 text-xs">
                    <span class="text-slate-500 dark:text-slate-400 font-medium">Tipe Target</span>
                    <span class="col-span-2">
                        <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase">
                            {{ \App\Support\PlatformAuditAction::targetTypeLabel($auditLog->target_type) }}
                        </span>
                        <span class="text-slate-400 font-mono text-[10px] ml-1">({{ $auditLog->target_type }})</span>
                    </span>

                    <span class="text-slate-500 dark:text-slate-400 font-medium">Label Target</span>
                    <span class="col-span-2 font-bold text-slate-900 dark:text-white">
                        {{ $auditLog->target_label ?? '-' }}
                    </span>

                    <span class="text-slate-500 dark:text-slate-400 font-medium">ID Target</span>
                    <span class="col-span-2 font-mono text-slate-800 dark:text-slate-200">
                        {{ $auditLog->target_id ?? '-' }}
                        @if($targetUrl)
                            <a
                                href="{{ $targetUrl }}"
                                class="ml-2 font-sans font-bold text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-0.5 text-xs"
                            >
                                <span>Buka Halaman Target</span>
                                <i data-lucide="external-link" class="w-3 h-3"></i>
                            </a>
                        @endif
                    </span>

                    <span class="text-slate-500 dark:text-slate-400 font-medium">Konteks Bisnis</span>
                    <span class="col-span-2">
                        @if($auditLog->business)
                            <a
                                href="{{ route('platform.businesses.show', $auditLog->business) }}"
                                class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1"
                            >
                                <span>{{ $auditLog->business->name }}</span>
                                <span class="font-normal text-slate-400 text-[10px]">(#{{ $auditLog->business_id }})</span>
                                <i data-lucide="external-link" class="w-3 h-3"></i>
                            </a>
                        @elseif($auditLog->business_id)
                            <span class="text-slate-700 dark:text-slate-300">Bisnis #{{ $auditLog->business_id }} <span class="text-slate-400 italic">(Telah dihapus)</span></span>
                        @else
                            <span class="text-slate-400 italic">Konfigurasi Global Platform (Tanpa Bisnis)</span>
                        @endif
                    </span>
                </div>
            </div>

        </div>

        {{-- Before vs After State Comparison Card --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="git-commit" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                        Perbandingan State (Before &bull; After)
                    </h2>
                </div>
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    Aksi: <code class="font-mono text-slate-700 dark:text-slate-300">{{ $auditLog->action }}</code>
                </span>
            </div>

            @php
                $before = $auditLog->before_state ?? [];
                $after = $auditLog->after_state ?? [];
                $allKeys = array_unique(array_merge(array_keys($before), array_keys($after)));
                sort($allKeys);
            @endphp

            @if(empty($allKeys))
                <div class="p-6 text-center text-xs text-slate-400 italic">
                    Tidak ada atribut state yang dimutasi untuk aksi ini.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold">
                                <th class="py-2.5 px-4 w-1/4">Atribut</th>
                                <th class="py-2.5 px-4 w-3/8 text-rose-600 dark:text-rose-400">Sebelum (Before)</th>
                                <th class="py-2.5 px-4 w-3/8 text-emerald-600 dark:text-emerald-400">Sesudah (After)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-mono">
                            @foreach($allKeys as $key)
                                @php
                                    $hasBefore = array_key_exists($key, $before);
                                    $hasAfter = array_key_exists($key, $after);
                                    $valBefore = $hasBefore ? $before[$key] : null;
                                    $valAfter = $hasAfter ? $after[$key] : null;
                                    $isChanged = ($valBefore !== $valAfter) || ($hasBefore !== $hasAfter);

                                    $formatVal = function ($val, $exists, $attr) {
                                        if (!$exists) {
                                            return '<span class="text-slate-300 dark:text-slate-600 italic">tidak ada</span>';
                                        }
                                        if ($val === null) {
                                            return '<span class="text-slate-400 dark:text-slate-500 italic">null</span>';
                                        }
                                        if (is_bool($val)) {
                                            return $val
                                                ? '<span class="px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 font-bold">true</span>'
                                                : '<span class="px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 font-bold">false</span>';
                                        }
                                        if ($attr === 'price_minor' && is_numeric($val)) {
                                            $formatted = 'Rp ' . number_format($val, 0, ',', '.');
                                            return e((string) $val) . ' <span class="font-sans font-bold text-slate-500 dark:text-slate-400">(' . e($formatted) . ')</span>';
                                        }
                                        return e(is_array($val) ? json_encode($val) : (string) $val);
                                    };
                                @endphp
                                <tr class="{{ $isChanged ? 'bg-amber-50/20 dark:bg-amber-950/10' : '' }}">
                                    <td class="py-2.5 px-4 font-bold text-slate-800 dark:text-slate-200">
                                        <div class="flex items-center gap-1.5">
                                            @if($isChanged)
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" title="Nilai Berubah"></span>
                                            @endif
                                            <span>{{ $key }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-4 text-slate-600 dark:text-slate-300">
                                        {!! $formatVal($valBefore, $hasBefore, $key) !!}
                                    </td>
                                    <td class="py-2.5 px-4 text-slate-900 dark:text-white font-semibold">
                                        {!! $formatVal($valAfter, $hasAfter, $key) !!}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Safe Metadata Card --}}
        @if(!empty($auditLog->metadata))
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="info" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                        Metadata Tambahan (Whitelisted)
                    </h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                    @foreach($auditLog->metadata as $metaKey => $metaVal)
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                            <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 block">
                                {{ $metaKey }}
                            </span>
                            <span class="font-mono font-semibold text-slate-900 dark:text-white mt-0.5 block truncate">
                                @if(is_bool($metaVal))
                                    {{ $metaVal ? 'true' : 'false' }}
                                @elseif($metaVal === null)
                                    null
                                @elseif(is_array($metaVal))
                                    {{ json_encode($metaVal) }}
                                @else
                                    {{ $metaVal }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</x-layouts::platform>
