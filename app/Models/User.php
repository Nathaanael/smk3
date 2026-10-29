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

        return array_values(array_intersect($plants, $this->plantCodes()));
    }

    /**
     * Plant codes stored in users.pabrik ("ckp,ing" -> ['ckp', 'ing']).
     *
     * @return list<string>
     */
    public function plantCodes(): array
    {
        return self::splitPlants($this->pabrik);
    }

    public function plantLabel(): string
    {
        return strtoupper(implode(' + ', $this->plantCodes())) ?: '-';
    }

    public function departmentName(): ?string
    {
        return self::departmentsForPlant($this->pabrik)[$this->departemen] ?? $this->departemen;
    }

    /**
     * Values users.pabrik may hold: single plants plus multi-plant groups.
     *
     * @return array<string, string>
     */
    public static function plantOptions(): array
    {
        return config('smk3.plants') + config('smk3.plant_groups');
    }

    /**
     * Departments available for a users.pabrik value; a multi-plant value gets the
     * departments of each of its plants.
     *
     * @return array<string, string>
     */
    public static function departmentsForPlant(?string $pabrik): array
    {
        $codes = self::splitPlants($pabrik);

        return array_merge(...array_map(fn (string $code) => NonConformity::departmentsFor($code), $codes ?: [null]));
    }

    /**
     * @return list<string>
     */
    private static function splitPlants(?string $pabrik): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $pabrik))));
    }
}
