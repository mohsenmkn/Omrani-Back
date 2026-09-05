<?php

namespace Modules\Assessment\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentMethod;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Models\AssessmentQuestion;
use Modules\Assessment\App\Models\AssessmentCategory;
use Modules\Assessment\App\Services\AssessmentService;

class AssessmentController extends Controller
{
    public function __construct(private AssessmentService $service) {}

    /**
     * لیست ارزیابی‌ها (اسکوپ بر اساس نقش)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Assessment::with(['employee', 'evaluator', 'post', 'cycle']);

        if ($user->can('assessment.manage')) {
            if ($request->filled('cycle_id')) $query->where('cycle_id', $request->cycle_id);
            if ($request->filled('status'))   $query->where('status', $request->status);
        } elseif ($user->can('assessment.evaluate')) {
            $query->where('evaluator_user_id', $user->id);
        } else {
            // کارمند: فقط ارزیابی‌های تاییدشده خودش
            $query->where('employee_user_id', $user->id)
                ->where('status', Assessment::STATUS_APPROVED);
        }

        return response()->json(['assessments' => $query->latest()->get()]);
    }

    /**
     * ایجاد ارزیابی تکی
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_user_id'  => 'required|exists:users,id',
            'post_id'           => 'required|exists:assessment_posts,id',
            'evaluator_user_id' => 'required|exists:users,id',
            'cycle_id'          => 'nullable|exists:assessment_cycles,id',
        ]);

        $assessment = $this->service->createAssessment(
            $validated['employee_user_id'],
            $validated['post_id'],
            $validated['evaluator_user_id'],
            $validated['cycle_id'] ?? null
        );

        return response()->json(['assessment' => $assessment], 201);
    }

    /**
     * ایجاد گروهی
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_id'           => 'required|exists:assessment_posts,id',
            'evaluator_user_id' => 'required|exists:users,id',
            'cycle_id'          => 'nullable|exists:assessment_cycles,id',
            'employee_user_ids' => 'required|array|min:1',
            'employee_user_ids.*' => 'exists:users,id',
        ]);

        $result = $this->service->bulkCreate(
            $validated['post_id'],
            $validated['evaluator_user_id'],
            $validated['cycle_id'] ?? null,
            $validated['employee_user_ids']
        );

        return response()->json($result);
    }

    /**
     * نمایش ارزیابی + فرم سوالات
     */
    public function show(Assessment $assessment): JsonResponse
    {
        $user = request()->user();

        $isManager   = $user->can('assessment.manage');
        $isEvaluator = $assessment->evaluator_user_id === $user->id;
        $isEmployee  = $assessment->employee_user_id === $user->id
            && $assessment->status === Assessment::STATUS_APPROVED;

        if (!$isManager && !$isEvaluator && !$isEmployee) {
            return response()->json(['message' => 'دسترسی به این ارزیابی ندارید.'], 403);
        }

        $assessment->load(['employee', 'evaluator', 'post', 'cycle']);

        return response()->json([
            'assessment' => $assessment,
            'categories' => $this->service->getForm($assessment),
            'summary'    => [
                'average_score'      => $assessment->average_score,
                'total_weighted_gap' => $assessment->total_weighted_gap,
                'gaps_count'         => $assessment->gaps()->count(),
            ],
        ]);
    }

    /**
     * ثبت نمرات + محاسبه گپ
     */
    public function submit(Request $request, Assessment $assessment): JsonResponse
    {
        $user = $request->user();

        // 🔐 فقط ارزیابِ خودِ رکورد یا مدیر کل
        if (!$user->can('assessment.manage') && $assessment->evaluator_user_id !== $user->id) {
            return response()->json(['message' => 'فقط ارزیابِ تعیین‌شده می‌تواند این ارزیابی را ثبت کند.'], 403);
        }

        // 🔒 فقط در حالت پیش‌نویس یا برگشت‌خورده قابل ویرایش
        if (!in_array($assessment->status, [Assessment::STATUS_DRAFT, Assessment::STATUS_REJECTED])) {
            return response()->json(['message' => 'این ارزیابی قابل ویرایش نیست.'], 422);
        }

        $request->validate([
            'scores'   => 'required|array',
            'scores.*' => 'integer|between:1,5',
        ]);

        $assessment = $this->service->submitAnswers($assessment, $request->scores);

        return response()->json([
            'message'    => 'ارزیابی ثبت شد و وضعیت به «تکمیل شده» تغییر کرد.',
            'assessment' => $assessment,
        ]);
    }

    public function approve(Assessment $assessment): JsonResponse
    {
        return response()->json(['assessment' => $this->service->approve($assessment, request()->user())]);
    }

    public function reject(Request $request, Assessment $assessment): JsonResponse
    {
        return response()->json(['assessment' => $this->service->reject($assessment, request()->user(), $request->notes)]);
    }

