<?php

use App\Models\User;
use App\Support\ContentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('provides a normalized destination for every source table and field', function (): void {
    foreach (ContentRegistry::all() as $source => $module) {
        expect(Schema::hasTable($module['table']))->toBeTrue();
        foreach ($module['columns'] as $column) {
            if ($source === 'usuario' && ! in_array($column['name'], ['id', 'name', 'email', 'password', 'is_active', 'legacy_level', 'created_at', 'updated_at', 'cover_path', 'last_login_at'], true)) {
                expect(Schema::hasTable('user_profiles'))->toBeTrue();
            } else {
                expect(Schema::hasColumn($module['table'], $column['name']))->toBeTrue();
            }
        }
    }
});

it('preserves user passwords without hashing an existing bcrypt value again', function (): void {
    $hash = password_hash('A strong testing password', PASSWORD_BCRYPT);
    $user = User::factory()->create(['is_active' => true, 'legacy_level' => 3]);
    DB::table('users')->where('id', $user->id)->update(['password' => $hash]);
    $user->refresh();
    expect($user->fresh()->password)->toBe($hash);
    expect(password_verify('A strong testing password', $user->password))->toBeTrue();
});
