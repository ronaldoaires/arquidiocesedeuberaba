<?php

use App\Models\User;
use Filament\Panel;

it('allows only active legacy administrators into the admin panel', function (bool $isActive, ?int $legacyLevel, bool $allowed): void {
    $user = User::factory()->make();
    $user->forceFill(['is_active' => $isActive, 'legacy_level' => $legacyLevel]);

    expect($user->canAccessPanel(Panel::make()->id('admin')))->toBe($allowed)
        ->and($user->canAccessPanel(Panel::make()->id('other')))->toBeFalse();
})->with([
    'active administrator' => [true, 3, true],
    'inactive administrator' => [false, 3, false],
    'regular user' => [true, 1, false],
    'unclassified user' => [true, null, false],
]);
