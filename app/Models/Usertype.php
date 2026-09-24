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
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

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
