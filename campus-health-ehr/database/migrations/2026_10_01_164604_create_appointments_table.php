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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade')->comment('FHIR Patient reference');
            $table->foreignId('practitioner_id')->nullable()->constrained('users')->onDelete('set null')->comment('FHIR Practitioner reference (HPCSA/SAPC/SACSSP registered)');
            $table->enum('service_type', ['cnp', 'gp', 'psychologist', 'pharmacy_pickup'])->comment('Service type: CNP Nurse (free), GP Doctor (medical aid), Psychologist (stepped-care), Pharmacy Pickup');
            $table->enum('status', ['booked', 'arrived', 'in-consultation', 'fulfilled', 'noshow', 'cancelled'])->default('booked')->comment('Appointment state machine per FHIR Appointment status');
            $table->timestamp('scheduled_at')->comment('Appointment scheduled date/time');
            $table->timestamp('started_at')->nullable()->comment('Actual consultation start time');
            $table->timestamp('ended_at')->nullable()->comment('Actual consultation end time');
            $table->integer('duration_minutes')->default(30)->comment('Slot duration in minutes');
            $table->jsonb('fhir_data')->nullable()->comment('FHIR R4 Appointment Resource JSON');
            $table->text('cancellation_reason')->nullable()->comment('Reason for cancellation if applicable');
            $table->boolean('is_walk_in')->default(false)->comment('Walk-in triage buffer flag');
            $table->jsonb('triage_screening')->nullable()->comment('Pre-booking triage screening responses');
            $table->boolean('crisis_flag_triggered')->default(false)->comment('Whether crisis alert was triggered during booking');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['practitioner_id', 'scheduled_at']);
            $table->index(['service_type', 'status']);
            $table->index('scheduled_at');
            $table->index('crisis_flag_triggered');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
