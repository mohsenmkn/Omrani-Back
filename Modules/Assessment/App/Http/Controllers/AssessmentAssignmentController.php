<?php
namespace Modules\Assessment\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Services\JobFamilyClassifier;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\OrganizationalUnit;

class AssessmentAssignmentController extends Controller
{
    private const EVALUATOR_CHAIN = [
        'معاون' => 'مدیرعامل',
        'مدیر' => 'معاون',
        'رئیس' => 'مدیر',
        'سرپرست/کارشناس ارشد' => 'رئیس',
        'کارشناس' => 'سرپرست/کارشناس ارشد',
        'کاردان/تکنسین/مسئول' => 'سرپرست/کارشناس ارشد',
        'متصدی' => 'سرپرست/کارشناس ارشد',
        'راننده/اپراتور' => 'سرپرست/کارشناس ارشد',
        'کارگر' => 'سرپرست/کارشناس ارشد',
    ];

    public function __construct(private JobFamilyClassifier $classifier)
    {
    }

    public function preview(Request $request): JsonResponse
    {
        $plan = $this->buildPlan($request->query('cycle_id') ? (int)$request->query('cycle_id') : null);
        return response()->json([
            'rows' => $plan['rows'],
            'summary' => $plan['summary'],
        ]);
    }

    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'cycle_id' => 'required|exists:assessment_cycles,id',
        ]);

        $plan = $this->buildPlan((int)$request->input('cycle_id'));
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($plan, $request, &$created, &$skipped) {
            foreach ($plan['rows'] as $row) {
                if ($row['status'] !== 'ready') {
                    $skipped++;
                    continue;
                }

                $assessment = Assessment::firstOrCreate(
                    [
                        'cycle_id' => $request->input('cycle_id'),
                        'employee_user_id' => $row['user_id'],
                    ],
                    [
                        'post_id' => $row['post_id'],
                        'evaluator_user_id' => $row['evaluator_id'],
                        'status' => 'draft',
                    ]
                );

                $assessment->wasRecentlyCreated ? $created++ : $skipped++;
            }
        });

        return response()->json([
            'message' => "تخصیص کامل شد: {$created} ارزیابی ساخته شد، {$skipped} رد شد.",
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    private function buildPlan(?int $cycleId): array
    {
        // ✅ اصلاح: فقط پوزیشن‌هایی که واحد سازمانی مشخص دارند پردازش می‌شوند
        $positions = EmployeePosition::with(['user', 'unit'])
            ->whereNotNull('post_title')
            ->whereNotNull('organizational_unit_id') // <-- این خط حیاتی است
            ->whereHas('user')
            ->get();

        $profiles = AssessmentPost::where('is_active', true)->get();
        $byUnit = $positions->groupBy('organizational_unit_id');
        $units = OrganizationalUnit::all()->keyBy('id');

        $existing = $cycleId
            ? Assessment::where('cycle_id', $cycleId)->pluck('employee_user_id')->flip()
            : collect();

        $rows = [];
        $summary = [
            'total' => 0, 'ready' => 0, 'exists' => 0,
            'no_profile' => 0, 'no_evaluator' => 0, 'out_of_scope' => 0,
        ];

        foreach ($positions as $pos) {
            $summary['total']++;
            $family = $this->classifier->classify($pos->post_title);

            if (!$family) {
                $summary['out_of_scope']++;
                $rows[] = $this->row($pos, null, null, null, 'out_of_scope');
                continue;
            }

            $profile = $profiles->first(fn($p) => $p->grade === $family && $p->unit === $pos->unit?->title)
                ?? $profiles->first(fn($p) => $p->grade === $family);

            if (!$profile) {
                $summary['no_profile']++;
                $rows[] = $this->row($pos, $family, null, null, 'no_profile');
                continue;
            }

            $evaluator = $this->findEvaluator($pos, $family, $byUnit, $units);

            if (!$evaluator) {
                $summary['no_evaluator']++;
                $rows[] = $this->row($pos, $family, $profile, null, 'no_evaluator');
                continue;
            }

            if ($existing->has($pos->user_id)) {
                $summary['exists']++;
                $rows[] = $this->row($pos, $family, $profile, $evaluator, 'exists');
                continue;
            }

            $summary['ready']++;
            $rows[] = $this->row($pos, $family, $profile, $evaluator, 'ready');
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    private function row($pos, ?string $family, ?AssessmentPost $profile, ?array $evaluator, string $status): array
    {
        return [
            'user_id' => $pos->user_id,
            'name' => $pos->user?->name,
            'personnel_code' => $pos->personnel_code,
            'unit' => $pos->unit?->title,
            'post_title' => $pos->post_title,
            'family' => $family,
            'post_id' => $profile?->id,
            'profile_title' => $profile?->title,
            'evaluator_id' => $evaluator['id'] ?? null,
            'evaluator_name' => $evaluator['name'] ?? null,
            'status' => $status,
        ];
    }

    /**
     * ✅ اصلاح شده: یافتن ارزیاب با بررسی hierarchy واقعی
     */
    /**
     * یافتن ارزیاب از زنجیره: در واحد خودِ کارمند، سپس واحدهای والد
     * ✅ اصلاح شده: بررسی اینکه ارزیاب واقعاً مدیر واحد است
     */
    private function findEvaluator($pos, string $family, $byUnit, $units): ?array
    {
        $evaluatorFamily = self::EVALUATOR_CHAIN[$family] ?? null;
        if (!$evaluatorFamily) return null;

        $unitIds = [];
        $unitId = $pos->organizational_unit_id;

        while ($unitId && isset($units[$unitId])) {
            $unitIds[] = $unitId;
            $unitId = $units[$unitId]->parent_id;
        }

        foreach ($unitIds as $uid) {
            $found = ($byUnit[$uid] ?? collect())
                ->where('user_id', '!=', $pos->user_id)
                ->first(function ($p) use ($evaluatorFamily, $uid) {
                    // ✅ بررسی اینکه ارزیاب واقعاً مدیر این واحد است
                    return $this->isManagerOfUnit($p, $uid)
                        && $this->classifier->classify($p->post_title) === $evaluatorFamily;
                });

            if ($found) {
                return ['id' => $found->user_id, 'name' => $found->user?->name];
            }
        }

        return null;
    }

    /**
     * ✅ متد جدید: بررسی اینکه آیا یک نفر مدیر یک واحد سازمانی است
     */
    private function isManagerOfUnit($position, int $unitId): bool
    {
        if ($position->organizational_unit_id !== $unitId) {
            return false;
        }

        $title = mb_strtolower($position->post_title ?? '');
        $managerKeywords = ['مدیر', 'رئیس', 'رییس', 'سرپرست', 'معاون', 'مدیرعامل'];

        foreach ($managerKeywords as $keyword) {
            if (mb_strpos($title, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
