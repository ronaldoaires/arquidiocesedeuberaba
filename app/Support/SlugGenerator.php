<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SlugGenerator
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function unique(
        string $modelClass,
        string $title,
        ?Model $ignoreRecord = null,
        string $field = 'slug',
        string $separator = '-',
        ?Closure $queryModifier = null,
    ): string {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Cannot generate a slug from an empty title.');
        }
        $base = Str::substr(Str::slug(trim($title), $separator) ?: 'record', 0, 255);
        $slug = $base;
        $attempt = 1;
        while ($modelClass::withoutGlobalScopes()->where($field, $slug)
            ->when($ignoreRecord?->exists, fn ($query) => $query->whereKeyNot($ignoreRecord->getKey()))
            ->when($queryModifier, fn ($query) => $queryModifier($query))->exists()) {
            $suffix = $separator.$attempt++;
            $slug = rtrim(Str::substr($base, 0, 255 - Str::length($suffix)), $separator).$suffix;
        }

        return $slug;
    }
}
