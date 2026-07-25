<?php

namespace Modules\Auth\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Auth\App\Models\User;
use Modules\Auth\App\Services\AuthService;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;


class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'mobile' => 'required|regex:/^09\d{9}$/',
            'password' => 'required',
            'captcha' => 'sometimes|nullable|string',
            'captcha_key' => 'sometimes|nullable|string',
        ]);

        $throttleKey = 'login_attempts:' . $request->mobile . '_' . $request->ip();

// اگر بیش از ۵ بار اشتباه کرده باشد
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {

            // استفاده از filled به جای has برای اطمینان از اینکه مقادیر null یا خالی نیستند
            if (!$request->filled('captcha') || !$request->filled('captcha_key')) {
                return response()->json([
                    'message' => 'تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفا کد امنیتی را وارد کنید.',
                    'require_captcha' => true
                ], 429);
            }

            // کست کردن مقادیر به (string) برای جلوگیری از ارور Fatal در صورت ارسال نوع داده‌ی اشتباه
            if (!captcha_api_check((string) $request->captcha, (string) $request->captcha_key)) {
                return response()->json(['message' => 'کد امنیتی (کپچا) اشتباه است.'], 400);
            }
        }

        $user = User::where('mobile', $request->mobile)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 300);
            return response()->json(['message' => 'نام کاربری یا کلمه عبور اشتباه است.'], 401);
        }

        RateLimiter::clear($throttleKey);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);

    }


    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'با موفقیت خارج شدید.']);
    }

    public function sendOtp(Request $request)
    {
        $request->validate(['mobile' => 'required|string|exists:users,mobile'

        ]);

        $user = User::where('mobile', $request->mobile)->first();
        if (!$user) {
            return response()->json(['message' => 'کاربری با این شماره همراه یافت نشد.'], 400);
        }
        $otp = rand(10000, 99999);

        $user->otp_code = $otp;
        $user->otp_expires_at = Carbon::now()->addMinutes(5);
        $user->save();

        // لاگ کردن کد (جایگزین موقت سرویس پیامک)
        Log::info("OTP Code for {$user->mobile} is: {$otp}");

        return response()->json(['message' => 'کد تایید ارسال شد.'],200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'mobile' => 'required|exists:users,mobile',
            'password' => 'required|string|min:6',
            'password_confirmation' => 'required|string|min:6'
        ]);
        if ($request->password!==$request->password_confirmation){
            return response()->json(['message' => 'کلمه عبور و تکرار کلمه عبور یکسان نمی باشند'], 400);
        }

        $user = User::where('mobile', $request->mobile)->first();

        if (!$user) {
            return response()->json(['message' => 'کاربری با این شماره همراه یافت نشد.'], 400);
        }

        $user->password = Hash::make($request->password);
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->save();

        return response()->json(['message' => 'رمز عبور با موفقیت تغییر کرد.']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|exists:users,mobile',
            'otp' => 'required|string',
        ]);

        $user = User::where('mobile', $request->mobile)->first();

        if ($user->otp_code !== $request->otp || Carbon::now()->isAfter($user->otp_expires_at)) {
            return response()->json(['message' => 'کد تایید نامعتبر یا منقضی شده است.'], 400);
        }
        return response()->json(['message' => 'کد با موفقیت ارسال شد.'], 200);
    }

    // متد دریافت کپچا
    public function getCaptcha()
    {
        // تولید کپچا به صورت Base64 مناسب برای API
        return response()->json(app('captcha')->create('default', true));
    }

    public function me(Request $request)
    {
        /** @var \Modules\Auth\App\Models\User $user */
        $user = $request->user();

        // پاکسازی cache برای جلوگیری از stale permissions (اختیاری ولی مفید در محیط dev)
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->mobile ?? null,

                // نقش‌ها
                'roles' => $user->getRoleNames()->values(),

                // تمام permissionهای موثر (چه مستقیم چه از طریق role)
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            ],
        ]);
    }


    public function syncUserPermissions(Request $request, User $user)
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(function ($query) {
                    $query->where('guard_name', 'web');
                }),
            ],
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user->syncPermissions($validated['permissions']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'دسترسی‌های مستقیم کاربر با موفقیت ثبت شد',
            'user_id' => $user->id,
            'direct_permissions' => $user->getPermissionNames()->values(),
            'all_permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }


    public function syncUserRoles(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where(function ($query) {
                    $query->where('guard_name', 'web');
                }),
            ],
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user->syncRoles($validated['roles']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'نقش‌های کاربر با موفقیت ثبت شد',
            'user_id' => $user->id,
            'roles' => $user->getRoleNames()->values(),
            'all_permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }


    public function getRoles()
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        return response()->json([
            'roles' => $roles,
        ]);
    }

    public function getPermissions()
    {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        return response()->json([
            'permissions' => $permissions,
        ]);
    }

    public function getUserAccess(User $user)
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->mobile ?? null,
            ],
            'roles' => $user->getRoleNames()->values(),
            'direct_permissions' => $user->getPermissionNames()->values(),
            'all_permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }









}
