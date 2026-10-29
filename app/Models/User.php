<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'departemen',
        'otorisasi',
        'pabrik',
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
            'password' => 'hashed',
            'otorisasi' => Role::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->otorisasi === Role::Admin;
    }

    /**
     * Plant codes this user may see. Admins see every plant; everyone else is
     * limited to users.pabrik, which may list several codes ("ckp,ing").
     *
     * @return list<string>
     */
    public function accessiblePlants(): array
    {
        $plants = array_keys(config('smk3.plants'));

        if ($this->isAdmin()) {
            return $plants;
        }

        $own = array_map('trim', explode(',', (string) $this->pabrik));

        return array_values(array_intersect($plants, $own));
    }
}
