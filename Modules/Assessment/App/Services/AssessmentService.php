<?php


namespace Modules\Assessment\App\Services;

use Illuminate\Support\Facades\DB;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentCycle;
use Modules\Assessment\App\Models\AssessmentQuestion;
use Modules\Auth\App\Models\User;

class AssessmentService
{
    // ────────────── چرخه ──────────────

    public function createCycle(array $data): AssessmentCycle
    {
        return AssessmentCycle::create([
            'title' => $data['title'],
            'type' => $data['type'] ?? 'annual',
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => AssessmentCycle::STATUS_DRAFT,
        ]);
    }

    public function setCycleStatus(AssessmentCycle $cycle, string $status): AssessmentCycle
    {
        $cycle->update(['status' => $status]);
        return $cycle;
    }

    // ────────────── ایجاد ارزیابی ──────────────

    public function createAssessment(int $employeeUserId, int $postId, int $evaluatorUserId, ?int $cycleId = null): Assessment
    {
        return Assessment::create([
            'cycle_id' => $cycleId,
            'employee_user_id' => $employeeUserId,
            'post_id' => $postId,
            'evaluator_user_id' => $evaluatorUserId,
            'status' => Assessment::STATUS_DRAFT,
        ]);
    }

    /**
     * ایجاد گروهی ارزیابی (برای یک پست + ارزیاب + لیست کارکنان)
     */
    public function bulkCreate(int $postId, int $evaluatorUserId, ?int $cycleId, array $employeeUserIds): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($employeeUserIds as $uid) {
            $exists = Assessment::where('employee_user_id', $uid)
                ->where('post_id', $postId)
                ->when($cycleId, fn($q) => $q->where('cycle_id', $cycleId))
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $this->createAssessment($uid, $postId, $evaluatorUserId, $cycleId);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    // ────────────── فرم ارزیابی ──────────────

    /**
     * سوالات پست به تفکیک منظر + نمره ثبت‌شده (اگر باشد)
     */
    public function getForm(Assessment $assessment): array
    {
        $questions = AssessmentQuestion::where('post_id', $assessment->post_id)
            ->where('is_active', true)
            ->with('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category_id');

        $answers = $assessment->answers()->pluck('score', 'question_id');

        return $questions->map(function ($group, $catId) use ($answers) {
            return [
                'id' => $catId,
                'title' => $group->first()->category?->title,
                'questions' => $group->map(fn($q) => [
                    'id' => $q->id,
                    'title' => $q->title,
                    'required_score' => $q->required_score,
                    'risk_level' => $q->risk_level,
                    'score' => $answers[$q->id] ?? null,
                ])->values(),
            ];
        })->values()->toArray();
    }

    // ────────────── ثبت نمرات + محاسبه گپ ──────────────

    public function submitAnswers(Assessment $assessment, array $scores): Assessment
    {
        if ($assessment->status === Assessment::STATUS_APPROVED) {
            throw new \RuntimeException('ارزیابی تاییدشده قابل ویرایش نیست.');
        }

        $questions = AssessmentQuestion::where('post_id', $assessment->post_id)
            ->get()->keyBy('id');

        DB::transaction(function () use ($assessment, $scores, $questions) {
            foreach ($scores as $questionId => $score) {
                $q = $questions[$questionId] ?? null;
                if (!$q) continue;

                $score = (int)$score;
                if ($score < 1 || $score > 5) continue;

                $assessment->answers()->updateOrCreate(
                    ['question_id' => $q->id],
                    ['score' => $score]
                );

                // ── محاسبه گپ ──
                $gap = max(0, ($q->required_score ?? 5) - $score);
                $weight = $q->risk_level ?? $q->required_score ?? 5;

                if ($gap > 0) {
                    $assessment->gaps()->updateOrCreate(
                        ['question_id' => $q->id],
                        [
                            'required_score' => $q->required_score,
                            'actual_score' => $score,
                            'gap' => $gap,
                            'weighted_gap' => $gap * $weight,
                            'fix_deadline' => $q->fix_deadline,
                            'status' => 'open',
                        ]
                    );
                } else {
                    $assessment->gaps()->where('question_id', $q->id)->delete();
                }
            }

            $assessment->update([
                'status' => Assessment::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);
        });

        return $assessment->fresh();
    }

    // ────────────── تایید / رد ──────────────

    public function approve(Assessment $a, User $approver): Assessment
    {
        $a->update([
            'status' => Assessment::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
        return $a;
    }

    public function reject(Assessment $a, User $approver, ?string $notes = null): Assessment
    {
        $a->update([
            'status' => Assessment::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'notes' => $notes,
        ]);
        return $a;
    }
}
