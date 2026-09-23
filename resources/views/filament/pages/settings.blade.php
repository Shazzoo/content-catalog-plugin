<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
    </form>

    {{ $this->table }}

    @if ($newApiKey)
        <x-filament::section class="mt-6" heading="New API key" description="Copy this key now. It is stored only as a hash and cannot be recovered later.">
            <div>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" readonly :value="$newApiKey" />
                </x-filament::input.wrapper>

                <x-filament::button class="mt-3" x-on:click="navigator.clipboard.writeText(@js($newApiKey))" type="button">
                    Copy API key
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
