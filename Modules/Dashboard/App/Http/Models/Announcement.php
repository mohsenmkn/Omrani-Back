<?php

namespace Modules\Dashboard\App\Http\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Auth\App\Models\User;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'is_active',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * کاربران این اعلان را خوانده‌اند
     */
    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    /**
     * Scope: فقط اعلانات فعال
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    /**
     * بررسی اینکه آیا کاربر این اعلان را خوانده است
     */
    public function isReadBy($user): bool
    {
        return $this->readers()->where('user_id', $user->id)->exists();
    }
}
