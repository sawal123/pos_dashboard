@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'NexaPOS')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center">
            <x-app-logo-icon class="size-9" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'NexaPOS')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center">
            <x-app-logo-icon class="size-9" />
        </x-slot>
    </flux:brand>
@endif
