<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
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
        'username',
        'password',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
