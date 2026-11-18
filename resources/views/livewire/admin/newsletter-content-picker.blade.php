<div>
    <x-filament::button type="button" color="gray" wire:click="open">Adicionar conteúdo do site</x-filament::button>
    @if($value)
        <ol style="margin-top:1rem">
            @foreach($value as $index => $key)
                @php($item = is_string($key) ? $selected->get($key) : null)
                <li wire:key="selection-{{ $item?->getTable() }}-{{ $item?->id ?? $index }}" style="display:flex;align-items:center;flex-wrap:wrap;gap:.5rem;padding:.6rem 0;border-bottom:1px solid #8884">
                    <span style="flex:1 1 14rem">{{ $loop->iteration }}. {{ $item?->display_title ?? 'Conteúdo indisponível' }}</span>
                    <x-filament::icon-button type="button" icon="heroicon-o-arrow-up" label="Mover para cima" :disabled="$loop->first" wire:click="move({{ $index }}, -1)" />
                    <x-filament::icon-button type="button" icon="heroicon-o-arrow-down" label="Mover para baixo" :disabled="$loop->last" wire:click="move({{ $index }}, 1)" />
                    <x-filament::icon-button type="button" icon="heroicon-o-x-mark" color="danger" label="Remover conteúdo" wire:click="remove({{ $index }})" />
                </li>
            @endforeach
        </ol>
    @endif
    <x-filament::modal :id="$this->modalId()" width="3xl" :close-by-clicking-away="false">
        <x-slot name="heading">Adicionar conteúdo do site</x-slot>
        @if($isOpen)
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem">
                @foreach($types as $typeKey => $label)
                    <x-filament::button type="button" :color="$type === $typeKey ? 'primary' : 'gray'" aria-pressed="{{ $type === $typeKey ? 'true' : 'false' }}" wire:click="selectType('{{ $typeKey }}')">{{ $label }}</x-filament::button>
                @endforeach
            </div>
            <label for="search-{{ $this->getId() }}">Buscar por nome ou título</label>
            <x-filament::input.wrapper><x-filament::input type="search" id="search-{{ $this->getId() }}" wire:model.live.debounce.300ms="search" x-on:keydown.enter.prevent /></x-filament::input.wrapper>
            @forelse($results as $result)
                <label wire:key="option-{{ $type }}-{{ $result->id }}" style="display:flex;gap:.75rem;align-items:center;min-height:2.75rem;border-bottom:1px solid #8884">
                    <input type="checkbox" class="fi-checkbox-input" wire:model.live="draft" value="{{ $type }}:{{ $result->id }}">
                    <span>{{ $result->display_title }}
                        @if($type === 'newspapers')
                            @if(filled($result->edition))<small> · Edição {{ $result->edition }}</small>@endif
                            @if(filled($result->publication_period))<small> · {{ $result->publication_period }}</small>@endif
                        @elseif(!in_array($type, ['parishes', 'institutes'], true) && ($date = ($type === 'events' ? $result->starts_on : $result->created_at)))
                            <small> · {{ $date->format('d/m/Y') }}</small>
                        @endif
                    </span>
                </label>
            @empty
                <p>Nenhum conteúdo encontrado.</p>
            @endforelse
            <x-filament::pagination :paginator="$results" />
        @endif
        <x-slot name="footer">
            <x-filament::button type="button" color="gray" wire:click="close">Cancelar</x-filament::button>
            <x-filament::button type="button" wire:click="applySelection">Aplicar seleção ({{ count($draft) }})</x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
