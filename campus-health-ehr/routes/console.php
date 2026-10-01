<?php

use App\Jobs\HandleNoShowAppointmentJob;
use App\Jobs\SendChronicRefillRemindersJob;
use App\Models\MedicationDispense;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Scheduled Jobs for Campus Health EHR Platform
 *
 * Runs on Laravel Herd with Redis queue worker
 */

// Daily at 08:00 - Check chronic medications due for refill in 5 days
// Sends SMS/Email reminders via queued notifications
Schedule::job(new SendChronicRefillRemindersJob)
    ->dailyAt('08:00')
    ->timezone('Africa/Johannesburg')
    ->description('Send chronic medication refill reminders')
    ->onOneServer()
    ->withoutOverlapping(10);

// Every 15 minutes - Process any pending no-show appointments
// (In production, this would be triggered by appointment status change event)
Schedule::call(function () {
    // This is a placeholder - no-shows are handled via event-driven job dispatch
    // AppointmentController::updateStatus dispatches HandleNoShowAppointmentJob
})
    ->everyFifteenMinutes()
    ->description('Process no-show appointments (event-driven)');

// Daily at 06:00 - Generate daily appointment schedule for practitioners
Schedule::call(function () {
    // In production: Generate daily schedule reports
    // DailyScheduleReportJob::dispatch();
})
    ->dailyAt('06:00')
    ->timezone('Africa/Johannesburg')
    ->description('Generate daily practitioner schedules');

// Weekly on Monday 07:00 - Generate weekly clinic utilization report
Schedule::call(function () {
    // In production: WeeklyUtilizationReportJob::dispatch();
})
    ->weeklyOn(1, '07:00') // Monday
    ->timezone('Africa/Johannesburg')
    ->description('Generate weekly clinic utilization report');

// Monthly on 1st at 02:00 - Archive old audit logs (POPIA retention)
// Schedule::call(function () {
//     ArchiveAuditLogsJob::dispatch();
// })
//     ->monthlyOn(1, '02:00')
//     ->timezone('Africa/Johannesburg')
//     ->description('Archive audit logs per POPIA retention policy');

// Daily at 23:59 - Clean up expired QR codes for medication collection
Schedule::call(function () {
    MedicationDispense::where('status', 'prepared')
        ->where('created_at', '<', now()->subDays(7))
        ->update(['status' => 'expired']);
})
    ->dailyAt('23:59')
    ->timezone('Africa/Johannesburg')
    ->description('Expire old medication collection QR codes');
