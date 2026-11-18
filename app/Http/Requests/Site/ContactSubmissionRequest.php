<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['website' => ['nullable', 'prohibited']];
        if ($this->route('kind') === 'contact') {
            return $rules + [
                'name' => ['required', 'string', 'max:191'], 'email' => ['required', 'email', 'max:191'],
                'phone' => ['nullable', 'string', 'max:50'], 'subject' => ['required', 'string', 'max:255'],
                'message' => ['required', 'string', 'max:30000'],
            ];
        }
        foreach (config('contact.support_fields') as $name => [$label,$type,$required]) {
            $rules[$name] = [$required ? 'required' : 'nullable'];
            $rules[$name][] = match ($type) {
                'checkbox' => 'boolean', 'email' => 'email', 'date' => 'date', default => 'string'
            };
            if ($type === 'select') {
                $rules[$name][] = Rule::in(array_keys(config('contact.support_options.'.$name, [])));
            }
            if (! in_array($type, ['checkbox', 'date'], true)) {
                $rules[$name][] = 'max:'.($type === 'textarea' ? 30000 : 255);
            }
        }
        $rules['responsibility_accepted'] = ['required', 'accepted'];
        $rules['attachments'] = ['nullable', 'array', 'max:10'];
        $rules['attachments.*'] = ['file', 'max:51200', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip,rar,mp3,mp4,mov'];

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = ['name' => 'nome', 'email' => 'e-mail', 'phone' => 'telefone', 'subject' => 'assunto', 'message' => 'mensagem', 'responsibility_accepted' => 'termo de responsabilidade', 'attachments' => 'anexos', 'attachments.*' => 'anexo'];
        foreach (config('contact.support_fields') as $name => [$label]) {
            $attributes[$name] = $label;
        }

        return $attributes;
    }
}
