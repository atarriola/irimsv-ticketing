<?php

namespace App\Models;

use Database\Factories\UsertypeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An LRMIS user type, such as Teacher or School Head. The table belongs to
 * LRMIS, so the ticketing system only reads it.
 */
class Usertype extends Model
{
    /** @use HasFactory<UsertypeFactory> */
    use HasFactory, HasUuids;

    /**
     * The level LRMIS gives its Administrator type. Every other level is a
     * school, district, division or region position.
     */
    public const int ADMINISTRATOR_LEVEL = 0;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
        ];
    }

    /**
     * Determine whether this is the LRMIS Administrator type, whose users administer the helpdesk.
     */
    public function isAdministrator(): bool
    {
        return $this->level === self::ADMINISTRATOR_LEVEL;
    }

    /**
     * Get the users of this type.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
