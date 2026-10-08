<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateAvatarRequest;
use App\Http\Requests\Auth\UpdateKtpRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user()->load('sellerProfile'));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    public function updateAvatar(UpdateAvatarRequest $request): UserResource
    {
        $user = $request->user();

        $oldPath = $user->avatar_path;

        $user->update([
            'avatar_path' => $request->file('avatar')->store('avatars', 'public'),
        ]);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return new UserResource($user->load('sellerProfile'));
    }

    public function updateKtp(UpdateKtpRequest $request): UserResource
    {
        $user = $request->user();

        $user->update([
            'ktp_number' => $request->validated('ktp_number'),
            'ktp_name' => $request->validated('ktp_name'),
            'ktp_verified_at' => now(),
        ]);

        return new UserResource($user->load('sellerProfile'));
    }
}
