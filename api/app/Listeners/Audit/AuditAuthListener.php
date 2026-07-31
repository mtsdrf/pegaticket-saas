<?php

namespace App\Listeners\Audit;

use App\Events\Auth\LoginSucceeded;
use App\Events\Auth\LoginFailed;
use App\Events\Auth\TokenRefreshed;
use App\Events\Auth\LogoutSucceeded;
use App\Events\Auth\LogoutFailed;
use App\Models\AuditLog;

class AuditAuthListener
{
    public function handle(object $event): void
    {
        match (true) {

            $event instanceof LoginSucceeded =>
            AuditLog::record(
                event: 'auth.login.succeeded',
                model: null,
                meta: [
                    'user_uuid' => $event->userUuid,
                ],
                actorId: $event->actorId
            ),

            $event instanceof LoginFailed =>
            AuditLog::record(
                event: 'auth.login.failed',
                model: null,
                meta: [
                    'email' => $event->email,
                    'reason' => $event->reason,
                ]
            ),

            $event instanceof TokenRefreshed =>
            AuditLog::record(
                event: 'auth.token.refreshed',
                model: null,
                meta: [
                    'user_uuid' => $event->userUuid,
                ],
                actorId: $event->actorId
            ),

            $event instanceof LogoutSucceeded =>
            AuditLog::record(
                event: 'auth.logout.succeeded',
                model: null,
                meta: [],
                actorId: $event->actorId
            ),

            $event instanceof LogoutFailed =>
            AuditLog::record(
                event: 'auth.logout.failed',
                model: null,
                meta: [
                    'error' => $event->error,
                ]
            ),

            default => null,
        };
    }
}