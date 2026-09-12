<?php


namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Acl\App\Models\Group;
use Modules\Auth\App\Models\User;

class AssessmentPeriodTarget extends Model
{
    protected $fillable = [
        'period_id',
        'target_type',
        'target_value',
        'evaluator_mode',
        'evaluator_user_id',
    ];

    /* ──────────────────────────────── Relations ──────────────────────────────── */

    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'period_id');
    }

    /**
     * وقتی target_type = 'group' باشد
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'target_value');
    }

    /**
     * ارزیاب مشخص (وقتی evaluator_mode = 'specific')
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_user_id');
    }

    /* ──────────────────────────────── Accessors ──────────────────────────────── */

    public function getTargetLabelAttribute(): string
    {
        if ($this->target_type === 'group') {
            return $this->group?->title ?? "گروه #{$this->target_value}";
        }

        return $this->target_value; // نام خانواده شغلی
    }

    public function getEvaluatorModeLabelAttribute(): string
    {
        return match ($this->evaluator_mode) {
            'auto_manager' => 'مدیر مستقیم (خودکار)',
            'specific' => 'ارزیاب مشخص',
            default => $this->evaluator_mode,
        };
    }
}
