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
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->onDelete('set null')->comment('FHIR Appointment reference');
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade')->comment('FHIR Patient reference');
            $table->foreignId('practitioner_id')->constrained('users')->onDelete('cascade')->comment('FHIR Practitioner reference');
            $table->text('soap_subjective')->nullable()->comment('SOAP Subjective: Chief complaint, HPI, allergy flags, social history');
            $table->text('soap_objective')->nullable()->comment('SOAP Objective: Vital signs (BP, HR, SpO2, Temp, Glucose), physical exam findings');
            $table->jsonb('soap_assessment_icd10')->nullable()->comment('SOAP Assessment: Primary & secondary ICD-10 diagnostic codes array');
            $table->text('soap_plan')->nullable()->comment('SOAP Plan: e-Prescriptions, lab requisitions, referrals, follow-up directives');
            $table->enum('triage_level', ['emergency', 'urgent', 'routine'])->default('routine')->comment('Triage acuity level per UCT SWS protocol');
            $table->jsonb('fhir_data')->nullable()->comment('FHIR R4 Encounter Resource JSON');
            $table->jsonb('vital_signs')->nullable()->comment('Structured vital signs per FHIR Observation');
            $table->jsonb('allergies')->nullable()->comment('Allergy/intolerance list per FHIR AllergyIntolerance');
            $table->jsonb('medications_prescribed')->nullable()->comment('Medications prescribed during encounter');
            $table->jsonb('referrals')->nullable()->comment('Referrals made during encounter');
            $table->jsonb('follow_up_instructions')->nullable()->comment('Post-consultation care instructions');
            $table->boolean('is_mental_health_high_risk')->default(false)->comment('Flag for MentalHealth-HighRisk pathway');
            $table->boolean('confidentiality_level')->default('standard')->comment('Confidentiality level per HPCSA/SACSSP');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
            $table->index(['practitioner_id', 'created_at']);
            $table->index('triage_level');
            $table->index('is_mental_health_high_risk');
            $table->index('appointment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
