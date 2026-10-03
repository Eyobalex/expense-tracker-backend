<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\StaffAuditService;
use App\Application\Admin\TotpService;
use App\Http\Controllers\Controller;
use App\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request, TotpService $totp, StaffAuditService $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);
        $email = mb_strtolower($credentials['email']);
        $staff = StaffUser::query()->where('email', $email)->first();
        $valid = $staff !== null
            && $staff->isActive()
            && $staff->mfa_confirmed_at !== null
            && Hash::check($credentials['password'], $staff->password)
            && $totp->verify($staff->mfa_secret, $credentials['code']);

        if (! $valid) {
            $audit->record('staff.login', 'denied', null, ['email_hash' => hash('sha256', $email)], $request);

            return back()->withErrors(['email' => 'These credentials are not valid.'])->withInput($request->only('email'));
        }

        Auth::guard('staff')->login($staff);
        $request->session()->regenerate();
        $request->session()->put(['staff_mfa_verified' => true, 'staff_session_version' => $staff->session_version]);
        $staff->forceFill(['last_login_at' => now()])->save();
        $audit->record('staff.login', 'allowed', $staff, [], $request);

        return redirect()->route('admin.overview');
    }

    public function destroy(Request $request, StaffAuditService $audit): RedirectResponse
    {
        $staff = $request->user('staff');
        if ($staff instanceof StaffUser) {
            $audit->record('staff.logout', 'allowed', $staff, [], $request);
        }
        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
