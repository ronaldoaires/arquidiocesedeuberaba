<?php

namespace App\Filament\Clusters;

use App\Models\User;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Pages\Enums\SubNavigationPosition;

class Newsletter extends Cluster
{
    protected static string|\UnitEnum|null $navigationGroup = 'Administração';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canAccessPanel(Filament::getCurrentOrDefaultPanel());
    }
}
