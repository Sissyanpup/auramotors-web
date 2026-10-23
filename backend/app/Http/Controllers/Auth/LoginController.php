<?php

namespace App\Http\Controllers\Auth;

use App\Activity\ActivityLogger;
use App\Enums\ActivityCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function store(LoginRequest $request): UserResource
    {
        if (! Auth::attempt($request->validated(), remember: true)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        app(ActivityLogger::class)->log(ActivityCategory::Account, 'user.login', "{$request->user()->name} masuk ke aplikasi", $request->user());

        return new UserResource($request->user());
    }
}
