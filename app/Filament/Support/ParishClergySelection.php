<?php

namespace App\Filament\Support;

use App\Models\ParishClergyAssignment;
use Filament\Forms\Components\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ParishClergySelection extends Field
{
    protected string $view = 'filament.forms.components.parish-clergy-selection';

    protected function setUp(): void
    {
        parent::setUp();
        $this->label('Clero')->columnSpanFull()->dehydrated(false)->saved(true)
            ->default(fn (): array => $this->initialState())
            ->loadStateFromRelationshipsUsing(fn (self $component) => $component->state($component->initialState()))
            ->saveRelationshipsUsing(fn (self $component, Model $record) => $component->saveAssignments($record));
    }

    public static function roles(?string $current = null): array
    {
        $labels = ['Pároco', 'Administrador Paroquial', 'Capelão', 'Vigário Paroquial', 'Diácono Permanente'];
        $options = array_combine($labels, $labels);
        if (filled($current)) {
            $options[$current] = $current;
        }

        return $options;
    }

    public function getExistingRoles(): array
    {
        return $this->getRecord()?->assignments()->pluck('role_text', 'clergy_member_id')->all() ?? [];
    }

    private function initialState(): array
    {
        $state = [];
        foreach ($this->getExistingRoles() as $id => $role) {
            $state[$id] = ['clergy_member_id' => $id, 'selected' => true, 'role_text' => $role ?? ''];
        }

        return $state;
    }

    private function saveAssignments(Model $record): void
    {
        $state = $this->getState() ?? [];
        Validator::make(['assignments' => $state], [
            'assignments' => ['array'],
            'assignments.*.clergy_member_id' => ['required', 'integer', 'distinct', 'exists:clergy_members,id'],
            'assignments.*.selected' => ['required', 'boolean'],
            'assignments.*.role_text' => ['nullable', 'string', 'max:255'],
        ])->validate();
        DB::transaction(function () use ($record, $state): void {
            $keep = [];
            foreach ($state as $item) {
                if (! $item['selected']) {
                    continue;
                }
                $role = $item['role_text'] ?: null;
                $assignment = ParishClergyAssignment::withTrashed()->firstOrNew(['parish_id' => $record->id, 'clergy_member_id' => $item['clergy_member_id']]);
                if ($role !== null && ! array_key_exists($role, self::roles($assignment->role_text))) {
                    throw ValidationException::withMessages([$this->getStatePath() => 'Selecione uma função válida para o clero.']);
                }
                if ($assignment->exists && $assignment->trashed()) {
                    $assignment->restore();
                }
                $assignment->role_text = $role;
                $assignment->save();
                $keep[] = $assignment->id;
            }
            foreach ($record->assignments()->whereNotIn('id', $keep)->get() as $assignment) {
                $assignment->delete();
            }
        });
    }
}
