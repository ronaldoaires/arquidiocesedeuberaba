<?php

namespace App\Jobs;

use App\Models\ContactSubmission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendContactSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $submissionId) {}

    public function handle(): void
    {
        $submission = ContactSubmission::findOrFail($this->submissionId);
        if ($submission->delivery_status === 'sent') {
            return;
        }
        $body = '<h1>'.e($submission->subject).'</h1>';
        foreach ($submission->payload as $field => $value) {
            $label = config('contact.support_fields.'.$field.'.0') ?? ['name' => 'Nome', 'email' => 'E-mail', 'phone' => 'Telefone', 'subject' => 'Assunto', 'message' => 'Mensagem', 'responsibility_accepted' => 'Termo de responsabilidade'][$field] ?? 'Informação';
            $displayValue = config('contact.support_options.'.$field.'.'.$value) ?? ((config('contact.support_fields.'.$field.'.1') === 'checkbox' || is_bool($value)) ? ($value ? 'Sim' : 'Não') : $value);
            $body .= '<p><strong>'.e($label).'</strong>: '.nl2br(e((string) $displayValue)).'</p>';
        }
        Mail::html($body, function ($message) use ($submission): void {
            $message->to(config('contact.recipients.'.$submission->kind))->replyTo($submission->email, $submission->name)->subject($submission->subject);
            foreach ($submission->attachments ?? [] as $path) {
                $attachmentPath = is_array($path) ? $path['path'] : $path;
                $message->attach(Storage::disk('local')->path($attachmentPath), ['as' => is_array($path) ? $path['name'] : basename($path)]);
            }
        });
        $submission->update(['delivery_status' => 'sent']);
    }

    public function failed(?\Throwable $exception): void
    {
        ContactSubmission::whereKey($this->submissionId)->update(['delivery_status' => 'failed']);
    }
}
