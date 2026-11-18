<?php

namespace App\Services\Newsletter;

use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\URL;

class SubscriptionLinks
{
    public static function confirmation(NewsletterSubscriber $subscriber): string
    {
        return self::forSubscriber('confirm', $subscriber);
    }

    public static function profile(NewsletterSubscriber $subscriber): string
    {
        return self::forSubscriber('profile', $subscriber);
    }

    public static function cancellation(NewsletterSubscriber $subscriber): string
    {
        return self::forSubscriber('cancel', $subscriber);
    }

    public static function forCampaign(NewsletterSubscriber $subscriber): array
    {
        $base = rtrim(config('app.url'), '/');
        $parameters = ['key' => $subscriber->link_key];

        return [
            'profile' => $base.URL::signedRoute('site.newsletter.profile', $parameters, absolute: false),
            'cancel' => $base.URL::signedRoute('site.newsletter.cancel', $parameters, absolute: false),
        ];
    }

    private static function forSubscriber(string $page, NewsletterSubscriber $subscriber): string
    {
        $path = URL::temporarySignedRoute(
            'site.newsletter.'.$page,
            now()->addDay(),
            ['key' => $subscriber->link_key],
            absolute: false,
        );

        return rtrim(config('app.url'), '/').$path;
    }
}
