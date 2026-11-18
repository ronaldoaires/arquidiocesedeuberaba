<?php

namespace App\Filament\Support;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\StateCasts\RichEditorStateCast;
use Illuminate\Support\Arr;

class PreservingRichEditor extends RichEditor
{
    public function getToolbarButtons(): array
    {
        $groups = parent::getToolbarButtons();
        if (! in_array('fullscreen', Arr::flatten($groups), true)) {
            $groups[] = ['fullscreen'];
        }

        return $groups;
    }

    public function getDefaultStateCasts(): array
    {
        return array_map(
            fn ($cast) => $cast instanceof RichEditorStateCast ? new PreservingRichEditorStateCast($this) : $cast,
            parent::getDefaultStateCasts(),
        );
    }
}
