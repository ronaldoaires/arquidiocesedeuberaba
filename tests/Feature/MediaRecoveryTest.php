<?php

use App\Services\Migration\LegacyMediaRecovery;

it('limits recovery URLs to known hosts and safe upload paths', function (): void {
    $recovery = app(LegacyMediaRecovery::class);
    expect($recovery->path('https://arquidiocesedeuberaba.com.br/uploads/summernote/file.jpg'))->toBe('uploads/summernote/file.jpg')
        ->and($recovery->path('http://127.0.0.1/private'))->toBeNull()
        ->and($recovery->path('https://example.test/uploads/image.jpg'))->toBeNull()
        ->and($recovery->path('https://arquidiocesedeuberaba.com.br/uploads/%2e%2e/.env'))->toBeNull();
});