    /**
     * گپ‌های یک ارزیابی + اقدامات
     */
    public function gaps(Assessment $assessment): JsonResponse
    {
        $gaps = $assessment->gaps()
            ->with(['question.category', 'actions.method'])
            ->orderByDesc('weighted_gap')
            ->get()
            ->map(fn($g) => [
                'id'             => $g->id,
                'question'       => $g->question?->title,
                'category'       => $g->question?->category?->title,
                'required_score' => $g->required_score,
                'actual_score'   => $g->actual_score,
                'gap'            => $g->gap,
                'weighted_gap'   => $g->weighted_gap,
                'severity'       => $g->severity_label,
                'requires_action'=> $g->requires_action,
                'fix_deadline'   => $g->fix_deadline,
                'status'         => $g->status,
                'actions'        => $g->actions,
            ]);

        return response()->json(['gaps' => $gaps]);
    }

    /**
     * ایجاد اقدام اصلاحی برای یک گپ
     */
    public function storeAction(Request $request, Assessment $assessment): JsonResponse
    {
        $validated = $request->validate([
            'gap_id'     => 'required|exists:assessment_gaps,id',
            'method_id'  => 'nullable|exists:assessment_methods,id',
            'title'      => 'required|string|max:500',
            'due_date'   => 'nullable|date',
            'description'=> 'nullable|string',
        ]);

        $gap = $assessment->gaps()->where('id', $validated['gap_id'])->firstOrFail();

        $action = $gap->actions()->create([
            'method_id'   => $validated['method_id'] ?? null,
            'title'       => $validated['title'],
            'due_date'    => $validated['due_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'status'      => 'planned',
        ]);

        $gap->update(['status' => 'actioned']);

        return response()->json(['action' => $action], 201);
    }

    // ────────────── کاتالوگ‌ها برای UI ──────────────

    public function posts(Request $request): JsonResponse
    {
        $query = AssessmentPost::where('is_active', true)
            ->withCount([
                'questions as total_questions',
                'questions as risk_done' => fn($q) => $q->whereNotNull('risk_level'),
            ]);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('title', 'like', "%{$s}%")
                ->orWhere('unit', 'like', "%{$s}%")
                ->orWhere('grade', 'like', "%{$s}%"));
        }

        if ($request->filled('grade')) {
            $query->where('grade', $request->grade);
        }

