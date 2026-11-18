<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\ContentResource;
use App\Models\User;

class UserResource extends ContentResource
{
    protected static ?string $model = User::class;

    public static string $sourceTable = 'usuario';

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
