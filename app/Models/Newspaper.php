<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Newspaper extends ContentModel
{
    protected $table = 'newspapers';

    public static string $sourceTable = 'jornal';

    public function getPublicUrlAttribute(): ?string
    {
        return filled($this->slug) ? route('site.show.newspapers', ['slug' => $this->slug]) : null;
    }

    public function getPublicationPeriodAttribute(): string
    {
        $months = [1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
            7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'];

        return collect([$months[$this->month] ?? $this->month, $this->year])->filter(fn ($value): bool => filled($value))->implode('/');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
