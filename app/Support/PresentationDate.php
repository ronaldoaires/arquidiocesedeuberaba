<?php

namespace App\Support;

use DateTimeImmutable;
use InvalidArgumentException;

class PresentationDate
{
    public static function toIso(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format($format) === $value && (int) $date->format('Y') >= 1000) {
                return $date->format('Y-m-d');
            }
        }
        throw new InvalidArgumentException('Presentation date is not a complete, valid calendar date.');
    }
}
