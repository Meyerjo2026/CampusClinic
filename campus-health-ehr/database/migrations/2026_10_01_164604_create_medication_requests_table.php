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
        Schema::create('medication_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade')->comment('FHIR Patient reference');
            $table->foreignId('prescriber_id')->constrained('users')->onDelete('cascade')->comment('FHIR Practitioner reference (HPCSA registered prescriber)');
            $table->foreignId('encounter_id')->nullable()->constrained('encounters')->onDelete('set null')->comment('Originating encounter');
            $table->string('drug_name')->comment('Medication name (SAHPRA registered)');
            $table->string('generic_name')->nullable()->comment('INN generic name');
            $table->unsignedTinyInteger('sapc_schedule')->comment('SAPC Schedule 1-6 per Medicines Act 101 of 1965');
            $table->string('dosage')->comment('Dosage instructions (e.g., "500mg PO BD")');
            $table->string('route')->default('oral')->comment('Administration route');
            $table->string('frequency')->comment('Frequency (e.g., "BD", "TDS", "OD")');
            $table->integer('duration_days')->comment('Treatment duration in days');
            $table->integer('quantity')->comment('Total quantity prescribed');
            $table->unsignedInteger('repeats_issued')->default(0)->comment('Number of repeats authorized');
            $table->unsignedInteger('repeats_remaining')->default(0)->comment('Repeats remaining for chronic dispensing');
            $table->enum('status', ['active', 'dispensed', 'cancelled', 'expired', 'on_hold'])->default('active')->comment('Prescription status');
            $table->boolean('is_chronic')->default(false)->comment('Chronic medication flag for repeat dispensing');
            $table->date('prescribed_at')->comment('Prescription date');
            $table->date('expires_at')->comment('Prescription expiry (6 months per SAPC)');
            $table->jsonb('fhir_data')->nullable()->comment('FHIR R4 MedicationRequest Resource JSON');
            $table->text('clinical_indication')->nullable()->comment('ICD-10 linked clinical indication');
            $table->jsonb('drug_interactions_checked')->nullable()->comment('Drug interaction check results');
            $table->jsonb('allergy_alerts')->nullable()->comment('Allergy cross-check results');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['prescriber_id', 'prescribed_at']);
            $table->index('sapc_schedule');
            $table->index('is_chronic');
            $table->index('expires_at');
            $table->index('repeats_remaining');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_requests');
    }
};