        return response()->json([
            'posts' => $query->orderBy('grade')->orderBy('unit')->get(),
        ]);
    }

    /**
     * GET /assessment/methods?all=1
     */
    public function methods(Request $request): JsonResponse
    {
        $query = AssessmentMethod::query();

        // حالت مدیریتی: همه (فعال + غیرفعال)
        if (!$request->boolean('all')) {
            $query->where('is_active', true);
        }

        return response()->json(['methods' => $query->orderBy('title')->get()]);
    }


    public function users(Request $request): JsonResponse
    {
        $q = $request->search;

        $users = \Modules\Auth\App\Models\User::query()
            ->when($q, fn($qq) => $qq
                ->where('name', 'like', "%{$q}%")
                ->orWhere('personnel_code', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'personnel_code']);

        return response()->json(['users' => $users]);
    }


    /**
     * GET /assessment/posts/{post}/questions
     * سوالات شناسنامه به تفکیک منظر + آمار
     */
    public function postQuestions(AssessmentPost $post): JsonResponse
    {
        $questions = AssessmentQuestion::where('post_id', $post->id)
            ->with('category')
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $total = $questions->count();
        $done  = $questions->whereNotNull('risk_level')->count();

        return response()->json([
            'post' => $post,
            'stats' => [
                'total'      => $total,
                'risk_done'  => $done,
                'completion' => $total ? round(($done / $total) * 100) : 0,
            ],
            'categories' => $questions->groupBy('category.title')->map(fn($g) => [
                'title'     => $g->first()->category?->title ?? 'سایر',
                'questions' => $g->values(),
            ])->values(),
        ]);
    }

    /**
     * PUT /assessment/questions/{question}
     */
    public function updateQuestion(Request $request, AssessmentQuestion $question): JsonResponse
    {
        $validated = $request->validate([
            'required_score' => 'nullable|integer|between:1,5',
            'risk_level'     => 'nullable|integer|between:1,5',
            'fix_deadline'   => 'nullable|in:immediate,short_term,mid_term,long_term',
            'title'          => 'nullable|string|max:1000',
            'is_active'      => 'nullable|boolean',
        ]);

        $question->update($validated);

        return response()->json(['question' => $question->fresh()]);
    }

    /**
     * PUT /assessment/questions/bulk
     * ذخیره گروهی تغییرات شناسنامه
     */
    public function bulkUpdateQuestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.id'             => 'required|exists:assessment_questions,id',
            'items.*.required_score' => 'nullable|integer|between:1,5',
            'items.*.risk_level'     => 'nullable|integer|between:1,5',
            'items.*.fix_deadline'   => 'nullable|in:immediate,short_term,mid_term,long_term',
        ]);

        foreach ($validated['items'] as $item) {
            AssessmentQuestion::where('id', $item['id'])
                ->update(collect($item)->except('id')->toArray());
        }

        return response()->json(['updated' => count($validated['items'])]);
    }



    /**
     * GET /assessment/categories
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'categories' => AssessmentCategory::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * POST /assessment/categories
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200|unique:assessment_categories,title',
        ]);

        $category = AssessmentCategory::create([
            'title'      => $validated['title'],
            'sort_order' => (AssessmentCategory::max('sort_order') ?? 0) + 1,
            'is_active'  => true,
        ]);

        return response()->json(['category' => $category], 201);
    }

    /**
     * POST /assessment/questions
     */
    public function storeQuestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_id'        => 'required|exists:assessment_posts,id',
            'category_id'    => 'required|exists:assessment_categories,id',
            'title'          => 'required|string|max:1000',
            'required_score' => 'nullable|integer|between:1,5',
            'risk_level'     => 'nullable|integer|between:1,5',
            'fix_deadline'   => 'nullable|in:immediate,short_term,mid_term,long_term',
        ]);

        $question = AssessmentQuestion::create($validated + ['is_active' => true]);

        return response()->json(['question' => $question], 201);
    }

    /**
     * DELETE /assessment/questions/{question}
     * اگر سوال در ارزیابی‌ها استفاده شده باشد، به جای حذف، غیرفعال می‌شود
     */
    public function destroyQuestion(AssessmentQuestion $question): JsonResponse
    {
        if ($question->answers()->exists()) {
            $question->update(['is_active' => false]);
            return response()->json([
                'message'  => 'این سوال در ارزیابی‌ها استفاده شده، بنابراین به جای حذف، غیرفعال شد.',
                'disabled' => true,
            ]);
        }

        $question->delete();

        return response()->json([
            'message'  => 'سوال حذف شد.',
            'disabled' => false,
        ]);
    }

    /**
     * POST /assessment/posts
     * تعریف پست سازمانی جدید
     */
    public function storePost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'                => 'required|string|max:255|unique:assessment_posts,title',
            'grade'                => 'nullable|string|max:50',
            'unit'                 => 'nullable|string|max:150',
            'domain'               => 'nullable|string|max:150',
            'min_education'        => 'nullable|string|max:50',
            'min_experience_years' => 'nullable|integer|min:0|max:50',
            'description'          => 'nullable|string',
        ], [
            'title.required' => 'عنوان پست الزامی است.',
            'title.unique'   => 'پستی با این عنوان قبلاً ثبت شده است.',
        ]);

        $post = AssessmentPost::create($validated + ['is_active' => true]);

        return response()->json(['post' => $post], 201);
    }

    /**
     * PUT /assessment/posts/{post}
     * ویرایش پست
     */
    public function updatePost(Request $request, AssessmentPost $post): JsonResponse
    {
        $validated = $request->validate([
            'title'                => 'sometimes|required|string|max:255|unique:assessment_posts,title,' . $post->id,
            'grade'                => 'nullable|string|max:50',
            'unit'                 => 'nullable|string|max:150',
            'domain'               => 'nullable|string|max:150',
            'min_education'        => 'nullable|string|max:50',
            'min_experience_years' => 'nullable|integer|min:0|max:50',
            'description'          => 'nullable|string',
            'is_active'            => 'nullable|boolean',
        ]);

        $post->update($validated);

        return response()->json(['post' => $post->fresh()]);
    }

    /**
     * DELETE /assessment/posts/{post}
     * حذف پست — اگر سوال/ارزیابی داشته باشد، غیرفعال می‌شود
     */
    public function destroyPost(AssessmentPost $post): JsonResponse
    {
        if ($post->questions()->exists() || $post->assessments()->exists()) {
            $post->update(['is_active' => false]);
            return response()->json([
                'message'  => 'این پست دارای سوال یا ارزیابی است، بنابراین به جای حذف، غیرفعال شد.',
                'disabled' => true,
            ]);
        }

        $post->delete();

        return response()->json([
            'message'  => 'پست با موفقیت حذف شد.',
            'disabled' => false,
        ]);
    }

    /**
     * GET /assessment/suggest-post?user_id=
     * پیشنهاد پست ارزیابی بر اساس پست فعلی کارمند در HR
     */
    public function suggestPost(Request $request): JsonResponse
    {
        $userId = (int) $request->query('user_id');

        $position = \Modules\HR\App\Models\EmployeePosition::where('user_id', $userId)->first();

        if (!$position || !$position->post_title) {
            return response()->json(['post_id' => null, 'post_title' => null]);
        }

        $normalized = $this->normalizeTitle($position->post_title);

        $post = AssessmentPost::where('is_active', true)->get()
            ->first(fn($p) => $this->normalizeTitle($p->title) === $normalized);

        return response()->json([
            'post_id'    => $post?->id,
            'post_title' => $position->post_title,
        ]);
    }

    private function normalizeTitle(string $t): string
    {
        $t = str_replace(['‌', '‏', '‎'], '', $t);
        $t = preg_replace('/\s+/u', ' ', trim($t));
        return mb_strtolower($t);
    }


    /**
     * POST /assessment/methods
     */
    public function storeMethod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255|unique:assessment_methods,title',
        ], [
            'title.required' => 'عنوان روش الزامی است.',
            'title.unique'   => 'این روش قبلاً ثبت شده است.',
        ]);

        $method = AssessmentMethod::create($validated + ['is_active' => true]);

        return response()->json(['method' => $method], 201);
    }

    /**
     * PUT /assessment/methods/{method}
     */
    public function updateMethod(Request $request, AssessmentMethod $method): JsonResponse
    {
        $validated = $request->validate([
            'title'     => 'sometimes|required|string|max:255|unique:assessment_methods,title,' . $method->id,
            'is_active' => 'nullable|boolean',
        ]);

        $method->update($validated);

        return response()->json(['method' => $method->fresh()]);
    }

    /**
     * DELETE /assessment/methods/{method}
     * اگر در اقدامات اصلاحی استفاده شده → غیرفعال؛ وگرنه حذف
     */
    public function destroyMethod(AssessmentMethod $method): JsonResponse
    {
        $used = \Modules\Assessment\App\Models\AssessmentAction::where('method_id', $method->id)->exists();

        if ($used) {
            $method->update(['is_active' => false]);
            return response()->json([
                'message'  => 'این روش در اقدامات اصلاحی استفاده شده، بنابراین غیرفعال شد.',
                'disabled' => true,
            ]);
        }

        $method->delete();

        return response()->json([
            'message'  => 'روش با موفقیت حذف شد.',
            'disabled' => false,
        ]);
    }


    /**
     * GET /assessment/employees/{user}/report
     * کارنامه شایستگی یک کارمند (همه ارزیابی‌ها + گپ‌ها + اقدامات)
     */
    public function employeeReport(Request $request, int $user): JsonResponse
    {
        // 🔐 فقط HR یا دارای دسترسی ارزیابی
        if (!$request->user()->can('hr.view') && !$request->user()->can('assessment.view')) {
            return response()->json(['message' => 'دسترسی مجاز نیست.'], 403);
        }

        $assessments = Assessment::where('employee_user_id', $user)
            ->with([
                'post', 'cycle', 'evaluator',
                'gaps' => fn($q) => $q->with(['question.category', 'actions.method'])->orderByDesc('weighted_gap'),
            ])
            ->latest()
            ->get()
            ->map(function ($a) {
                return [
                    'id'                   => $a->id,
                    'status'               => $a->status,
                    'status_label'         => $this->statusLabel($a->status),
                    'post_title'           => $a->post?->title,
                    'cycle_title'          => $a->cycle?->title,
                    'evaluator_name'       => $a->evaluator?->name,
                    'submitted_at'         => $a->submitted_at?->format('Y-m-d'),
                    'average_score'        => $a->average_score,
                    'total_weighted_gap'   => $a->total_weighted_gap,
                    'gaps_count'           => $a->gaps->count(),
                    'critical_count'       => $a->gaps->filter(fn($g) => $g->severity_label === 'بحرانی')->count(),
                    'categories'           => $a->gaps->groupBy('question.category.title')->map(fn($group, $title) => [
                        'title' => $title,
                        'gaps'  => $group->map(fn($g) => [
                            'id'             => $g->id,
                            'question'       => $g->question?->title,
                            'required_score' => $g->required_score,
                            'actual_score'   => $g->actual_score,
                            'gap'            => $g->gap,
                            'weighted_gap'   => $g->weighted_gap,
                            'severity'       => $g->severity_label,
                            'actions'        => $g->actions->map(fn($act) => [
                                'title'  => $act->title,
                                'method' => $act->method?->title,
                                'status' => $act->status,
                            ])->values(),
                        ])->values(),
                    ])->values(),
                ];
            });

        return response()->json(['assessments' => $assessments]);
    }

    private function statusLabel(string $status): string
    {
        return [
            'draft'     => 'در حال ارزیابی',
            'submitted' => 'تکمیل شده',
            'approved'  => 'تایید شده',
            'rejected'  => 'برگشت خورده',
        ][$status] ?? $status;
    }




}
