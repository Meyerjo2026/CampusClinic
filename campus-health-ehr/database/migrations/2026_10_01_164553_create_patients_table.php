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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Link to Student/User account');
            $table->jsonb('fhir_data')->nullable()->comment('FHIR R4 Patient Resource JSON');
            $table->boolean('popia_consent_given')->default(false)->comment('POPIA Section 26/32 explicit consent for special personal information processing');
            $table->timestamp('popia_consent_at')->nullable()->comment('Timestamp when POPIA consent was given');
            $table->jsonb('consent_metadata')->nullable()->comment('Consent scope, version, and withdrawal history');
            $table->string('student_number')->unique()->comment('Institutional student identifier');
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable()->comment('FHIR AdministrativeGender code');
            $table->jsonb('contact_details')->nullable()->comment('FHIR ContactPoint array');
            $table->jsonb('address')->nullable()->comment('FHIR Address array');
            $table->jsonb('emergency_contact')->nullable()->comment('Emergency contact per UCT SWS policy');
            $table->boolean('is_high_risk_mental_health')->default(false)->comment('Flag for MentalHealth-HighRisk stepped-care pathway');
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
            $table->index('student_number');
            $table->index('is_high_risk_mental_health');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
