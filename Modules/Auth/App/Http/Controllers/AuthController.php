<?php

namespace Modules\Auth\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Auth\App\Models\User;
use Modules\Auth\App\Services\AuthService;
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

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            if (!$request->filled('captcha') || !$request->filled('captcha_key')) {
                return response()->json([
                    'message' => 'تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفا کد امنیتی را وارد کنید.',
                    'require_captcha' => true
                ], 429);
            }

            if (!captcha_api_check((string) $request->captcha, (string) $request->captcha_key)) {
                return response()->json(['message' => 'کد امنیتی (کپچا) اشتباه است.'], 400);
            }
        }

        try {
            $result = $this->authService->login($request->only('mobile', 'password'));
            RateLimiter::clear($throttleKey);

            return response()->json([
                'token' => $result['token'],
                'user' => $result['user']
            ]);
        } catch (ValidationException $e) {
            RateLimiter::hit($throttleKey, 300);
            return response()->json(['message' => $e->errors()['mobile'][0]], 401);
        }
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|string|exists:users,mobile'
        ]);

        try {
            $result = $this->authService->sendOtp($request->mobile);
            return response()->json($result, 200);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->errors()['mobile'][0]], 400);
        } catch (\Exception $e) {
            Log::error('Send OTP Error: ' . $e->getMessage());
            return response()->json(['message' => 'خطا در ارسال کد تایید.'], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required|exists:users,mobile',
            'otp' => 'required|string|size:5',
        ]);

        try {
            $user = $this->authService->verifyOtp($request->mobile, $request->otp);

            return response()->json([
                'message' => 'کد با موفقیت تایید شد.',
                'user' => [
                    'mobile' => $user->mobile,
                    'name' => $user->name
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->errors()['otp'][0] ?? 'کد تایید نامعتبر است.'], 400);
        }
    }

    public function resetPassword(Request $request)
    {
        // ✅ اضافه کردن 'otp' به ولیدیشن برای جلوگیری از خطای null
        $request->validate([
            'mobile' => 'required|exists:users,mobile',
            'otp' => 'required|string|size:5', // <-- این خط حیاتی است
            'password' => 'required|string|min:6|confirmed',
        ]);

        try {
            // 1. ابتدا OTP را مجدداً بررسی می‌کنیم (امنیت)
            $user = $this->authService->verifyOtp($request->mobile, $request->otp);

            // 2. تغییر رمز عبور
            $this->authService->resetPassword($user, $request->password);

            return response()->json(['message' => 'رمز عبور با موفقیت تغییر کرد.'], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->errors()['otp'][0] ?? 'کد تایید نامعتبر یا منقضی شده است.'], 400);
        } catch (\Exception $e) {
            Log::error('Reset Password Error: ' . $e->getMessage());
            return response()->json(['message' => 'خطا در تغییر رمز عبور. لطفاً مجدداً تلاش کنید.'], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'با موفقیت خارج شدید.']);
    }

    public function getCaptcha()
    {
        return response()->json(app('captcha')->create('default', true));
    }

    // ... (سایر متدهای me, getRoles, getPermissions و ... بدون تغییر باقی می‌مانند)
    public function me(Request $request)
    {
        /** @var \Modules\Auth\App\Models\User $user */
        $user = $request->user();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->mobile ?? null,
                'personnel_code'=> $user->personnel_code ?? null,
                'email' => $user->email ?? null,
                'roles' => $user->getRoleNames()->values(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            ],
        ]);
    }

    // (متدهای syncUserPermissions, syncUserRoles, getRoles, getPermissions, getUserAccess را همان‌طور که بودند نگه دارید)
}
