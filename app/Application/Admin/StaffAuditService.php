<?php

namespace App\Application\Admin;

use App\Models\AdminAuditEvent;
use App\Models\StaffUser;
use Illuminate\Http\Request;

class StaffAuditService
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public function record(string $action, string $outcome, ?StaffUser $actor = null, array $context = [], ?Request $request = null): AdminAuditEvent
    {
        return AdminAuditEvent::query()->create([
            'staff_user_id' => $actor?->getKey(),
            'action' => $action,
            'outcome' => $outcome,
            'context' => $context,
            'request_id' => $request?->attributes->get('request_id'),
            'created_at' => now(),
        ]);
    }
}
