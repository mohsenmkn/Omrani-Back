<?php


namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\App\Models\User;

class Assessment extends Model
{
    protected $fillable = [
        'cycle_id',
        'period_id',          // ✅ اضافه شد
        'employee_user_id',
        'evaluator_user_id',
        'post_id',
        'status',
        'submitted_at',
        'approved_at',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];



// Assessment
    const STATUS_DRAFT     = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED  = 'approved';
    const STATUS_REJECTED  = 'rejected';


    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_user_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(AssessmentPost::class, 'post_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AssessmentCycle::class, 'cycle_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class);
    }

    public function gaps(): HasMany
    {
        return $this->hasMany(AssessmentGap::class);
    }

    /** میانگین نمرات */
    public function getAverageScoreAttribute(): float
    {
        return round($this->answers()->avg('score') ?? 0, 2);
    }

    /** مجموع گپ وزن‌دار */
    public function getTotalWeightedGapAttribute(): int
    {
        return (int)$this->gaps()->sum('weighted_gap');
    }
}
