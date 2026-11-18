<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <livewire:admin.newsletter-content-picker wire:model="{{ $getStatePath() }}" :key="$getId()" />
</x-dynamic-component>
