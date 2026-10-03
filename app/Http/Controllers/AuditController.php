<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Services\AuditChain;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    /**
     * Recompute this tenant's hash chain from scratch and report the first
     * entry where the stored hash no longer matches its contents, or whose
     * previous_hash no longer links to the prior entry's actual hash.
     */
    public function verify(Request $request)
    {
        abort_unless($request->user()->role === Role::Admin, 403);

        $tenantId = $request->user()->tenant_id;

        $entries = AuditLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get();

        $expectedPreviousHash = null;

        foreach ($entries as $entry) {
            $payload = [
                'tenant_id' => $entry->tenant_id,
                'user_id' => $entry->user_id,
                'action' => $entry->action,
                'auditable_type' => $entry->auditable_type,
                'auditable_id' => $entry->auditable_id,
                'changes' => $entry->changes,
                'previous_hash' => $entry->previous_hash,
            ];

            $linkBroken = $entry->previous_hash !== $expectedPreviousHash;
            $hashBroken = AuditChain::hash($payload) !== $entry->hash;

            if ($linkBroken || $hashBroken) {
                return response()->json([
                    'valid' => false,
                    'checked' => $entries->count(),
                    'broken_at' => $entry->id,
                ]);
            }

            $expectedPreviousHash = $entry->hash;
        }

        return response()->json([
            'valid' => true,
            'checked' => $entries->count(),
            'broken_at' => null,
        ]);
    }
}
