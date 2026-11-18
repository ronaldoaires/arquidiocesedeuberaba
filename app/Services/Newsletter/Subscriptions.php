<?php

namespace App\Services\Newsletter;

use App\Mail\NewsletterAccessMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class Subscriptions
{
    public function sendAccessLink(string $email): void
    {
        $subscriber = NewsletterSubscriber::firstOrCreate(['email' => Str::lower(trim($email))]);
        $requestedAt = now();
        $claimed = NewsletterSubscriber::whereKey($subscriber->id)
            ->where(function ($query): void {
                $query->whereNull('last_requested_at')->orWhere('last_requested_at', '<=', now()->subMinute());
            })->update(['last_requested_at' => $requestedAt]);

        if ($claimed) {
            try {
                Mail::to($subscriber->email)->send(new NewsletterAccessMail($subscriber->refresh()));
            } catch (TransportExceptionInterface $exception) {
                NewsletterSubscriber::whereKey($subscriber->id)->where('last_requested_at', $requestedAt)
                    ->update(['last_requested_at' => null]);
                throw $exception;
            }
        }
    }

    public function find(string $key, bool $active, bool $lock = false): NewsletterSubscriber
    {
        $query = NewsletterSubscriber::where('link_key', $key)
            ->whereIn('status', $active ? [NewsletterSubscriber::ACTIVE] : [NewsletterSubscriber::PENDING, NewsletterSubscriber::UNSUBSCRIBED]);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first() ?? throw new InvalidSubscriptionLink;
    }

    public function confirm(string $key, array $profile): NewsletterSubscriber
    {
        return DB::transaction(function () use ($key, $profile): NewsletterSubscriber {
            $subscriber = $this->find($key, active: false, lock: true);
            $subscriber->fill($profile);
            $subscriber->status = NewsletterSubscriber::ACTIVE;
            $subscriber->verified_at = now();
            $subscriber->unsubscribed_at = null;
            $subscriber->link_key = (string) Str::uuid();
            $subscriber->save();

            return $subscriber;
        });
    }

    public function updateProfile(string $key, array $profile): void
    {
        DB::transaction(fn () => $this->find($key, active: true, lock: true)->update($profile));
    }

    public function unsubscribe(string $key): void
    {
        DB::transaction(function () use ($key): void {
            $subscriber = $this->find($key, active: true, lock: true);
            $subscriber->status = NewsletterSubscriber::UNSUBSCRIBED;
            $subscriber->unsubscribed_at = now();
            $subscriber->link_key = (string) Str::uuid();
            $subscriber->last_requested_at = null;
            $subscriber->save();
        });
    }
}
