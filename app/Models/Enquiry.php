<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use App\Enums\Priority;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EnquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<EnquiryFactory> */
class Enquiry extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'student_name',
        'student_email',
        'subject',
        'description',
        'status',
        'priority',
        'assigned_to',
        'created_by',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnquiryStatus::class,
            'priority' => Priority::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Enquiry $enquiry) {
            if ($enquiry->isDirty() && ! $enquiry->isDirty('version')) {
                $enquiry->version++;
            }
        });
    }

    public function etag(): string
    {
        return '"'.$this->version.'"';
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
