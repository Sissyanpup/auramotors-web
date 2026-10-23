<?php

namespace App\Http\Controllers\Auth;

use App\Activity\ActivityLogger;
use App\Enums\ActivityCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function destroy(Request $request, ActivityLogger $activity): Response
    {
        if ($request->user()) {
            $activity->log(ActivityCategory::Account, 'user.logout', "{$request->user()->name} keluar dari aplikasi", $request->user());
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
