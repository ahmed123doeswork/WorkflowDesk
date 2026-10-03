<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('changes')->nullable();
            $table->string('previous_hash')->nullable();
            $table->string('hash');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'id']);
            $table->index(['auditable_type', 'auditable_id']);
        });

        // Tamper-evidence only works if the log itself can't be edited after
        // the fact, including by raw SQL that bypasses Eloquent entirely.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_prevent_update
            BEFORE UPDATE ON audit_logs
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs rows are immutable: update is not allowed'
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_logs_prevent_delete
            BEFORE DELETE ON audit_logs
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs rows are immutable: delete is not allowed'
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_delete');

        Schema::dropIfExists('audit_logs');
    }
};
