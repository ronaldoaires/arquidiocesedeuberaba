<?php

namespace App\Livewire\Admin;

use App\Filament\Clusters\Newsletter;
use App\Services\Newsletter\CampaignCatalog;
use Illuminate\View\View;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class NewsletterContentPicker extends Component
{
    use WithoutUrlPagination, WithPagination;

    #[Modelable]
    public array $value = [];

    public array $draft = [];

    public string $type = 'news';

    public string $search = '';

    public bool $isOpen = false;

    public function boot(): void
    {
        abort_unless(Newsletter::canAccess(), 403);
    }

    public function open(): void
    {
        $this->draft = $this->value;
        $this->type = 'news';
        $this->search = '';
        $this->resetPage('contentPage');
        $this->isOpen = true;
        $this->dispatch('open-modal', id: $this->modalId());
    }

    public function selectType(string $type): void
    {
        abort_unless(isset(CampaignCatalog::labels()[$type]), 404);
        $this->type = $type;
        $this->search = '';
        $this->resetPage('contentPage');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('contentPage');
    }

    public function applySelection(): void
    {
        $this->value = array_values($this->draft);
        $this->close();
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->dispatch('close-modal', id: $this->modalId());
    }

    public function remove(int $index): void
    {
        unset($this->value[$index]);
        $this->value = array_values($this->value);
    }

    public function move(int $index, int $direction): void
    {
        $other = $index + $direction;
        if (abs($direction) === 1 && isset($this->value[$index], $this->value[$other])) {
            [$this->value[$index], $this->value[$other]] = [$this->value[$other], $this->value[$index]];
        }
    }

    public function modalId(): string
    {
        return 'newsletter-content-'.$this->getId();
    }

    public function render(CampaignCatalog $catalog): View
    {
        return view('livewire.admin.newsletter-content-picker', [
            'selected' => $catalog->resolve($this->value),
            'results' => $this->isOpen ? $catalog->browse($this->type, $this->search)->paginate(10, pageName: 'contentPage') : null,
            'types' => CampaignCatalog::labels(),
        ]);
    }
}
