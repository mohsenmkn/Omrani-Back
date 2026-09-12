<?php

namespace Modules\Assessment\App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentPeriod;
use Modules\Assessment\App\Models\AssessmentPeriodTarget;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\HR\App\Models\EmployeePosition;

class AssessmentBulkService
{
    public function __construct(
        private JobFamilyClassifier $classifier
    ) {}

    /**
     * تولید انبوه ارزیابی برای یک دوره
     */
    public function generate(AssessmentPeriod $period): array
    {
        $result = [
            'created' => 0,
            'skipped' => 0,
            'errors'  => [],
        ];

        $targets = $period->targets()->with('group.users')->get();

        foreach ($targets as $target) {
            try {
                $employees = $this->resolveEmployees($target);

                foreach ($employees as $employee) {
                    $outcome = $this->createAssessmentForEmployee(
                        $period,
                        $target,
                        $employee
                    );

                    $result[$outcome]++;
                }
            } catch (\Throwable $e) {
                Log::error("Bulk assessment error for target #{$target->id}", [
                    'error' => $e->getMessage(),
                ]);

                $result['errors'][] = [
                    'target'  => $target->target_label,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * حل لیست کارمندان از روی هدف
     */
    private function resolveEmployees(AssessmentPeriodTarget $target)
    {
        // هدف: گروه
        if ($target->target_type === 'group') {
            return $target->group?->users ?? collect();
        }

        // هدف: خانواده شغلی
        if ($target->target_type === 'family') {
            return EmployeePosition::whereNotNull('post_title')
                ->get()
                ->filter(fn ($p) => $this->classifier->classify($p->post_title) === $target->target_value)
                ->map(fn ($p) => $p->user)
                ->filter();
        }

        return collect();
    }

    /**
     * ساخت ارزیابی برای یک کارمند
     */
    private function createAssessmentForEmployee(
        AssessmentPeriod $period,
        AssessmentPeriodTarget $target,
        $employee
    ): string {
        // جلوگیری از تکرار
        $exists = Assessment::where('period_id', $period->id)
            ->where('employee_user_id', $employee->id)
            ->exists();

        if ($exists) {
            return 'skipped';
        }

        // یافتن شناسنامه شایستگی
        $position = EmployeePosition::where('user_id', $employee->id)->first();
        $family   = $position
            ? $this->classifier->classify($position->post_title)
            : null;

        $post = $family
            ? AssessmentPost::where('grade', $family)->where('is_active', true)->first()
            : null;

        if (!$post) {
            return 'skipped';
        }

        // تعیین ارزیاب
        $evaluatorId = $this->resolveEvaluator($target, $employee);

        if (!$evaluatorId) {
            return 'skipped';
        }

        Assessment::create([
            'period_id'         => $period->id,
            'employee_user_id'  => $employee->id,
            'evaluator_user_id' => $evaluatorId,
            'post_id'           => $post->id,
            'status'            => 'draft',
        ]);

        return 'created';
    }

    /**
     * تعیین ارزیاب بر اساس هدف
     */
    private function resolveEvaluator(AssessmentPeriodTarget $target, $employee): ?int
    {
        // حالت ۱: ارزیاب مشخص
        if ($target->evaluator_mode === 'specific' && $target->evaluator_user_id) {
            return $target->evaluator_user_id;
        }

        // حالت ۲: مدیر مستقیم (خودکار)
        $position = EmployeePosition::where('user_id', $employee->id)->first();

        if (!$position || !$position->organizational_unit_id) {
            return null;
        }

        // یافتن مدیر واحد
        $manager = EmployeePosition::where('organizational_unit_id', $position->organizational_unit_id)
            ->whereHas('user.roles', fn ($q) => $q->where('name', 'like', '%مدیر%'))
            ->where('user_id', '!=', $employee->id)
            ->first();

        return $manager?->user_id;
    }
}
