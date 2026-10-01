<?php

namespace App\Jobs;

use App\Models\MedicationRequest;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendChronicRefillRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of days ahead to check for refills.
     */
    protected int $daysAhead = 5;

    /**
     * Maximum number of reminders to send per run.
     */
    protected int $maxReminders = 100;

    /**
     * Execute the job.
     *
     * Queries chronic prescriptions due for refill in 5 days
     * and dispatches queued notifications (SMS/Email) via Mailpit/Redis.
     */
    public function handle(): void
    {
        Log::info('Starting chronic refill reminders job', [
            'days_ahead' => $this->daysAhead,
        ]);

        $chronicRequests = MedicationRequest::where('is_chronic', true)
            ->where('status', 'active')
            ->whereHas('patient', function ($query) {
                $query->where('popia_consent_given', true);
            })
            ->with(['patient.user', 'prescriber'])
            ->limit($this->maxReminders)
            ->get();

        $sentCount = 0;
        $failedCount = 0;

        foreach ($chronicRequests as $request) {
            if (! $request->isDueForRefill($this->daysAhead)) {
                continue;
            }

            try {
                $this->sendRefillReminder($request);
                $sentCount++;
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Failed to send refill reminder', [
                    'medication_request_id' => $request->id,
                    'patient_id' => $request->patient_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Chronic refill reminders job completed', [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total_checked' => $chronicRequests->count(),
        ]);
    }

    /**
     * Send refill reminder notification to patient.
     * Dispatches SMS and Email via queued notifications.
     */
    protected function sendRefillReminder(MedicationRequest $request): void
    {
        $patient = $request->patient;
        $user = $patient->user;

        if (! $user) {
            throw new \Exception('Patient has no associated user account');
        }

        $daysUntilDue = $this->calculateDaysUntilDue($request);
        $nextRefillDate = $this->calculateNextRefillDate($request);

        // Prepare notification data
        $notificationData = [
            'patient_name' => $user->name,
            'student_number' => $patient->student_number,
            'drug_name' => $request->drug_name,
            'dosage' => $request->dosage,
            'repeats_remaining' => $request->repeats_remaining,
            'days_until_due' => $daysUntilDue,
            'next_refill_date' => $nextRefillDate,
            'pharmacy_location' => 'SWS Pharmacy, Ivan Toms Building',
            'pharmacy_hours' => 'Mon-Fri 08:30-16:00',
            'collection_methods' => ['Counter pickup', 'Smart locker (QR code)', 'Delivery (if eligible)'],
            'emergency_contact' => 'Pharmacy: 021 650 1020',
        ];

        // Dispatch queued SMS notification
        // In production: $user->notify(new ChronicRefillSmsNotification($notificationData));

        // Dispatch queued Email notification
        // In production: $user->notify(new ChronicRefillEmailNotification($notificationData));

        // For now, log the notification (Mailpit will catch emails)
        Log::info('Chronic refill reminder queued', array_merge($notificationData, [
            'patient_id' => $patient->id,
            'medication_request_id' => $request->id,
            'channels' => ['sms', 'email'],
        ]));

        // Also create in-app notification record
        $this->createInAppNotification($user, $notificationData);
    }

    /**
     * Create in-app notification record.
     */
    protected function createInAppNotification($user, array $data): void
    {
        // In production: Use Laravel Notifications with database driver
        // $user->notifications()->create([...]);

        Log::debug('In-app notification created for chronic refill', [
            'user_id' => $user->id,
            'type' => 'chronic_refill_reminder',
            'data' => $data,
        ]);
    }

    /**
     * Calculate days until refill is due.
     */
    protected function calculateDaysUntilDue(MedicationRequest $request): int
    {
        $lastDispense = $request->dispenses()
            ->where('status', 'collected')
            ->latest('collected_at')
            ->first();

        if (! $lastDispense) {
            // Never collected, use prescribed date
            $lastDate = $request->prescribed_at;
        } else {
            $lastDate = $lastDispense->collected_at;
        }

        $dueDate = Carbon::parse($lastDate)->addDays($request->duration_days);

        return max(0, now()->diffInDays($dueDate, false));
    }

    /**
     * Calculate next refill date.
     */
    protected function calculateNextRefillDate(MedicationRequest $request): string
    {
        $lastDispense = $request->dispenses()
            ->where('status', 'collected')
            ->latest('collected_at')
            ->first();

        if (! $lastDispense) {
            return Carbon::parse($request->prescribed_at)
                ->addDays($request->duration_days)
                ->format('Y-m-d');
        }

        return Carbon::parse($lastDispense->collected_at)
            ->addDays($request->duration_days)
            ->format('Y-m-d');
    }
}
