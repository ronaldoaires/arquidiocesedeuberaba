<div>
    <x-filament::button type="button" icon="heroicon-o-plus" size="xl" wire:click="open" wire:loading.attr="disabled" wire:target="open">
        Adicionar clero
    </x-filament::button>

    <div style="margin-top: 1rem;">
        @forelse ($selected as $member)
            <div wire:key="selected-clergy-{{ $member->id }}" style="display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: .75rem 0; border-bottom: 1px solid rgba(128,128,128,.2);">
                <div style="flex: 1 1 16rem;">
                    <strong>{{ $member->name }}</strong>
                    @unless($member->is_active)<x-filament::badge color="danger">Inativo</x-filament::badge>@endunless
                    <p>{{ $value[$member->id]['role_text'] ?: 'Sem função definida' }}</p>
                </div>
                <x-filament::icon-button type="button" icon="heroicon-o-pencil-square" size="xl" icon-size="xl" label="Editar função de {{ $member->name }}" wire:click="open({{ $member->id }})" />
                <x-filament::icon-button type="button" icon="heroicon-o-trash" color="danger" size="xl" icon-size="xl" label="Remover {{ $member->name }} da paróquia" wire:click="removeMember({{ $member->id }})" />
            </div>
        @empty
            <p>Nenhum clero adicionado.</p>
        @endforelse
    </div>

    <x-filament::modal :id="$this->modalId()" width="4xl" :close-by-clicking-away="false" x-on:modal-closed="$wire.cancel()">
        <x-slot name="heading">Adicionar clero</x-slot>
        <x-slot name="description">Marque os clérigos e selecione a função de cada um. Os vínculos serão gravados ao salvar a paróquia.</x-slot>

        @if ($isOpen)
            <label for="{{ $this->getId() }}-search" class="fi-fo-field-label">Buscar clero</label>
            <x-filament::input.wrapper>
                <x-filament::input id="{{ $this->getId() }}-search" type="search" wire:model.live.debounce.350ms="search" x-on:keydown.enter.prevent placeholder="Buscar clero..." />
            </x-filament::input.wrapper>
            <div style="margin-top: 1rem;" wire:loading.class="fi-opacity-50" wire:target="search,gotoPage,nextPage,previousPage">
                @forelse ($members as $member)
                    <div wire:key="clergy-option-{{ $member->id }}" style="display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: .75rem 0; border-bottom: 1px solid rgba(128,128,128,.2);">
                        <label style="display: flex; align-items: center; flex: 1 1 16rem; gap: .75rem; min-height: 2.75rem;">
                            <input type="checkbox" class="fi-checkbox-input" wire:click="toggleMember({{ $member->id }})" @checked(isset($draft[$member->id])) />
                            <span>{{ $member->name }}</span>
                            @unless($member->is_active)<x-filament::badge color="danger">Inativo</x-filament::badge>@endunless
                        </label>
                        <div style="flex: 0 1 17rem; width: 100%;">
                            <label for="{{ $this->getId() }}-role-{{ $member->id }}" class="fi-fo-field-label">Função</label>
                            <x-filament::input.wrapper>
                                <select id="{{ $this->getId() }}-role-{{ $member->id }}" class="fi-select-input" style="width: 100%; min-height: 2.75rem;" @disabled(!isset($draft[$member->id]))
                                    @if (isset($draft[$member->id])) wire:model="draft.{{ $member->id }}.role_text" @endif>
                                    <option value="">— (Nenhuma) —</option>
                                    @foreach (\App\Filament\Support\ParishClergySelection::roles($existingRoles[$member->id] ?? null) as $roleValue => $label)
                                        <option value="{{ $roleValue }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-filament::input.wrapper>
                            @error('draft.'.$member->id.'.role_text') <p role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @empty
                    <p>Nenhum clero encontrado.</p>
                @endforelse
            </div>
            <x-filament::pagination :paginator="$members" />
            <p>{{ count($draft) }} clérigo(s) selecionado(s).</p>
        @endif

        <x-slot name="footer">
            <x-filament::button type="button" color="gray" size="lg" wire:click="cancel">Cancelar</x-filament::button>
            <x-filament::button type="button" size="lg" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm">Confirmar seleção</x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
