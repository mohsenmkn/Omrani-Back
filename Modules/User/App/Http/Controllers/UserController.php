<?php

namespace Modules\User\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'permissions'])
            ->latest()
            ->paginate(10);
        return response()->json($users);
    }

    // ایجاد کاربر جدید
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mobile' => 'required|regex:/^09\d{9}$/|unique:users,mobile',
            'password' => 'required|string|min:6',
            'national_code'  => ['nullable', 'digits:10', 'unique:users,national_code'],
            'personnel_code' => ['nullable', 'digits_between:1,20', 'unique:users,personnel_code'],
            'roles'          => 'nullable|array',
            'roles.*'        => 'string|exists:roles,name', // بررسی وجود نقش در دیتابیس
        ]);


        $user = User::create([
            'name' => $validated['name'],
            'mobile' => $validated['mobile'],
            'password' => Hash::make($validated['password']),
            'personnel_code' => $validated['personnel_code'],
            'national_code' => $validated['national_code'],
        ]);

        // 3. اختصاص نقش‌ها به کاربر جدید
        if ($request->has('roles')) {
            $user->syncRoles($validated['roles']);
        }

        return response()->json(['message' => 'کاربر با موفقیت ایجاد شد', 'user' => $user], 201);
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
                'name'           => 'sometimes|string|max:255',
                'mobile'         => ['sometimes', 'regex:/^09\d{9}$/', 'unique:users,mobile,' . $user->id],
                'password'       => 'nullable|string|min:6',
                'national_code'  => ['nullable', 'digits:10', 'unique:users,national_code,' . $user->id],
                'personnel_code' => ['nullable', 'digits_between:1,20', 'unique:users,personnel_code,' . $user->id],
                'roles'          => 'nullable|array',
                'roles.*'        => 'string|exists:roles,name',
            ]);

            $dataToUpdate = [
                'name'           => $validated['name'],
                'mobile'         => $validated['mobile'],
                'national_code'  => $validated['national_code'],
                'personnel_code' => $validated['personnel_code'],
            ];

            // جلوگیری از هش شدن پسورد در صورت ارسال مقدار خالی
            if (!empty($validated['password'])) {
                $dataToUpdate['password'] = Hash::make($validated['password']);
            } else {
                unset($dataToUpdate['password']);
            }

            $user->update($dataToUpdate);

            //update Role
            $user->syncRoles($request->input('roles', []));

            return response()->json(['message' => 'کاربر با موفقیت ویرایش شد', 'user' => $user,'data' => $user->load('roles') ], 201);
        }
        catch (\Illuminate\Validation\ValidationException $e) {
            // بهتر است خطاهای ولیدیشن را جداگانه مدیریت کنید تا دقیقاً بفهمید کدام فیلد خطا دارد
            return response()->json(['message' => 'خطای اعتبارسنجی', 'errors' => $e->errors()], 422);
        }
        catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
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
