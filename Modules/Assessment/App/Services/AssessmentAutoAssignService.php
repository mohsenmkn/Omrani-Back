<?php
namespace Modules\Assessment\App\Services;

use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentPeriod;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\OrganizationalUnit;

class AssessmentAutoAssignService
{
    private const EVALUATOR_CHAIN = [
        'مدیر' => 'معاون',
        'رئیس' => 'مدیر',
        'سرپرست/کارشناس ارشد' => 'رئیس',
        'کارشناس' => 'سرپرست/کارشناس ارشد',
        'کاردان/تکنسین/مسئول' => 'سرپرست/کارشناس ارشد',
        'راننده/اپراتور' => 'سرپرست/کارشناس ارشد',
        'متصدی' => 'سرپرست/کارشناس ارشد',
        'کارگر' => 'سرپرست/کارشناس ارشد',
    ];

    public function __construct(
        private JobFamilyClassifier $classifier
    ) {
    }

    public function autoAssign(AssessmentPeriod $period): array
    {
        $result = [
            'assigned' => 0,
            'unassigned' => 0,
            'skipped' => 0,
        ];

        $assessments = Assessment::where('period_id', $period->id)
            ->whereNull('evaluator_user_id')
            ->get();

        foreach ($assessments as $assessment) {
            $position = EmployeePosition::where('user_id', $assessment->employee_user_id)->first();

            if (!$position) {
                $result['skipped']++;
                continue;
            }

            $family = $this->classifier->classify($position->post_title);
            $evaluatorFamily = self::EVALUATOR_CHAIN[$family] ?? null;

            if (!$evaluatorFamily) {
                $result['unassigned']++;
                continue;
            }

            $evaluator = $this->findEvaluator($position, $evaluatorFamily);

            if ($evaluator) {
                $assessment->update(['evaluator_user_id' => $evaluator->user_id]);
                $result['assigned']++;
            } else {
                $result['unassigned']++;
            }
        }

        return $result;
    }

    /**
     * یافتن ارزیاب در واحد سازمانی (یا واحدهای والد)
     * ✅ اصلاح شده: بررسی hierarchy واقعی
     */
    private function findEvaluator($pos, string $family, $byUnit, $units): ?array
    {
        $evaluatorFamily = self::EVALUATOR_CHAIN[$family] ?? null;
        if (!$evaluatorFamily) return null;

        $unitId = $pos->organizational_unit_id;

        // جستجو در واحد فعلی و واحدهای والد
        while ($unitId && isset($units[$unitId])) {
            $unit = $units[$unitId];

            // ✅ یافتن مدیر این واحد (نه فقط اولین نفر با آن عنوان)
            $found = ($byUnit[$unitId] ?? collect())
                ->where('user_id', '!=', $pos->user_id)
                ->first(function ($p) use ($evaluatorFamily, $unitId) {
                    // بررسی اینکه این نفر واقعاً مدیر این واحد است
                    return $this->isManagerOfUnit($p, $unitId)
                        && $this->classifier->classify($p->post_title) === $evaluatorFamily;
                });

            if ($found) {
                return ['id' => $found->user_id, 'name' => $found->user?->name];
            }

            $unitId = $unit->parent_id;
        }

        return null;
    }

    /**
     * ✅ متد جدید: بررسی اینکه آیا یک نفر مدیر یک واحد سازمانی است
     */
    private function isManagerOfUnit($position, int $unitId): bool
    {
        // بررسی اینکه آیا این نفر در این واحد است
        if ($position->organizational_unit_id !== $unitId) {
            return false;
        }

        // بررسی عنوان پست: باید مدیر/رئیس/سرپرست باشد
        $title = mb_strtolower($position->post_title ?? '');
        $managerKeywords = ['مدیر', 'رئیس', 'رییس', 'سرپرست', 'معاون', 'مدیرعامل'];

        foreach ($managerKeywords as $keyword) {
            if (mb_strpos($title, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * ✅ متد جدید: بررسی اینکه آیا یک نفر مدیر یک واحد سازمانی است
     */

    /**
     * ✅ متد جدید: بررسی اینکه آیا یک کارمند در زیرمجموعه یک مدیر است
     */
    public function isSubordinate(int $employeeUserId, int $managerUserId): bool
    {
        $employeePosition = EmployeePosition::where('user_id', $employeeUserId)->first();
        $managerPosition = EmployeePosition::where('user_id', $managerUserId)->first();

        if (!$employeePosition || !$managerPosition) {
            return false;
        }

        $employeeUnitId = $employeePosition->organizational_unit_id;
        $managerUnitId = $managerPosition->organizational_unit_id;

        if (!$employeeUnitId || !$managerUnitId) {
            return false;
        }

        // بررسی اینکه آیا واحد کارمند در زیرمجموعه واحد مدیر است
        $currentUnitId = $employeeUnitId;
        while ($currentUnitId) {
            if ($currentUnitId === $managerUnitId) {
                return true;
            }
            $unit = OrganizationalUnit::find($currentUnitId);
            $currentUnitId = $unit?->parent_id;
        }

        return false;
    }
}
