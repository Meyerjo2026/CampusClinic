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
        Schema::create('medication_dispenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_request_id')->constrained('medication_requests')->onDelete('cascade')->comment('FHIR MedicationRequest reference');
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade')->comment('FHIR Patient reference');
            $table->foreignId('dispenser_id')->constrained('users')->onDelete('cascade')->comment('FHIR Practitioner reference (SAPC registered pharmacist)');
            $table->string('drug_name')->comment('Medication name as dispensed');
            $table->unsignedTinyInteger('sapc_schedule')->comment('SAPC Schedule 1-6');
            $table->string('batch_number')->comment('Manufacturer batch number for traceability');
            $table->date('expires_at')->comment('Medication expiry date');
            $table->integer('quantity_dispensed')->comment('Quantity actually dispensed');
            $table->string('dosage')->comment('Dosage instructions');
            $table->enum('status', ['prepared', 'dispensed', 'collected', 'returned', 'expired'])->default('prepared')->comment('Dispensing status');
            $table->timestamp('dispensed_at')->nullable()->comment('Actual dispensing timestamp');
            $table->timestamp('collected_at')->nullable()->comment('Patient collection timestamp');
            $table->string('collection_method')->nullable()->comment('Collection: counter, locker, delivery');
            $table->string('qr_code_token')->nullable()->comment('Secure QR token for pickup validation');
            $table->jsonb('fhir_data')->nullable()->comment('FHIR R4 MedicationDispense Resource JSON');
            $table->text('pharmacist_notes')->nullable()->comment('Pharmacist clinical notes');
            $table->jsonb('counselling_provided')->nullable()->comment('Patient counselling topics covered');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index(['medication_request_id', 'status']);
            $table->index('batch_number');
            $table->index('expires_at');
            $table->index('qr_code_token');
            $table->index('dispensed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_dispenses');
    }
};
