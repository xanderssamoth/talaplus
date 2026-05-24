<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'api_token',
    'api_key',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'firstname',
        'lastname',
        'surname',
        'partner_name',
        'gender',
        'birthdate',
        'country',
        'city',
        'address_1',
        'address_2',
        'p_o_box',
        'currency',
        'email',
        'phone',
        'email_verified_at',
        'phone_verified_at',
        'username',
        'password',
        'api_token',
        'avatar_url',
        'cover_url',
        'promo_code',
        'tips_at_every_login',
        'is_online',
        'christian_preference',
        'status',
        'type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_email_confirmed_at' => 'datetime',
            'two_factor_phone_confirmed_at' => 'datetime',
            'tips_at_every_login' => 'boolean',
            'is_online' => 'boolean',
            'christian_preference' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot('is_selected')->withTimestamps();
    }

    public function medias(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function messagesSent(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function promoCodes(): HasMany
    {
        return $this->hasMany(PromoCode::class);
    }

    public function followers(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
