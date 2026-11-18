<?php

use App\Jobs\SendContactSubmission;
use App\Models\ContactSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    Storage::fake('local');
});

it('persists a validated message and queues delivery without sending during the request', function (): void {
    $this->from('/contato')->post('/contato', ['name' => 'Pessoa', 'email' => 'person@example.test', 'subject' => 'Contato de teste', 'message' => 'Mensagem preservada'])
        ->assertRedirect('/contato')->assertSessionHas('status');
    $this->assertDatabaseHas('contact_submissions', ['subject' => 'Contato de teste', 'delivery_status' => 'pending']);
    Queue::assertPushed(SendContactSubmission::class);
});

it('validates invalid submissions and does not enqueue them', function (): void {
    $this->post('/contato', ['name' => '', 'email' => 'invalid'])->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
    Queue::assertNothingPushed();
    expect(ContactSubmission::count())->toBe(0);
});

it('stores support attachments privately with all supplied fields', function (): void {
    $data = [];
    foreach (config('contact.support_fields') as $field => [$label,$type,$required]) {
        if (! $required) {
            continue;
        }
        $data[$field] = match ($type) {
            'email' => 'person@example.test','date' => '2026-12-01','select' => 'mass',default => 'Informação de teste'
        };
    }
    $data['responsibility_accepted'] = '1';
    $data['attachments'] = [UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')];
    $this->from('/solicitar')->post('/solicitar', $data)->assertRedirect('/solicitar')->assertSessionHasNoErrors();
    $submission = ContactSubmission::firstOrFail();
    Storage::disk('local')->assertExists($submission->attachments[0]['path']);
    $this->get('/storage/'.$submission->attachments[0]['path'])->assertNotFound();
    expect($submission->payload['event_title'])->toBe('Informação de teste');
});
