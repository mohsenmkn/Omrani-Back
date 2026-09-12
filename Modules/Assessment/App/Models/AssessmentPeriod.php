<?php


namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;

class AssessmentPeriod extends Model
{
    protected $fillable = [
        'title',
        'year',
        'start_date',
        'end_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /* ──────────────────────────────── Relations ──────────────────────────────── */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AssessmentPeriodTarget::class, 'period_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'period_id');
    }

    /* ──────────────────────────────── Scopes ──────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /* ──────────────────────────────── Accessors ──────────────────────────────── */

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'پیش‌نویس',
            'active' => 'فعال',
            'closed' => 'بسته‌شده',
            default => $this->status,
        };
    }

    public function getTargetsCountAttribute(): int
    {
        return $this->targets()->count();
    }

    public function getAssessmentsCountAttribute(): int
    {
        return $this->assessments()->count();
    }
}
