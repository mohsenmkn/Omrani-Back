<?php

namespace Modules\User\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\EmployeePosition;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 15);

        $query = EmployeePosition::query()
            ->with(['user', 'unit'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->input('search'));
                $q->where(function ($q) use ($search) {
                    $q->where('post_title', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%")
                        ->orWhere('personnel_code', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('unit_id'), function ($q) use ($request) {
                $q->where('organizational_unit_id', $request->input('unit_id'));
            })
            ->when($request->filled('employee_type'), function ($q) use ($request) {
                $q->whereHas('user', function ($q) use ($request) {
                    $q->where('employee_type', $request->input('employee_type'));
                });
            })
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($q) use ($request) {
                $q->whereHas('user', function ($q) use ($request) {
                    $q->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
                });
            })
            ->latest();

        $positions = $query->paginate($perPage);

        return response()->json($positions);
    }

    // ایجاد کاربر جدید
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => [
                'required',
                'regex:/^09\d{9}$/',
                Rule::unique('users', 'mobile'),
            ],
            'password' => ['required', 'string', 'min:6'],
            'national_code' => [
                'nullable',
                'digits:10',
                Rule::unique('users', 'national_code'),
            ],
            'personnel_code' => [
                'nullable',
                'digits_between:1,20',
                Rule::unique('users', 'personnel_code'),
            ],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name'           => $validated['name'],
            'mobile'         => $validated['mobile'],
            'password'       => Hash::make($validated['password']),
            'national_code'  => $validated['national_code'] ?? null,
            'personnel_code' => $validated['personnel_code'] ?? null,

            /**
             * کاربر دستی فعلاً پرسنل در نظر گرفته می‌شود.
             * پیمانکاران از سینک گستراب تعیین نوع می‌شوند.
             */
            'employee_type'  => 'personnel',

            'is_active'      => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : true,
        ]);

        if ($request->has('roles')) {
            $user->syncRoles($validated['roles'] ?? []);
        }

        return response()->json([
            'message' => 'کاربر با موفقیت ایجاد شد',
            'user'    => $user->load('roles'),
        ], 201);
    }

    // نمایش یک کاربر خاص
    public function show(User $user)
    {
        return response()->json($user);
    }

    // ویرایش کاربر
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'name'           => ['sometimes', 'required', 'string', 'max:255'],
                'mobile'         => [
                    'sometimes',
                    'required',
                    'regex:/^09\d{9}$/',
                    Rule::unique('users', 'mobile')->ignore($user->id),
                ],
                'password'       => ['nullable', 'string', 'min:6'],
                'national_code'  => [
                    'nullable',
                    'digits:10',
                    Rule::unique('users', 'national_code')->ignore($user->id),
                ],
                'personnel_code' => [
                    'nullable',
                    'digits_between:1,20',
                    Rule::unique('users', 'personnel_code')->ignore($user->id),
                ],
                'roles'          => ['nullable', 'array'],
                'roles.*'        => ['string', 'exists:roles,name'],
                'is_active'      => ['nullable', 'boolean'],
            ]);

            /**
             * فقط فیلدهایی که ارسال شده‌اند آپدیت شوند
             */
            $dataToUpdate = collect($validated)
                ->only([
                    'name',
                    'mobile',
                    'national_code',
                    'personnel_code',
                ])
                ->toArray();

            if (!empty($validated['password'])) {
                $dataToUpdate['password'] = Hash::make($validated['password']);
            }

            /**
             * مدیریت وضعیت فعال/غیرفعال
             */
            if (array_key_exists('is_active', $validated)) {
                $newStatus = (bool) $validated['is_active'];

                // پیمانکار نباید فعال شود
                if ($user->isContractor() && $newStatus) {
                    return response()->json([
                        'message' => 'امکان فعال‌سازی پیمانکار وجود ندارد',
                    ], 403);
                }

                // کاربر نباید بتواند خودش را غیرفعال کند
                if (
                    $user->id === auth()->id()
                    && !$newStatus
                ) {
                    return response()->json([
                        'message' => 'شما نمی‌توانید حساب کاربری خودتان را غیرفعال کنید',
                    ], 403);
                }

                $dataToUpdate['is_active'] = $newStatus;
            }

            /**
             * اگر پیمانکار است، وضعیت همیشه غیرفعال باقی بماند
             */
            if ($user->isContractor()) {
                $dataToUpdate['is_active'] = false;
            }

            $user->update($dataToUpdate);

            /**
             * فقط زمانی نقش‌ها را sync کن که فیلد roles ارسال شده باشد
             */
            if ($request->has('roles')) {
                $user->syncRoles($validated['roles'] ?? []);
            }

            /**
             * اگر کاربر غیرفعال شد، توکن‌های او حذف شود
             */
            if (isset($dataToUpdate['is_active']) && !$dataToUpdate['is_active']) {
                $user->tokens()->delete();
            }

            return response()->json([
                'message' => 'کاربر با موفقیت ویرایش شد',
                'user'    => $user->fresh()->load('roles'),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'خطای اعتبارسنجی',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // حذف کاربر
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'کاربر با موفقیت حذف شد'],200);
    }


}
