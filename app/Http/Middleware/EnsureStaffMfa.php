<?php

namespace App\Http\Middleware;

use App\Models\StaffUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffMfa
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $staff = Auth::guard('staff')->user();
        $staff = $staff instanceof StaffUser ? StaffUser::query()->whereKey($staff->getKey())->first() : null;

        if (! $staff || ! $staff->isActive() || ! $staff->mfa_confirmed_at
            || ! $request->session()->get('staff_mfa_verified')
            || $request->session()->get('staff_session_version') !== $staff->session_version) {
            Auth::guard('staff')->logout();
            $request->session()->forget(['staff_mfa_verified', 'staff_session_version']);
            abort(403, 'Staff session is not authorized.');
        }

        return $next($request);
    }
}
