<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-settings.layout :heading="__('Appearance')" :subheading=" __('Update the appearance settings for your account')">
        <flux:radio.group
            x-data
            x-init="$watch('$flux.appearance', val => {
                if (val === 'dark' || val === 'light') {
                    try { localStorage.setItem('nexa-theme', val); } catch (e) {}
                }
            })"
            variant="segmented"
            x-model="$flux.appearance"
        >
            <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
            <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
            <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</section>
