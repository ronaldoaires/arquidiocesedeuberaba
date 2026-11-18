<?php

namespace App\Livewire\Admin;

use App\Filament\Resources\Parishes\ParishResource;
use App\Filament\Support\ParishClergySelection;
use App\Models\ClergyMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class ClergyPicker extends Component
{
    use WithoutUrlPagination, WithPagination;

    #[Modelable]
    public array $value = [];

    #[Locked]
    public array $existingRoles = [];

    public array $draft = [];

    public string $search = '';

    public bool $isOpen = false;

    public function open(?int $clergyId = null): void
    {
        $this->authorizeAccess();
        $this->draft = $this->value;
        $this->search = $clergyId ? (string) $this->membersQuery()->findOrFail($clergyId)->name : '';
        $this->resetPage('clergyPage');
        $this->resetValidation();
        $this->isOpen = true;
        $this->dispatch('open-modal', id: $this->modalId());
    }

    public function updatedSearch(): void
    {
        $this->resetPage('clergyPage');
    }

    public function toggleMember(int $id): void
    {
        $this->authorizeAccess();
        if (isset($this->draft[$id])) {
            unset($this->draft[$id]);

            return;
        }
        $this->membersQuery()->findOrFail($id);
        $this->draft[$id] = ['clergy_member_id' => $id, 'selected' => true, 'role_text' => $this->existingRoles[$id] ?? ''];
    }

    public function confirm(): void
    {
        $this->authorizeAccess();
        $this->validate([
            'draft' => ['array'],
            'draft.*.clergy_member_id' => ['required', 'integer', 'distinct', 'exists:clergy_members,id'],
            'draft.*.selected' => ['required', 'accepted'],
            'draft.*.role_text' => ['nullable', 'string', 'max:255'],
        ]);
        foreach ($this->draft as $id => $item) {
            if ((int) $id !== (int) $item['clergy_member_id'] || ! array_key_exists($item['role_text'] ?? '', ['' => ''] + ParishClergySelection::roles($this->existingRoles[$id] ?? null))) {
                throw ValidationException::withMessages(['draft.'.$id.'.role_text' => 'Selecione uma função válida para o clero.']);
            }
        }
        $this->value = $this->draft;
        $this->cancel();
    }

    public function cancel(): void
    {
        $this->isOpen = false;
        $this->draft = [];
        $this->dispatch('close-modal', id: $this->modalId());
    }

    public function removeMember(int $id): void
    {
        $this->authorizeAccess();
        unset($this->value[$id]);
    }

    public function modalId(): string
    {
        return 'clergy-picker-'.$this->getId();
    }

    private function membersQuery(): Builder
    {
        return ClergyMember::withTrashed()->where(fn (Builder $query): Builder => $query->whereNull('deleted_at')->orWhereIn('id', array_keys($this->existingRoles)));
    }

    private function authorizeAccess(): void
    {
        abort_unless(ParishResource::canViewAny(), 403);
    }

    public function render(): View
    {
        $this->authorizeAccess();
        $selectedIds = array_keys(array_filter($this->value, fn (array $item): bool => (bool) ($item['selected'] ?? false)));
        $selected = $selectedIds ? $this->membersQuery()->whereIn('id', $selectedIds)->orderBy('name')->get(['id', 'name', 'is_active']) : collect();
        $members = $this->isOpen ? $this->membersQuery()
            ->when(filled($this->search), fn (Builder $query): Builder => $query->where('name', 'like', '%'.addcslashes(mb_substr(trim($this->search), 0, 120), '%_\\').'%'))
            ->orderBy('name')->orderBy('id')->paginate(10, ['id', 'name', 'is_active'], 'clergyPage') : null;

        return view('livewire.admin.clergy-picker', ['members' => $members, 'selected' => $selected]);
    }
}
