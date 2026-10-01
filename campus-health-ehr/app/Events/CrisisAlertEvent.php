<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrisisAlertEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Crisis alert data payload.
     */
    public array $crisisData;

    /**
     * Create a new event instance.
     *
     * Broadcasts immediately via Laravel Reverb to SWS-Triage-Dashboard
     * for real-time crisis response per UCT SWS Crisis Intervention SOP.
     */
    public function __construct(array $crisisData)
    {
        $this->crisisData = array_merge([
            'alert_id' => 'CRISIS-'.now()->format('YmdHis').'-'.random_int(1000, 9999),
            'timestamp' => now()->toIso8601String(),
            'source' => 'booking_triage',
            'severity' => 'critical',
            'acknowledged' => false,
            'acknowledged_by' => null,
            'acknowledged_at' => null,
        ], $crisisData);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * Channels:
     * - SWS-Triage-Dashboard: Main triage nurse dashboard
     * - SWS-Crisis-Team: Crisis response team (psychologists, social workers)
     * - SWS-Security: Campus Protection Services (for physical emergencies)
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('SWS-Triage-Dashboard'),
        ];

        // Add crisis team channel for mental health crises
        if (in_array('suicidal', $this->crisisData['crisis_reasons'] ?? []) ||
            in_array('self_harm', $this->crisisData['crisis_reasons'] ?? [])) {
            $channels[] = new Channel('SWS-Crisis-Team');
        }

        // Add security channel for physical emergencies
        if (in_array('chest_pain', $this->crisisData['crisis_reasons'] ?? []) ||
            in_array('dyspnea', $this->crisisData['crisis_reasons'] ?? []) ||
            in_array('trauma', $this->crisisData['crisis_reasons'] ?? [])) {
            $channels[] = new Channel('SWS-Security');
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'crisis.alert';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'alert_id' => $this->crisisData['alert_id'],
            'patient' => [
                'id' => $this->crisisData['patient_id'],
                'student_number' => $this->crisisData['student_number'],
                'name' => $this->crisisData['patient_name'],
                'is_high_risk_mental_health' => $this->crisisData['is_high_risk_mental_health'] ?? false,
            ],
            'crisis' => [
                'reasons' => $this->crisisData['crisis_reasons'],
                'severity' => $this->crisisData['severity'],
                'source' => $this->crisisData['source'],
            ],
            'triage_screening' => $this->crisisData['triage_screening'] ?? [],
            'timestamp' => $this->crisisData['timestamp'],
            'emergency_contacts' => [
                'sws_crisis_line' => '021 650 1271',
                'uct_careline' => '0800 24 25 26',
                'campus_protection' => '080 650 2222',
                'higher_health' => '0800 36 36 36',
                'sadag_suicide' => '0800 567 567',
                'emergency_services' => '10111 / 10177',
            ],
            'recommended_actions' => $this->getRecommendedActions(),
        ];
    }

    /**
     * Get recommended immediate actions based on crisis type.
     */
    protected function getRecommendedActions(): array
    {
        $actions = [
            'Immediately contact student via phone',
            'Alert Campus Protection Services if on campus',
            'Prepare crisis intervention room',
        ];

        $reasons = $this->crisisData['crisis_reasons'] ?? [];

        if (in_array('Suicidal ideation with plan detected', $reasons) ||
            in_array('Critical self-harm risk detected', $reasons)) {
            $actions = array_merge($actions, [
                'Assign psychologist for immediate assessment',
                'Contact UCT Careline (SADAG) for backup support',
                'If imminent risk: Accompany to Groote Schuur Hospital ED',
                'Notify emergency contact per student consent',
            ]);
        }

        if (in_array('Acute chest pain - possible cardiac emergency', $reasons)) {
            $actions = array_merge($actions, [
                'Call 10177 for ambulance immediately',
                'Administer aspirin 300mg if no allergy',
                'Prepare AED and oxygen',
                'Escort to Groote Schuur Hospital ED',
            ]);
        }

        if (in_array('Acute dyspnea - respiratory emergency', $reasons)) {
            $actions = array_merge($actions, [
                'Call 10177 for ambulance',
                'Administer high-flow oxygen',
                'Position upright',
                'Prepare for possible intubation',
            ]);
        }

        if (in_array('Acute trauma - requires emergency department', $reasons)) {
            $actions = array_merge($actions, [
                'Call 10177 for ambulance',
                'Control bleeding, immobilize spine',
                'Activate trauma team at Groote Schuur',
            ]);
        }

        return $actions;
    }

    /**
     * Determine if event should be broadcast.
     */
    public function broadcastWhen(): bool
    {
        return true; // Always broadcast crisis alerts
    }
}
