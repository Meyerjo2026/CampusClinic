<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->nullable()->constrained('users')->onDelete('set null')->comment('User who performed the action (practitioner, admin, system)');
            $table->foreignId('patient_id')->nullable()->constrained('patients')->onDelete('set null')->comment('Patient whose data was accessed');
            $table->enum('action', ['READ', 'WRITE', 'EXPORT', 'DELETE', 'PRINT', 'SHARE'])->comment('Action type per POPIA/HIPAA audit requirements');
            $table->string('resource_type')->comment('Resource accessed: Patient, Encounter, MedicationRequest, etc.');
            $table->unsignedBigInteger('resource_id')->nullable()->comment('Specific resource ID accessed');
            $table->ipAddress('ip_address')->comment('Client IP address');
            $table->text('user_agent')->nullable()->comment('Client user agent string');
            $table->string('session_id')->nullable()->comment('Session identifier for correlation');
            $table->text('reason')->nullable()->comment('Clinical/operational reason for access (required for clinical access)');
            $table->jsonb('metadata')->nullable()->comment('Additional context: fields accessed, query parameters, etc.');
            $table->enum('consent_status', ['valid', 'revoked', 'expired', 'emergency_override'])->comment('POPIA consent status at time of access');
            $table->boolean('is_emergency_access')->default(false)->comment('Emergency access override flag (HPCSA statutory exemption)');
            $table->timestamp('timestamp')->useCurrent()->comment('Immutable audit timestamp');
            $table->string('outcome')->default('success')->comment('Access outcome: success, denied, error');

            // Immutable table - no updates/deletes allowed
            $table->index(['patient_id', 'timestamp']);
            $table->index(['operator_id', 'timestamp']);
            $table->index('action');
            $table->index('resource_type');
            $table->index('timestamp');
            $table->index('consent_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
