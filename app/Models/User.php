<?php

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An LRMIS account. The users table belongs to LRMIS, so the ticketing system
 * only reads it. Accounts of the LRMIS Administrator type administer the
 * helpdesk and every other account is a member.
 */
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * The columns a constrained eager load needs to display a user by name, photo and position.
     */
    public const string DISPLAY_COLUMNS = 'id,firstname,lastname,extension_name,photo,usertype_id';

    /**
     * The relationships that should always be loaded.
     *
     * @var list<string>
     */
    protected $with = ['usertype'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Get the user's full name as LRMIS displays it.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => collect([$this->firstname, $this->lastname, $this->extension_name])
            ->filter()
            ->implode(' '));
    }

    /**
     * Get the user's position, which is their LRMIS user type such as Teacher or School Head.
     *
     * @return Attribute<string, never>
     */
    protected function position(): Attribute
    {
        return Attribute::get(fn (): string => $this->usertype->type_name);
    }

    /**
     * Get the URL of the user's LRMIS profile photo, or null when they have none.
     *
     * LRMIS stores the photo as a bare filename inside its user_pic folder, as a
     * folder-qualified path, or as an absolute URL, so all three are resolved.
     *
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $photo = trim((string) $this->photo);

            if ($photo === '') {
                return null;
            }

            if (Str::startsWith($photo, ['http://', 'https://'])) {
                return $photo;
            }

            $path = Str::contains($photo, '/') ? ltrim($photo, '/') : 'user_pic/'.$photo;

            return Storage::disk('public')->url($path);
        });
    }

    /**
     * Determine whether the user administers the helpdesk, which LRMIS Administrators do.
     */
    public function isAdmin(): bool
    {
        return $this->usertype->isAdministrator();
    }

    /**
     * Determine whether LRMIS allows the account to sign in.
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Scope the query to users whose name, username or email contains the term.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    #[Scope]
    protected function search(Builder $query, string $term): Builder
    {
        $pattern = '%'.addcslashes(trim($term), '%_\\').'%';

        return $query->where(function (Builder $query) use ($pattern): void {
            $query->whereLike('firstname', $pattern)
                ->orWhereLike('lastname', $pattern)
                ->orWhereLike('username', $pattern)
                ->orWhereLike('email', $pattern);
        });
    }

    /**
     * Get the LRMIS user type, such as Teacher or School Head.
     *
     * @return BelongsTo<Usertype, $this>
     */
    public function usertype(): BelongsTo
    {
        return $this->belongsTo(Usertype::class);
    }

    /**
     * Get the tickets raised by the user.
     *
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get the forum threads started by the user.
     *
     * @return HasMany<ForumThread, $this>
     */
    public function forumThreads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }
}
