<?php

use App\Models\ClergyLegacyAffiliation;
use App\Models\ClergyMember;
use App\Models\Parish;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps private profile fields structured and preserves formatting', function (): void {
    $user = User::factory()->create();
    $profile = UserProfile::create(['user_id' => $user->id, 'profile_data' => [], 'tax_id' => '001.002.003-04', 'postal_code' => '00123-000']);
    expect($user->profile->tax_id)->toBe('001.002.003-04')->and($profile->postal_code)->toBe('00123-000');
});

it('stores legacy parish slots separately from current assignments', function (): void {
    $clergy = ClergyMember::factory()->create(['name' => 'Clérigo']);
    $parish = Parish::factory()->create(['name' => 'Paróquia']);
    ClergyLegacyAffiliation::factory()->create(['clergy_member_id' => $clergy->id, 'slot' => 1, 'parish_id' => $parish->id, 'parish_text' => 'Referência original', 'role_text' => 'Pároco']);
    expect($clergy->assignments()->count())->toBe(0);
    expect(ClergyLegacyAffiliation::first()->parish->id)->toBe($parish->id);
});
