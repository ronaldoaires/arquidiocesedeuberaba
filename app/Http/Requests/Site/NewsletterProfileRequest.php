<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsletterProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', Rule::in(config('newsletter.states'))],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'city' => 'cidade', 'state' => 'estado'];
    }

    protected function getRedirectUrl(): string
    {
        return $this->fullUrl();
    }
}
