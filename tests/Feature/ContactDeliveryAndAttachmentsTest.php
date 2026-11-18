<?php

use App\Jobs\SendContactSubmission;
use App\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('delivers a stored message once and preserves its delivery state', function (): void {
    $submission = ContactSubmission::create(['kind' => 'contact', 'name' => 'Pessoa', 'email' => 'person@example.test', 'subject' => 'Teste', 'payload' => ['message' => 'Mensagem <segura>'], 'attachments' => []]);
    Mail::shouldReceive('html')->once()->withArgs(fn (string $body, $callback): bool => str_contains($body, '&lt;segura&gt;') && $callback instanceof Closure);
    $job = new SendContactSubmission($submission->id);
    $job->handle();
    $job->handle();
    expect($submission->fresh()->delivery_status)->toBe('sent');
});

it('allows only active administrators to download private attachments', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('contact-attachments/file.pdf', 'private attachment');
    $submission = ContactSubmission::create(['kind' => 'support', 'name' => 'Pessoa', 'email' => 'person@example.test', 'subject' => 'Evento', 'payload' => [], 'attachments' => [['path' => 'contact-attachments/file.pdf', 'name' => 'documento.pdf']]]);
    $url = '/admin/contact-submissions/'.$submission->id.'/attachments/0';
    $this->get($url)->assertForbidden();
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 1]))->get($url)->assertForbidden();
    $this->actingAs(User::factory()->create(['is_active' => true, 'legacy_level' => 3]))->get($url)->assertOk()->assertDownload('documento.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get('/storage/contact-attachments/file.pdf')->assertNotFound();
});
