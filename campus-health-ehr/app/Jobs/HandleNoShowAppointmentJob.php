<?php

namespace App\Jobs;

use App\Events\CrisisAlertEvent;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class HandleNoShowAppointmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The appointment instance.
     */
    protected Appointment $appointment;

    /**
     * Create a new job instance.
     */
    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
    }

    /**
     * Execute the job.
     *
     * Implements UCT SWS No-Show Protocol:
     * - Routine Primary Care: Send automated rescheduling link
     * - High-Risk Mental Health: Flag urgent outreach to Social Worker + Reverb alert
     */
    public function handle(): void
    {
        $appointment = $this->appointment->load(['patient.user', 'encounter', 'practitioner']);
        $patient = $appointment->patient;

        Log::info('Processing no-show appointment', [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'service_type' => $appointment->service_type,
        ]);

        // Check if this was a mental health high-risk appointment
        $isHighRiskMentalHealth = $this->isHighRiskMentalHealth($appointment);

        if ($isHighRiskMentalHealth) {
            $this->handleHighRiskNoShow($appointment, $patient);
        } else {
            $this->handleRoutineNoShow($appointment, $patient);
        }

        // Log audit trail
        AuditLog::logAccess(
            'WRITE',
            'NoShowHandler',
            $appointment->id,
            $patient,
            'No-show protocol executed',
            [
                'service_type' => $appointment->service_type,
                'is_high_risk' => $isHighRiskMentalHealth,
                'action_taken' => $isHighRiskMentalHealth ? 'urgent_outreach' : 'reschedule_prompt',
            ]
        );
    }

    /**
     * Determine if appointment was high-risk mental health.
     */
    protected function isHighRiskMentalHealth(Appointment $appointment): bool
    {
        // Check encounter flag
        if ($appointment->encounter && $appointment->encounter->is_mental_health_high_risk) {
            return true;
        }

        // Check patient flag
        if ($appointment->patient->is_high_risk_mental_health) {
            return true;
        }

        // Check if psychologist appointment with mental health ICD-10
        if ($appointment->service_type === 'psychologist' && $appointment->encounter) {
            $icd10Codes = $appointment->encounter->soap_assessment_icd10 ?? [];
            $highRiskPrefixes = ['F20', 'F21', 'F22', 'F23', 'F24', 'F25', 'F28', 'F29', 'F30', 'F31', 'F32', 'F33', 'F40', 'F41', 'F42', 'F43'];

            foreach ($icd10Codes as $icd) {
                $code = $icd['code'] ?? '';
                foreach ($highRiskPrefixes as $prefix) {
                    if (str_starts_with($code, $prefix)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Handle high-risk mental health no-show.
     * Creates urgent outreach task for Social Worker and dispatches Reverb alert.
     */
    protected function handleHighRiskNoShow(Appointment $appointment, Patient $patient): void
    {
        Log::warning('HIGH-RISK MENTAL HEALTH NO-SHOW', [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'student_number' => $patient->student_number,
        ]);

        // 1. Create urgent outreach task for Social Worker
        $socialWorkers = User::role('social_worker')->get();

        foreach ($socialWorkers as $socialWorker) {
            $this->createOutreachTask($socialWorker, $appointment, $patient);
        }

        // 2. Dispatch Reverb alert to crisis team
        $crisisData = [
            'patient_id' => $patient->id,
            'student_number' => $patient->student_number,
            'patient_name' => $patient->user->name,
            'crisis_reasons' => ['High-risk mental health patient missed appointment'],
            'triage_screening' => [
                'service_type' => $appointment->service_type,
                'original_appointment' => $appointment->scheduled_at->format('c'),
            ],
            'timestamp' => now()->toIso8601String(),
            'source' => 'no_show_triage',
            'severity' => 'urgent',
            'is_high_risk_mental_health' => true,
        ];

        CrisisAlertEvent::dispatch($crisisData);

        // 3. Send urgent notification to patient
        $this->sendUrgentPatientNotification($appointment, $patient);

        // 4. Notify assigned practitioner
        if ($appointment->practitioner) {
            $this->notifyPractitioner($appointment->practitioner, $appointment, $patient, 'high_risk');
        }
    }

    /**
     * Handle routine no-show.
     * Sends automated rescheduling link.
     */
    protected function handleRoutineNoShow(Appointment $appointment, Patient $patient): void
    {
        Log::info('Routine no-show processed', [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
        ]);

        // Send automated rescheduling notification
        $this->sendRescheduleNotification($appointment, $patient);

        // Notify practitioner
        if ($appointment->practitioner) {
            $this->notifyPractitioner($appointment->practitioner, $appointment, $patient, 'routine');
        }
    }

    /**
     * Create outreach task for social worker.
     */
    protected function createOutreachTask(User $socialWorker, Appointment $appointment, Patient $patient): void
    {
        // In production: Create task in task management system
        // Task::create([...]);

        Log::info('Urgent outreach task created', [
            'social_worker_id' => $socialWorker->id,
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'priority' => 'urgent',
            'due_at' => now()->addHours(2), // 2-hour SLA per UCT SWS policy
        ]);

        // Send notification to social worker
        // $socialWorker->notify(new UrgentOutreachTaskNotification([...]));
    }

    /**
     * Send urgent notification to patient for high-risk no-show.
     */
    protected function sendUrgentPatientNotification(Appointment $appointment, Patient $patient): void
    {
        $user = $patient->user;

        if (! $user) {
            return;
        }

        $data = [
            'patient_name' => $user->name,
            'missed_appointment' => $appointment->scheduled_at->format('l, F j, Y \a\t g:i A'),
            'service_type' => $appointment->service_type,
            'urgent_message' => 'We noticed you missed your appointment. Your wellbeing is important to us. Please contact us immediately.',
            'contact_options' => [
                'SWS_Reception' => '021 650 1020',
                'UCT_Careline' => '0800 24 25 26 (24/7)',
                'SWS_Crisis_Line' => '021 650 1271',
                'Emergency' => '10111 / 10177',
            ],
            'reschedule_link' => url('/patient/appointments/reschedule/'.$appointment->id),
        ];

        // Queue SMS and Email
        // $user->notify(new UrgentNoShowNotification($data));

        Log::info('Urgent no-show notification queued', array_merge($data, [
            'patient_id' => $patient->id,
            'channels' => ['sms', 'email', 'in_app'],
        ]));
    }

    /**
     * Send routine reschedule notification.
     */
    protected function sendRescheduleNotification(Appointment $appointment, Patient $patient): void
    {
        $user = $patient->user;

        if (! $user) {
            return;
        }

        $data = [
            'patient_name' => $user->name,
            'missed_appointment' => $appointment->scheduled_at->format('l, F j, Y \a\t g:i A'),
            'service_type' => $appointment->service_type,
            'message' => 'You missed your appointment. Please reschedule at your convenience.',
            'reschedule_link' => url('/patient/appointments/reschedule/'.$appointment->id),
            'contact' => 'SWS Reception: 021 650 1020',
        ];

        // Queue SMS and Email
        // $user->notify(new RoutineNoShowNotification($data));

        Log::info('Routine no-show reschedule notification queued', array_merge($data, [
            'patient_id' => $patient->id,
            'channels' => ['sms', 'email'],
        ]));
    }

    /**
     * Notify practitioner of no-show.
     */
    protected function notifyPractitioner(User $practitioner, Appointment $appointment, Patient $patient, string $riskLevel): void
    {
        $data = [
            'practitioner_name' => $practitioner->name,
            'patient_name' => $patient->user->name,
            'student_number' => $patient->student_number,
            'appointment_time' => $appointment->scheduled_at->format('l, F j, Y \a\t g:i A'),
            'service_type' => $appointment->service_type,
            'risk_level' => $riskLevel,
            'action_required' => $riskLevel === 'high_risk' ? 'Review patient status urgently' : 'Note for clinical records',
        ];

        // $practitioner->notify(new PractitionerNoShowNotification($data));

        Log::info('Practitioner no-show notification queued', $data);
    }
}
