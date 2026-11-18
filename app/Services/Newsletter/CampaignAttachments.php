<?php

namespace App\Services\Newsletter;

use App\Models\NewsletterCampaign;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CampaignAttachments
{
    public const DIRECTORY = 'newsletter/attachments';

    public static function forMail(NewsletterCampaign $campaign): array
    {
        $files = [];
        foreach ($campaign->attachments ?? [] as $path) {
            if (! self::ownsPath($path) || ! Storage::disk('local')->exists($path)) {
                throw ValidationException::withMessages(['attachments' => 'Um anexo está indisponível. Edite a campanha para removê-lo ou substituí-lo.']);
            }

            $name = basename(str_replace('\\', '/', $campaign->attachment_names[$path] ?? $path));
            $files[] = Attachment::fromStorageDisk('local', $path)->as($name);
        }

        return $files;
    }

    public static function hashes(NewsletterCampaign $campaign): array
    {
        $hashes = [];
        foreach ($campaign->attachments ?? [] as $path) {
            if (! self::ownsPath($path) || ! Storage::disk('local')->exists($path)) {
                throw ValidationException::withMessages(['attachments' => 'Um anexo está indisponível.']);
            }
            $hash = hash_file('sha256', Storage::disk('local')->path($path));
            if ($hash === false) {
                throw ValidationException::withMessages(['attachments' => 'Não foi possível ler um dos anexos.']);
            }
            $hashes[$path] = $hash;
        }
        ksort($hashes);

        return $hashes;
    }

    public static function removeUnused(array $paths): void
    {
        foreach ($paths as $path) {
            if (self::ownsPath($path) && ! NewsletterCampaign::whereJsonContains('attachments', $path)->exists()) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    private static function ownsPath(string $path): bool
    {
        return (bool) preg_match('~^newsletter/attachments/[a-zA-Z0-9_-]+\.[a-zA-Z0-9]+$~D', $path);
    }
}
