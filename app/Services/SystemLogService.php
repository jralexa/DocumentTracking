<?php

namespace App\Services;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

class SystemLogService
{
    /**
     * Persist a system log entry.
     *
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $category,
        string $action,
        string $message,
        ?User $user = null,
        ?Request $request = null,
        ?Model $entity = null,
        array $context = [],
        string $level = 'info'
    ): void {
        try {
            SystemLog::query()->create([
                'level' => $level,
                'category' => $category,
                'action' => $action,
                'message' => $message,
                'user_id' => $user?->id,
                'ip_address' => $request?->ip(),
                'http_method' => $request?->method(),
                'route_name' => $request?->route()?->getName(),
                'request_path' => $request?->path(),
                'entity_type' => $entity !== null ? class_basename($entity) : null,
                'entity_id' => $entity?->getKey() !== null ? (string) $entity->getKey() : null,
                'context' => $context,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }

    /**
     * Persist a successful auth log entry.
     */
    public function auth(
        string $action,
        string $message,
        ?User $user = null,
        ?Request $request = null,
        array $context = []
    ): void {
        $this->record(
            category: 'auth',
            action: $action,
            message: $message,
            user: $user,
            request: $request,
            context: $context
        );
    }

    /**
     * Persist an admin action log entry.
     */
    public function admin(
        string $action,
        string $message,
        ?User $user = null,
        ?Request $request = null,
        ?Model $entity = null,
        array $context = []
    ): void {
        $this->record(
            category: 'admin',
            action: $action,
            message: $message,
            user: $user,
            request: $request,
            entity: $entity,
            context: $context
        );
    }

    /**
     * Persist a workflow action log entry.
     */
    public function workflow(
        string $action,
        string $message,
        ?User $user = null,
        ?Model $entity = null,
        array $context = []
    ): void {
        $this->record(
            category: 'workflow',
            action: $action,
            message: $message,
            user: $user,
            entity: $entity,
            context: $context
        );
    }

    /**
     * Persist a scheduled job log entry.
     */
    public function scheduler(
        string $action,
        string $message,
        array $context = [],
        string $level = 'info'
    ): void {
        $this->record(
            category: 'scheduler',
            action: $action,
            message: $message,
            context: $context,
            level: $level
        );
    }

    /**
     * Persist a custody action log entry.
     */
    public function custody(
        string $action,
        string $message,
        ?User $user = null,
        ?Model $entity = null,
        array $context = []
    ): void {
        $this->record(
            category: 'custody',
            action: $action,
            message: $message,
            user: $user,
            entity: $entity,
            context: $context
        );
    }

    /**
     * Persist an alert generation log entry.
     */
    public function alert(
        string $action,
        string $message,
        array $context = [],
        string $level = 'info'
    ): void {
        $this->record(
            category: 'alerts',
            action: $action,
            message: $message,
            context: $context,
            level: $level
        );
    }
}
