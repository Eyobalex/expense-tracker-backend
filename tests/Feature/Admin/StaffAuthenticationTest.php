<?php

use App\Application\Admin\TotpService;
use App\Models\AdminAuditEvent;
use App\Models\StaffRole;
use App\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function staffWithDashboardAccess(): StaffUser
{
    $staff = StaffUser::factory()->create();
    $role = StaffRole::factory()->create(['permissions' => ['admin.dashboard.read']]);
    $staff->roles()->attach($role);

    return $staff;
}

test('staff login requires valid credentials and a current authenticator code', function (): void {
    $staff = staffWithDashboardAccess();
    $code = app(TotpService::class)->code($staff->mfa_secret, intdiv(time(), 30));

    $this->post(route('admin.login.store'), [
        'email' => $staff->email,
        'password' => 'password',
        'code' => $code,
    ])->assertRedirect(route('admin.overview'));

    $this->assertAuthenticated('staff');
    expect(AdminAuditEvent::query()->where('action', 'staff.login')->where('outcome', 'allowed')->count())->toBe(1);
});

test('invalid staff MFA is denied without revealing account state', function (): void {
    $staff = staffWithDashboardAccess();

    $this->from(route('admin.login'))
        ->post(route('admin.login.store'), ['email' => $staff->email, 'password' => 'password', 'code' => '000000'])
        ->assertRedirect(route('admin.login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest('staff');
    expect(AdminAuditEvent::query()->where('action', 'staff.login')->where('outcome', 'denied')->count())->toBe(1);
});

test('customer authentication cannot access the staff dashboard', function (): void {
    $this->get(route('admin.overview'))->assertRedirect(route('admin.login'));
});

test('staff without the dashboard permission is forbidden', function (): void {
    $staff = StaffUser::factory()->create();
    $this->actingAs($staff, 'staff')->withSession([
        'staff_mfa_verified' => true,
        'staff_session_version' => $staff->session_version,
    ])->get(route('admin.overview'))->assertForbidden();
});

test('disabled staff sessions are rejected even when the session exists', function (): void {
    $staff = staffWithDashboardAccess();
    $staff->update(['status' => 'disabled']);

    $this->actingAs($staff, 'staff')->withSession([
        'staff_mfa_verified' => true,
        'staff_session_version' => $staff->session_version,
    ])->get(route('admin.overview'))->assertForbidden();
});
