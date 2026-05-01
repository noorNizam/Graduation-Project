<?php

namespace App\Infrastructure\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Role Constants
    public const ROLE_USER = 'user';
    public const ROLE_ADMIN = 'admin';

    /**
     * Available roles for validation
     */
    public static function getRoles(): array
    {
        return [
            self::ROLE_USER,
            self::ROLE_ADMIN,
        ];
    }

    protected $fillable = [
        'full_name',
        'current_job',
        'address',
        'gender',
        'email',
        'password',
        'phone_number',
        'birth_date',
        'profile_picture',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->full_name ?? '';
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isUser(): bool
    {
        return $this->hasRole(self::ROLE_USER);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function scopeRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeUsers($query)
    {
        return $query->role(self::ROLE_USER);
    }

    public function scopeAdmins($query)
    {
        return $query->role(self::ROLE_ADMIN);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ==================== Relationships ====================

    /**
     * Get all wallets belonging to the user.
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(WalletModel::class, 'user_id');
    }

    /**
     * Get all servings created by the user.
     */
    public function servings(): HasMany
    {
        return $this->hasMany(Serving::class, 'user_id');
    }

    // /**
    //  * Get all services created by the user.
    //  */
    // public function services(): HasMany
    // {
    //     return $this->hasMany(ServiceModel::class, 'user_id');
    // }

    // /**
    //  * Get all requests made by the user.
    //  */
    // public function requests(): HasMany
    // {
    //     return $this->hasMany(RequestModel::class, 'user_id');
    // }

    // /**
    //  * Get all complaints filed by the user.
    //  */
    // public function complaintsFiled(): HasMany
    // {
    //     return $this->hasMany(ComplaintModel::class, 'complainant_id');
    // }

    // /**
    //  * Get all complaints received by the user.
    //  */
    // public function complaintsReceived(): HasMany
    // {
    //     return $this->hasMany(ComplaintModel::class, 'respondent_id');
    // }

    // /**
    //  * Get all ratings given by the user.
    //  */
    // public function ratingsGiven(): HasMany
    // {
    //     return $this->hasMany(RatingModel::class, 'rater_id');
    // }

    // /**
    //  * Get all ratings received by the user.
    //  */
    // public function ratingsReceived(): HasMany
    // {
    //     return $this->hasMany(RatingModel::class, 'rated_id');
    // }

    // /**
    //  * Get all rewards earned by the user.
    //  */
    // public function rewards(): HasMany
    // {
    //     return $this->hasMany(RewardModel::class, 'user_id');
    // }

    // /**
    //  * Get all activity logs for the user.
    //  */
    // public function activityLogs(): HasMany
    // {
    //     return $this->hasMany(ActivityLogModel::class, 'user_id');
    // }

    // /**
    //  * Get all notifications for the user.
    //  */
    // public function notifications(): HasMany
    // {
    //     return $this->hasMany(NotificationModel::class, 'user_id');
    // }

    // /**
    //  * Get all chats the user participates in.
    //  */
    // public function chats(): HasMany
    // {
    //     return $this->hasMany(ChatModel::class, 'user_id');
    // }

    // /**
    //  * Get all messages sent by the user.
    //  */
    // public function messages(): HasMany
    // {
    //     return $this->hasMany(MessageModel::class, 'sender_id');
    // }
}
