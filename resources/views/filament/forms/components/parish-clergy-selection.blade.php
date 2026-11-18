<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <livewire:admin.clergy-picker
        wire:model="{{ $getStatePath() }}"
        :existing-roles="$getExistingRoles()"
        :key="$getId()"
    />
</x-dynamic-component>
