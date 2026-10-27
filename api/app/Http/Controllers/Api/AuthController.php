<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->validated(),
            'role' => 'student',
            'locale' => $request->validated('locale', 'es'),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        Mail::to($user)->queue(new WelcomeMail($user));

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $request->session()->regenerate();

        return UserResource::make($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'ok']);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('province'));
    }

    /**
     * Update the signed-in user's own preferences. Identity fields (email,
     * role, password) are deliberately not editable here.
     */
    public function updateMe(Request $request): UserResource
    {
        $data = $request->validate([
            'province_id' => ['sometimes', 'nullable', 'integer', 'exists:provinces,id'],
            'locale' => ['sometimes', 'string', Rule::in(config('locales.supported'))],
        ]);

        $user = $request->user();
        $user->update($data);

        return UserResource::make($user->load('province'));
    }
}
