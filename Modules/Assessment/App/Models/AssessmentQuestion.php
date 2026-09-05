<?php


namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentQuestion extends Model
{
    protected $fillable = [
        'post_id', 'category_id', 'title', 'risk_level',
        'fix_deadline', 'sort_order', 'is_active', 'required_score',
    ];

    protected $casts = ['risk_level' => 'integer', 'is_active' => 'boolean'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(AssessmentPost::class, 'post_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'category_id');
    }

    /** سطح مطلوب = درجه ریسک */
    public function getRequiredScoreAttribute(): ?int
    {
        $value = $this->attributes['required_score'] ?? null;

        if ($value !== null) {
            return (int) $value;
        }

        return $this->risk_level !== null ? (int) $this->risk_level : null;
    }

    public function getRiskLabelAttribute(): string
    {
        return match ($this->risk_level) {
            5 => 'فاجعه‌بار',
            4 => 'بحرانی',
            3 => 'متوسط',
            2 => 'ضعیف',
            1 => 'قابل چشم‌پوشی',
            default => '—',
        };
    }

    public function getFixDeadlineLabelAttribute(): string
    {
        return match ($this->fix_deadline) {
            'immediate' => 'فوری (تا ۶ ماه)',
            'short_term' => 'کوتاه‌مدت (۶ تا ۲ ماه)',
            'mid_term' => 'میان‌مدت (۱ تا ۲ سال)',
            'long_term' => 'بلندمدت (۲ تا ۳ سال)',
            default => '—',
        };
    }

    public function getFixDeadlineMonthsAttribute(): int
    {
        return match ($this->fix_deadline) {
            'immediate' => 6,
            'short_term' => 12,
            'mid_term' => 24,
            'long_term' => 36,
            default => 6,
        };
    }
}
