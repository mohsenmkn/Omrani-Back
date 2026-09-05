<?php


namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentGap extends Model
{
    protected $fillable = [
        'assessment_id', 'question_id', 'required_score', 'actual_score',
        'gap', 'weighted_gap', 'fix_deadline', 'status',
    ];

    protected $casts = ['required_score' => 'integer', 'actual_score' => 'integer'];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AssessmentAction::class, 'gap_id');
    }

    /** آیا اقدام الزامی است؟ (ماتریس ریسک) */
    public function getRequiresActionAttribute(): bool
    {
        $risk = $this->question?->risk_level ?? 0;
        return match (true) {
            $this->gap <= 0 => false,
            $risk >= 4 => $this->gap >= 1,
            $risk === 3 => $this->gap >= 2,
            default => false,
        };
    }

    public function getSeverityLabelAttribute(): string
    {
        if ($this->gap <= 0) return 'بدون گپ';
        $risk = $this->question?->risk_level ?? 0;
        return match (true) {
            $risk >= 4 && $this->gap >= 1 => 'بحرانی',
            $this->gap >= 2 => 'عمده',
            default => 'جزئی',
        };
    }
}
