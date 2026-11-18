<?php

namespace App\Http\Middleware;

use App\Services\Newsletter\InvalidSubscriptionLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateNewsletterLink
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $request->hasValidRelativeSignature()
            ? $next($request)
            : (new InvalidSubscriptionLink)->render();

        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
