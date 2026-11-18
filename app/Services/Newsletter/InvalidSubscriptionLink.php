<?php

namespace App\Services\Newsletter;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\Response;
use RuntimeException;

class InvalidSubscriptionLink extends RuntimeException implements ShouldntReport
{
    public function render(): Response
    {
        return response()->view('site.newsletter.invalid', status: 410);
    }
}
