<?php

namespace Modules\Complaint\App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Complaint\App\Enums\ComplaintStatus;
use Modules\Complaint\App\Events\ComplaintReplied;
use Modules\Complaint\App\Http\Resources\ComplaintResource;
use Modules\Complaint\App\Models\Complaint;
use Symfony\Component\HttpFoundation\Response;
use Modules\Complaint\App\Enums\ComplaintPriority;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Complaint\App\Exports\ComplaintsExport;
use Morilog\Jalali\Jalalian;

class AdminComplaintController extends Controller
{
    public function index(Request $request)
    {
        $complaints = Complaint::query()
            ->with(['category.parent.parent', 'user'])
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->input('priority'), function ($query, $priority) {
                $query->where('priority', $priority);
            })
            ->when($request->input('category_id'), function ($query, $categoryId) {
                $query->where('complaint_category_id', $categoryId);
            })
            ->when($request->input('date_from'), function ($query, $dateFrom) {
                $query->where('jalali_date', '>=', $dateFrom);
            })
            ->when($request->input('date_to'), function ($query, $dateTo) {
                $query->where('jalali_date', '<=', $dateTo);
            })
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('tracking_code', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%")
                                ->orWhere('personnel_code', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ComplaintResource::collection($complaints);
    }

    public function show(Request $request, Complaint $complaint)
    {
        $complaint->load([
            'category.parent.parent',
            'attachments',
            'replies.user',
            'smsLogs',
        ]);

        return new ComplaintResource($complaint);
    }

    public function updateStatus(Request $request, Complaint $complaint): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(ComplaintStatus::values())],
            'priority' => ['nullable', Rule::in(ComplaintPriority::values())],
        ]);

        $status = ComplaintStatus::from($validated['status']);

        $data = ['status' => $status];

        if (! empty($validated['priority'])) {
            $data['priority'] = ComplaintPriority::from($validated['priority']);
        }

        if ($status === ComplaintStatus::Answered && ! $complaint->answered_at) {
            $data['answered_at'] = now();
        }

        if ($status === ComplaintStatus::Resolved) {
            $data['resolved_at'] = now();
        }

        $complaint->update($data);

        return response()->json([
            'message' => 'وضعیت شکایت به‌روزرسانی شد.',
            'data' => new ComplaintResource($complaint->fresh()->load('category.parent.parent')),
        ]);
    }

    public function storeReply(Request $request, Complaint $complaint): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:3', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $reply = $complaint->replies()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'is_internal' => (bool) ($validated['is_internal'] ?? false),
        ]);

        if (! $reply->is_internal) {
            $complaint->update([
                'status' => ComplaintStatus::Answered,
                'answered_at' => $complaint->answered_at ?? now(),
            ]);

            event(new ComplaintReplied($complaint->fresh(), $reply));
        } elseif ($complaint->status === ComplaintStatus::Pending) {
            $complaint->update([
                'status' => ComplaintStatus::InProgress,
            ]);
        }

        return response()->json([
            'message' => 'پاسخ با موفقیت ثبت شد.',
            'data' => new ComplaintResource($complaint->fresh()->load('replies.user')),
        ], Response::HTTP_CREATED);
    }

    /**
     * خروجی Excel از لیست شکایات با اعمال فیلترهای فعلی
     */
    public function export(Request $request)
    {
        $filters = $request->only([
            'status',
            'priority',
            'category_id',
            'date_from',
            'date_to',
            'search',
        ]);

        $filename = 'complaints-' . Jalalian::now()->format('Y-m-d-H-i') . '.xlsx';

        return Excel::download(new ComplaintsExport($filters), $filename);
    }
}
