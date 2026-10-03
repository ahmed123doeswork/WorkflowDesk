<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditChain
{
    /**
     * Append an entry to the tenant's hash chain. Must be called inside the
     * same DB transaction as the change it records, so the log and the
     * change it describes commit or roll back together.
     */
    public static function record(string $action, Model $auditable, array $changes = [], ?int $tenantId = null, ?int $userId = null): AuditLog
    {
        $tenantId ??= $auditable->tenant_id;
        $userId ??= Auth::id();

        // Locks the tail of this tenant's chain so concurrent writers can't
        // both read the same previous hash and fork the chain.
        $previous = AuditLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $payload = [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'changes' => $changes,
            'previous_hash' => $previous?->hash,
        ];

        return AuditLog::create([
            ...$payload,
            'hash' => self::hash($payload),
        ]);
    }

    /**
     * Before/after diff limited to the given attribute keys, pulled from a
     * model that was just saved (relies on Eloquent's original/changes
     * tracking from that save still being fresh).
     */
    public static function diff(Model $model, array $keys): array
    {
        return [
            'before' => Arr::only($model->getOriginal(), $keys),
            'after' => Arr::only($model->getChanges(), $keys),
        ];
    }

    public static function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
}
