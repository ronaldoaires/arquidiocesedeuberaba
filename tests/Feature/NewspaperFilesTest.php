<?php

use App\Filament\Resources\Newspapers\Pages\ManageNewspapers;
use App\Models\Newspaper;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    config(['migration.record_visits' => true]);
    Storage::disk('public')->put('newspapers/sample.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
});

function newspaperFileRecord(array $attributes = []): Newspaper
{
    return Newspaper::factory()->create([...['title' => 'Correio de teste', 'slug' => 'correio-teste', 'file_path' => 'newspapers/sample.pdf', 'is_active' => true, 'month' => 1, 'year' => 2026, 'edition' => '015', 'visits' => 12, 'downloads' => 7], ...$attributes]);
}

it('counts one PDF reading while subsequent file and range requests do not count again', function (): void {
    $record = newspaperFileRecord();
    $original = $record->fresh()->getRawOriginal();
    $this->get($record->public_url)->assertOk();
    expect($record->fresh()->visits)->toBe(12);
    $response = $this->get(route('site.newspapers.preview', $record->slug))->assertRedirect(route('media.show', ['path' => $record->file_path]));
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->get(route('media.show', ['path' => $record->file_path]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->withHeader('Range', 'bytes=0-9')->get(route('media.show', ['path' => $record->file_path]))->assertStatus(206);
    $record->refresh();
    expect($record->visits)->toBe(13)->and($record->downloads)->toBe(7)->and($record->last_visited_at)->not->toBeNull();
    foreach (['title', 'slug', 'year', 'edition', 'updated_at', 'body_html', 'file_path'] as $field) {
        expect($record->getRawOriginal($field))->toBe($original[$field]);
    }
});

it('counts downloads separately and forces attachment delivery', function (): void {
    $record = newspaperFileRecord();
    $response = $this->get(route('site.newspapers.download', $record->slug))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($record->fresh()->visits)->toBe(12)->and($record->fresh()->downloads)->toBe(8);
});

it('initializes null counters on a successful read or download without resetting existing values', function (): void {
    $record = newspaperFileRecord(['visits' => null, 'downloads' => null]);
    $record->update(['visits' => null, 'downloads' => null]);
    $this->get(route('site.newspapers.preview', $record->slug))->assertRedirect();
    $this->get(route('site.newspapers.download', $record->slug))->assertOk();
    expect($record->fresh()->visits)->toBe(1)->and($record->fresh()->downloads)->toBe(1);
});

it('does not count HEAD range conditional or disabled smoke-test requests', function (): void {
    $record = newspaperFileRecord();
    $this->head(route('site.newspapers.preview', $record->slug))->assertRedirect();
    $this->head(route('site.newspapers.download', $record->slug))->assertOk();
    $this->withHeader('Range', 'bytes=0-9')->get(route('site.newspapers.download', $record->slug))->assertStatus(206);
    $this->flushHeaders();
    $etag = $this->get(route('media.show', ['path' => $record->file_path]))->headers->get('ETag');
    $this->withHeader('If-None-Match', $etag)->get(route('site.newspapers.download', $record->slug))->assertStatus(304);
    $this->flushHeaders();
    config(['migration.record_visits' => false]);
    $this->get(route('site.newspapers.preview', $record->slug))->assertRedirect();
    $this->get(route('site.newspapers.download', $record->slug))->assertOk();
    expect($record->fresh()->visits)->toBe(12)->and($record->fresh()->downloads)->toBe(7);
});

it('rejects missing inactive and deleted newspapers without counting accesses', function (): void {
    $record = newspaperFileRecord(['is_active' => false]);
    foreach (['preview', 'download'] as $action) {
        $this->get(route('site.newspapers.'.$action, $record->slug))->assertNotFound();
        $this->get(route('site.newspapers.'.$action, 'missing'))->assertNotFound();
    }
    expect($record->fresh()->visits)->toBe(12)->and($record->fresh()->downloads)->toBe(7);
    $record->update(['is_active' => true]);
    $record->delete();
    $this->get(route('site.newspapers.download', $record->slug))->assertNotFound();
});

it('rejects missing unsafe and disguised files before recording an access', function (string $path): void {
    Storage::disk('public')->put('newspapers/fake.pdf', '<?php echo "blocked";');
    $record = newspaperFileRecord(['file_path' => $path]);
    foreach (['preview', 'download'] as $action) {
        $this->get(route('site.newspapers.'.$action, $record->slug))->assertNotFound();
    }
    expect($record->fresh()->visits)->toBe(12)->and($record->fresh()->downloads)->toBe(7);
})->with(['newspapers/missing.pdf', 'newspapers/fake.pdf', '../private/secret.pdf', '', '.env']);

it('renders a single global file modal and distinct preview and download links without changing counters', function (): void {
    $record = newspaperFileRecord();
    $response = $this->get('/correio-catolico')->assertOk()->assertSee('data-file-preview', false)->assertSee('data-file-download', false)
        ->assertSee(route('site.newspapers.preview', $record->slug), false)->assertSee(route('site.newspapers.download', $record->slug), false);
    expect(substr_count($response->getContent(), 'id="file-dialog"'))->toBe(1);
    $this->get($record->public_url)->assertOk()->assertSee('Abrir arquivo')->assertSee('Baixar arquivo')->assertSee('data-file-preview', false);
    expect($record->fresh()->visits)->toBe(12)->and($record->fresh()->downloads)->toBe(7);
});

it('shows the combined month year and the existing read-only counters in the admin', function (): void {
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $record = newspaperFileRecord();
    $later = newspaperFileRecord(['slug' => 'later', 'month' => 12, 'year' => 2027]);
    Livewire::test(ManageNewspapers::class)->assertTableColumnDoesNotExist('month')->assertTableColumnDoesNotExist('year')
        ->assertTableColumnStateSet('publication_period', 'janeiro/2026', $record)
        ->assertTableColumnStateSet('visits', 12, $record)->assertTableColumnStateSet('downloads', 7, $record)
        ->sortTable('publication_period', 'asc')->assertCanSeeTableRecords([$record, $later], inOrder: true)
        ->mountAction('create')->assertFormFieldDoesNotExist('visits')->assertFormFieldDoesNotExist('downloads')
        ->assertSchemaStateSet(['year' => now('America/Sao_Paulo')->year]);
    Livewire::test(ManageNewspapers::class)->mountAction(TestAction::make('edit')->table($record))
        ->assertSchemaStateSet(['year' => 2026, 'month' => 1, 'edition' => '015']);
});
