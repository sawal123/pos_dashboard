@props([
    'variant' => 'auto', // 'auto' | 'blue' | 'white' | 'filled'
])

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 512 512"
    fill="none"
    aria-hidden="true"
    data-nexapos-logo-icon="{{ $variant }}"
    {{ $attributes->merge(['class' => 'shrink-0 transition-colors duration-200']) }}
>
    @if($variant === 'filled')
        <defs>
            <linearGradient id="nexa-bg" x1="48" y1="32" x2="464" y2="480" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#5B55E7" />
                <stop offset="55%" stop-color="#4945D6" />
                <stop offset="100%" stop-color="#3632B8" />
            </linearGradient>
            <linearGradient id="nexa-glow" x1="128" y1="96" x2="384" y2="416" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0.22" />
                <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0" />
            </linearGradient>
            <linearGradient id="nexa-accent" x1="308" y1="133" x2="374" y2="199" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#FF9E2C" />
                <stop offset="100%" stop-color="#FF6B00" />
            </linearGradient>
        </defs>
        <rect x="16" y="16" width="480" height="480" rx="112" fill="url(#nexa-bg)" />
        <rect x="20" y="20" width="472" height="472" rx="108" stroke="url(#nexa-glow)" stroke-width="5" />
        <rect x="96" y="96" width="320" height="320" rx="68" fill="#FFFFFF" fill-opacity="0.08" />
        <line x1="341" y1="245" x2="341" y2="343" stroke="#FFFFFF" stroke-opacity="0.93" stroke-width="58" stroke-linecap="round" />
        <line x1="171" y1="169" x2="171" y2="343" stroke="#FFFFFF" stroke-width="58" stroke-linecap="round" />
        <line x1="171" y1="169" x2="341" y2="343" stroke="#FFFFFF" stroke-width="58" stroke-linecap="round" />
        <circle cx="341" cy="169" r="33" fill="url(#nexa-accent)" />
        <circle cx="341" cy="169" r="13" fill="#FFFFFF" />
    @elseif($variant === 'white')
        <rect x="20" y="20" width="472" height="472" rx="112" fill="#FFFFFF" fill-opacity="0.12" stroke="#FFFFFF" stroke-opacity="0.26" stroke-width="12" />
        <line x1="341" y1="245" x2="341" y2="343" stroke="#FFFFFF" stroke-opacity="0.92" stroke-width="58" stroke-linecap="round" />
        <line x1="171" y1="169" x2="171" y2="343" stroke="#FFFFFF" stroke-width="58" stroke-linecap="round" />
        <line x1="171" y1="169" x2="341" y2="343" stroke="#FFFFFF" stroke-width="58" stroke-linecap="round" />
        <circle cx="341" cy="169" r="34" fill="#FF9E2C" />
        <circle cx="341" cy="169" r="13" fill="#FFFFFF" />
    @elseif($variant === 'blue')
        <rect x="20" y="20" width="472" height="472" rx="112" fill="#4945D6" fill-opacity="0.10" stroke="#4945D6" stroke-opacity="0.24" stroke-width="12" />
        <line x1="341" y1="245" x2="341" y2="343" stroke="#4945D6" stroke-opacity="0.92" stroke-width="58" stroke-linecap="round" />
        <line x1="171" y1="169" x2="171" y2="343" stroke="#4945D6" stroke-width="58" stroke-linecap="round" />
        <line x1="171" y1="169" x2="341" y2="343" stroke="#4945D6" stroke-width="58" stroke-linecap="round" />
        <circle cx="341" cy="169" r="34" fill="#FF7A00" />
        <circle cx="341" cy="169" r="13" fill="#FFFFFF" />
    @else
        {{-- Light Mode (Blue Tone) --}}
        <g class="dark:hidden">
            <rect x="20" y="20" width="472" height="472" rx="112" fill="#4945D6" fill-opacity="0.10" stroke="#4945D6" stroke-opacity="0.24" stroke-width="12" />
            <line x1="341" y1="245" x2="341" y2="343" stroke="#4945D6" stroke-opacity="0.92" stroke-width="58" stroke-linecap="round" />
            <line x1="171" y1="169" x2="171" y2="343" stroke="#4945D6" stroke-width="58" stroke-linecap="round" />
            <line x1="171" y1="169" x2="341" y2="343" stroke="#4945D6" stroke-width="58" stroke-linecap="round" />
            <circle cx="341" cy="169" r="34" fill="#FF7A00" />
            <circle cx="341" cy="169" r="13" fill="#FFFFFF" />
        </g>
        {{-- Dark Mode (White Tone) --}}
        <g class="hidden dark:inline">
            <rect x="20" y="20" width="472" height="472" rx="112" fill="#FFFFFF" fill-opacity="0.12" stroke="#FFFFFF" stroke-opacity="0.26" stroke-width="12" />
            <line x1="341" y1="245" x2="341" y2="343" stroke="#FFFFFF" stroke-opacity="0.92" stroke-width="58" stroke-linecap="round" />
            <line x1="171" y1="169" x2="171" y2="343" stroke="#FFFFFF" stroke-width="58" stroke-linecap="round" />
            <line x1="171" y1="169" x2="341" y2="343" stroke="#FFFFFF" stroke-width="58" stroke-linecap="round" />
            <circle cx="341" cy="169" r="34" fill="#FF9E2C" />
            <circle cx="341" cy="169" r="13" fill="#FFFFFF" />
        </g>
    @endif
</svg>
