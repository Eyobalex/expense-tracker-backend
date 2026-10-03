<?php

namespace App\Domain\Admin;

enum StaffPermission: string
{
    case DashboardRead = 'admin.dashboard.read';
    case StaffManage = 'admin.staff.manage';
    case RolesManage = 'admin.roles.manage';
    case AuditRead = 'admin.audit.read';
}
