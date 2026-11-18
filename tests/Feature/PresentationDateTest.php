<?php

use App\Filament\Resources\ClergyMembers\Pages\CreateClergyMember;
use App\Filament\Resources\ClergyMembers\Pages\EditClergyMember;
use App\Models\ClergyMember;
use App\Models\User;
use App\Support\PresentationDate;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(DatabaseMigrations::class);

it('normalizes complete presentation dates without a time component', function (?string $value, ?string $expected): void {
    expect(PresentationDate::toIso($value))->toBe($expected);
})->with([
    [null, null], ['', null], ['  ', null], ['02/02/2022', '2022-02-02'], [' 25/01/2021', '2021-01-25'],
    ['03/04/2024', '2024-04-03'], ['29/02/2024', '2024-02-29'], ['2026-11-14', '2026-11-14'],
]);

it('rejects unsupported incomplete impossible and time-bearing dates', function (string $value): void {
    expect(fn () => PresentationDate::toIso($value))->toThrow(InvalidArgumentException::class);
})->with(['dsafda', 'Junho de 2000', '31/02/2024', '29/02/2023', '0000-00-00', '12/2024', '2024-02-01 10:30:00', '2024-02-01 00:00:00']);

it('converts existing dates while archiving exact original strings and supports a lossless rollback', function (): void {
    $migration = require database_path('migrations/2026_11_14_205453_normalize_clergy_presentation_dates.php');
    $migration->down();
    DB::table('clergy_members')->insert([
        ['id' => 1, 'name' => 'Original', 'presentation_date_text' => ' 25/01/2021', 'visits' => 7],
        ['id' => 2, 'name' => 'Empty', 'presentation_date_text' => '', 'visits' => 8],
        ['id' => 168, 'name' => 'Approved test', 'presentation_date_text' => 'dsafda', 'visits' => 9],
    ]);
    $migration->up();
    expect(Schema::getColumnType('clergy_members', 'presentation_date'))->toBe('date')
        ->and(DB::table('clergy_members')->where('id', 1)->value('presentation_date'))->toBe('2021-01-25')
        ->and(DB::table('clergy_members')->where('id', 1)->value('legacy_presentation_date_text'))->toBe(' 25/01/2021')
        ->and(DB::table('clergy_members')->where('id', 2)->value('presentation_date'))->toBeNull()
        ->and(DB::table('clergy_members')->where('id', 168)->value('presentation_date'))->toBeNull()
        ->and(DB::table('clergy_members')->where('id', 168)->value('legacy_presentation_date_text'))->toBe('dsafda')
        ->and(DB::table('clergy_members')->where('id', 1)->value('visits'))->toBe(7);
    $migration->down();
    expect(DB::table('clergy_members')->where('id', 1)->value('presentation_date_text'))->toBe(' 25/01/2021')
        ->and(DB::table('clergy_members')->where('id', 2)->value('presentation_date_text'))->toBe('')
        ->and(DB::table('clergy_members')->where('id', 168)->value('presentation_date_text'))->toBe('dsafda')
        ->and(Schema::hasColumn('clergy_members', 'presentation_date'))->toBeFalse();
});

it('stops before schema changes when another record has an invalid presentation date', function (): void {
    $migration = require database_path('migrations/2026_11_14_205453_normalize_clergy_presentation_dates.php');
    $migration->down();
    DB::table('clergy_members')->insert(['id' => 4, 'name' => 'Unapproved date', 'presentation_date_text' => 'dsafda']);
    expect(fn () => $migration->up())->toThrow(InvalidArgumentException::class);
    expect(DB::table('clergy_members')->where('id', 4)->value('presentation_date_text'))->toBe('dsafda')
        ->and(Schema::hasColumn('clergy_members', 'presentation_date'))->toBeFalse();
});

it('keeps dates edited after migration when rolling back the operational field', function (): void {
    $migration = require database_path('migrations/2026_11_14_205453_normalize_clergy_presentation_dates.php');
    $migration->down();
    DB::table('clergy_members')->insert(['id' => 1, 'name' => 'Updated date', 'presentation_date_text' => '01/01/2020']);
    $migration->up();
    DB::table('clergy_members')->where('id', 1)->update(['presentation_date' => '2026-11-14']);
    $migration->down();
    expect(DB::table('clergy_members')->where('id', 1)->value('presentation_date_text'))->toBe('14/11/2026');
});

it('uses a date-only picker and persists and renders only the selected calendar day', function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $component = Livewire::test(CreateClergyMember::class);
    $field = $component->instance()->getSchema('form')->getComponentByStatePath('presentation_date');
    expect($field)->toBeInstanceOf(DatePicker::class)->and(Schema::getColumnType('clergy_members', 'presentation_date'))->toBe('date');
    $component->fillForm(['name' => 'Padre da data', 'presentation_date' => '2024-04-03', 'is_active' => true])->call('create')->assertHasNoFormErrors();
    $record = ClergyMember::sole();
    expect($record->presentation_date->format('Y-m-d'))->toBe('2024-04-03')->and($record->toArray()['presentation_date'])->toBe('2024-04-03')->and($record->toArray())->not->toHaveKey('legacy_presentation_date_text');
    $this->get('/clero/'.$record->slug)->assertOk()->assertSee('03/04/2024')->assertDontSee('2024-04-03 00:00:00');
    Livewire::test(EditClergyMember::class, ['record' => $record->id])->fillForm(['presentation_date' => null])->call('save')->assertHasNoFormErrors();
    expect($record->fresh()->presentation_date)->toBeNull();
});

it('rejects invalid or time-bearing presentation date input before saving', function (string $value): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(CreateClergyMember::class)->fillForm(['name' => 'Padre de validação', 'presentation_date' => $value])->call('create')->assertHasFormErrors(['presentation_date']);
    expect(ClergyMember::count())->toBe(0);
})->with(['dsafda', '2024-02-31', '2024-04-03 10:30:00']);
