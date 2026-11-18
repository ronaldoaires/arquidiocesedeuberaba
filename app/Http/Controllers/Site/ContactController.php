<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\ContactSubmissionRequest;
use App\Jobs\SendContactSubmission;
use App\Models\ContactSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show(Request $request): View
    {
        return view('site.contact', ['kind' => $request->route('kind'), 'title' => $request->route('kind') === 'support' ? 'Solicitar apoio à comunicação' : 'Fale Conosco']);
    }

    public function store(ContactSubmissionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['website'], $data['attachments']);
        $paths = [];
        foreach ($request->file('attachments', []) as $file) {
            $paths[] = ['path' => $file->store('contact-attachments', 'local'), 'name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType(), 'bytes' => $file->getSize()];
        }
        $kind = $request->route('kind');
        $submission = ContactSubmission::create([
            'kind' => $kind, 'name' => $data[$kind === 'support' ? 'contact_name' : 'name'],
            'email' => $data[$kind === 'support' ? 'contact_email' : 'email'],
            'subject' => $data[$kind === 'support' ? 'event_title' : 'subject'],
            'payload' => $data, 'attachments' => $paths,
        ]);
        SendContactSubmission::dispatch($submission->id);

        return back()->with('status', 'Sua mensagem foi recebida com sucesso.');
    }
}
