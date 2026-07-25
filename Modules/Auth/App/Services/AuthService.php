<?php

namespace Modules\Auth\App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Auth\App\Models\User;

class AuthService
{
    public function login(array $credentials)
    {
        // جستجو بر اساس شماره موبایل
        $user = User::where('mobile', $credentials['mobile'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'mobile' => ['شماره موبایل یا کلمه عبور اشتباه است.'],
            ]);
        }

        $token = $user->createToken('erp_omrani_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token
        ];
    }
}
