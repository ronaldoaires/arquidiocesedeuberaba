<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const UNSUBSCRIBED = 'unsubscribed';

    public const STATUS_LABELS = [
        self::PENDING => 'Pendente',
        self::ACTIVE => 'Ativo',
        self::UNSUBSCRIBED => 'Cancelado',
    ];

    protected $fillable = ['email', 'name', 'city', 'state'];

    protected $hidden = ['link_key'];

    protected $attributes = ['status' => self::PENDING];

    protected static function booted(): void
    {
        static::creating(function (self $subscriber): void {
            $subscriber->link_key = (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'last_requested_at' => 'datetime',
        ];
    }
}
