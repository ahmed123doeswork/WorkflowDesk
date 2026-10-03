<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

class AuditLog extends Model
{
    use BelongsToTenant;

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'changes',
        'previous_hash',
        'hash',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * App-level fail-fast mirror of the DB trigger: Eloquent should never
     * even attempt an update/delete, but the trigger is the real backstop
     * against anything that bypasses this model (raw SQL, another client).
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Audit log entries are immutable.');
        });

        static::deleting(function () {
            throw new RuntimeException('Audit log entries are immutable.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
