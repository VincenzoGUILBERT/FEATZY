<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasApiTokens, HasFactory, HasRoles, InteractsWithMedia, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'dietary_preferences',
        'notification_preferences',
    ];

    /**
     * Canonical notification channels (all default to enabled).
     *
     * @var list<string>
     */
    public const NOTIFICATION_PREFERENCES = ['email', 'push', 'important_updates', 'promotions'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deleted_at' => 'datetime',
            'password' => 'hashed',
            'dietary_preferences' => 'array',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * The user's full name (first + last). Used notably by Cashier (stripeName).
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * Notification settings normalized to every channel (missing keys default to true).
     *
     * @return array<string, bool>
     */
    public function notificationPreferences(): array
    {
        $stored = $this->notification_preferences ?? [];

        return collect(self::NOTIFICATION_PREFERENCES)
            ->mapWithKeys(fn (string $key): array => [$key => (bool) ($stored[$key] ?? true)])
            ->all();
    }

    /**
     * Register the media collections for the user.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }

    /**
     * Friend groups owned by the user.
     *
     * @return HasMany<FriendGroup, $this>
     */
    public function friendGroups(): HasMany
    {
        return $this->hasMany(FriendGroup::class, 'owner_id');
    }

    /**
     * Friend groups the user is a member of.
     *
     * @return BelongsToMany<FriendGroup, $this>
     */
    public function memberFriendGroups(): BelongsToMany
    {
        return $this->belongsToMany(FriendGroup::class, 'friend_group_user')
            ->withTimestamps();
    }

    /**
     * Restaurants owned by the user.
     *
     * @return HasMany<Restaurant, $this>
     */
    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class, 'owner_id');
    }

    /**
     * Reservations organized by the user.
     *
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'organizer_id');
    }

    /**
     * Reservation participations of the user (organizer or invited guest).
     *
     * @return HasMany<ReservationParticipant, $this>
     */
    public function participations(): HasMany
    {
        return $this->hasMany(ReservationParticipant::class);
    }

    /**
     * Reviews written by the user.
     *
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Restaurants the user has favorited.
     *
     * @return BelongsToMany<Restaurant, $this>
     */
    public function favoriteRestaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'favorites')
            ->withTimestamps();
    }

    /**
     * Payments made by the user.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payer_user_id');
    }
}
