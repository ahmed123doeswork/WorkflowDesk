<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->timestamp('response_due_at')->nullable()->after('priority');
            $table->timestamp('resolution_due_at')->nullable()->after('response_due_at');
            $table->timestamp('responded_at')->nullable()->after('resolution_due_at');
            $table->timestamp('resolved_at')->nullable()->after('responded_at');
            $table->string('sla_status')->default('on_track')->after('resolved_at');

            $table->index(['tenant_id', 'sla_status']);
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn([
                'response_due_at',
                'resolution_due_at',
                'responded_at',
                'resolved_at',
                'sla_status',
            ]);
        });
    }
};
