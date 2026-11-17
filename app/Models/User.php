<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'google_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'password_set_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // Any password change marks it as chosen by the person, unless the caller says otherwise
        // (a Google sign-up stores a random one and sets password_set_at to null explicitly).
        static::saving(function (User $user) {
            if ($user->isDirty('password') && ! $user->isDirty('password_set_at')) {
                $user->password_set_at = $user->password ? now() : null;
            }
        });
    }

    /** Has the person a password of their own (else Settings → Password sets one without the current one)? */
    public function hasOwnPassword(): bool
    {
        return $this->password_set_at !== null && ! empty($this->password);
    }

    public function smsGroups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SmsGroup::class);
    }

    public function smsTemplates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SmsTemplate::class);
    }

    public function smsHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SmsHistory::class);
    }
}
