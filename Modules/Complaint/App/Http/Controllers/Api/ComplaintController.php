<?php

namespace Modules\Complaint\App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Complaint\App\Enums\ComplaintPriority;
use Modules\Complaint\App\Enums\ComplaintStatus;
use Modules\Complaint\App\Http\Resources\CategoryResource;
use Modules\Complaint\App\Http\Resources\ComplaintResource;
use Modules\Complaint\App\Models\Complaint;
use Modules\Complaint\App\Models\ComplaintCategory;
use Symfony\Component\HttpFoundation\Response;

class ComplaintController extends Controller
{
    public function categories()
    {
        $categories = ComplaintCategory::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['children' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with(['children' => function ($childQuery) {
                        $childQuery->where('is_active', true)
                            ->orderBy('sort_order');
                    }]);
            }])
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'complaint_category_id' => [
                'required',
                'integer',
                Rule::exists('complaint_categories', 'id')
                    ->where('level', 3)
                    ->where('is_active', true),
            ],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'priority' => ['nullable', Rule::in(ComplaintPriority::values())],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [
                'file',
                'max:' . config('complaint.attachment.max_kb', 5120),
                'mimes:' . implode(',', config('complaint.attachment.mimes', ['jpg', 'pdf'])),
            ],
        ]);

        $complaint = Complaint::create([
            'user_id' => $request->user()->id,
            'complaint_category_id' => $validated['complaint_category_id'],
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'] ?? ComplaintPriority::Medium->value,
        ]);

        if ($request->hasFile('attachments')) {
            $this->storeAttachments($complaint, $request->file('attachments'), $request->user()->id);
        }

        return response()->json([
            'message' => 'شکایت با موفقیت ثبت شد.',
            'data' => new ComplaintResource($complaint->load('category.parent.parent', 'attachments')),
        ], Response::HTTP_CREATED);
    }

    public function my(Request $request)
    {
        $complaints = Complaint::query()
            ->forUser($request->user()->id)
            ->with('category.parent.parent')
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return ComplaintResource::collection($complaints);
    }

    public function show(Request $request, Complaint $complaint)
    {
        $this->authorizeComplaintAccess($request, $complaint);

        $complaint->load([
            'category.parent.parent',
            'attachments',
            'replies.user',
        ]);

        return new ComplaintResource($complaint);
    }

    public function update(Request $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('complaints.manage')) {
            if ($complaint->user_id !== $user->id) {
                return response()->json([
                    'message' => 'دسترسی کافی نیست.',
                ], Response::HTTP_FORBIDDEN);
            }

            if ($complaint->status !== ComplaintStatus::Pending) {
                return response()->json([
                    'message' => 'فقط شکایات در انتظار بررسی قابل ویرایش هستند.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $validated = $request->validate([
            'complaint_category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('complaint_categories', 'id')
                    ->where('level', 3)
                    ->where('is_active', true),
            ],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'min:10', 'max:5000'],
            'priority' => ['nullable', Rule::in(ComplaintPriority::values())],
        ]);

        $complaint->update($validated);

        return response()->json([
            'message' => 'شکایت با موفقیت ویرایش شد.',
            'data' => new ComplaintResource($complaint->fresh()->load('category.parent.parent')),
        ]);
    }

    public function destroy(Request $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('complaints.manage')) {
            if ($complaint->user_id !== $user->id) {
                return response()->json([
                    'message' => 'دسترسی کافی نیست.',
                ], Response::HTTP_FORBIDDEN);
            }

            if ($complaint->status !== ComplaintStatus::Pending) {
                return response()->json([
                    'message' => 'فقط شکایات در انتظار بررسی قابل حذف هستند.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $complaint->delete();

        return response()->json([
            'message' => 'شکایت حذف شد.',
        ]);
    }

    public function uploadAttachment(Request $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();

        if (! $user->can('complaints.manage') && $complaint->user_id !== $user->id) {
            return response()->json([
                'message' => 'دسترسی کافی نیست.',
            ], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'attachments' => ['required', 'array'],
            'attachments.*' => [
                'file',
                'max:' . config('complaint.attachment.max_kb', 5120),
                'mimes:' . implode(',', config('complaint.attachment.mimes', ['jpg', 'pdf'])),
            ],
        ]);

        $this->storeAttachments($complaint, $request->file('attachments'), $user->id);

        return response()->json([
            'message' => 'پیوست‌ها با موفقیت بارگذاری شدند.',
            'data' => new ComplaintResource($complaint->load('attachments')),
        ], Response::HTTP_CREATED);
    }

    protected function authorizeComplaintAccess(Request $request, Complaint $complaint): void
    {
        $user = $request->user();

        if ($user->can('complaints.manage')) {
            return;
        }

        if ($complaint->user_id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'دسترسی کافی نیست.');
        }
    }

    protected function storeAttachments(Complaint $complaint, array $files, int $userId): void
    {
        foreach ($files as $file) {
            $path = $file->store('complaints/' . $complaint->id, 'public');

            $complaint->attachments()->create([
                'uploaded_by' => $userId,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }
}
