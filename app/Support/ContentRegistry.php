<?php

namespace App\Support;

class ContentRegistry
{
    public static function all(): array
    {
        return config('content');
    }

    public static function get(string $source): array
    {
        return self::all()[$source] ?? throw new \InvalidArgumentException('Unknown content module.');
    }

    public static function model(string $source): string
    {
        return 'App\\Models\\'.self::get($source)['model'];
    }
}
