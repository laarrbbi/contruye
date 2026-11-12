<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Support\Account\DeviceSessions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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
        DeviceSessions::keepNewest($user, config('auth.max_devices'), $request->session()->getId());

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
        DeviceSessions::keepNewest($request->user(), config('auth.max_devices'), $request->session()->getId());

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
    public function updateMe(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return UserResource::make($user->load('province'));
    }
}
