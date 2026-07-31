<?php

namespace App\Listeners;

use App\Events\Group\GroupUsersSynced;
use App\Events\Group\GroupPermissionsSynced;
use App\Events\Auth\LoginSucceeded;
use App\Events\Auth\LoginFailed;
use App\Events\Auth\TokenRefreshed;
use App\Events\Auth\LogoutSucceeded;
use App\Events\Auth\LogoutFailed;
use App\Models\AuditLog;

class WriteAuditLog
{
    public function handle(object $event): void
    {
        match (true) {
            $event instanceof GroupUsersSynced =>
            $this->groupUsersSynced($event),

            $event instanceof GroupPermissionsSynced =>
            $this->groupPermissionsSynced($event),

            $event instanceof LoginSucceeded =>
            AuditLog::record('login', null, [
                'user_uuid' => $event->userUuid,
            ], $event->actorId),

            $event instanceof LoginFailed =>
            AuditLog::record('login_failed', null, [
                'email' => $event->email,
                'reason' => $event->reason,
            ]),

            $event instanceof TokenRefreshed =>
            AuditLog::record('refresh', null, [
                'user_uuid' => $event->userUuid,
            ], $event->actorId),

            $event instanceof LogoutSucceeded =>
            AuditLog::record('logout', null, [], $event->actorId),

            $event instanceof LogoutFailed =>
            AuditLog::record('logout_failed', null, [
                'error' => $event->error,
            ]),

            default => null,
        };
    }

    protected function groupUsersSynced(GroupUsersSynced $event): void
    {
        AuditLog::record('group_users_sync', null, [
            'group_uuid' => $event->groupUuid,
            'users_in_payload' => $event->userUuids,
        ], $event->actorId);
    }

    protected function groupPermissionsSynced(GroupPermissionsSynced $event): void
    {
        AuditLog::record('group_permissions_sync', null, [
            'group_uuid' => $event->groupUuid,
            'permissions_in_payload' => $event->permissions,
        ], $event->actorId);
    }
}