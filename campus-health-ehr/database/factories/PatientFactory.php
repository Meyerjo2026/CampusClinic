<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Patient>
 */
class PatientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Patient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null, // Will be set by relationship
            'student_number' => 'S' . fake()->unique()->numberBetween(1000000, 9999999),
            'date_of_birth' => fake()->dateTimeBetween('-30 years', '-18 years'),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'popia_consent_given' => true,
            'popia_consent_at' => now(),
            'consent_metadata' => [
                'version' => '1.0',
                'scope' => ['treatment', 'billing', 'research'],
                'method' => 'digital_signature',
            ],
            'contact_details' => [
                [
                    'system' => 'phone',
                    'value' => fake()->phoneNumber(),
                    'use' => 'mobile',
                ],
                [
                    'system' => 'email',
                    'value' => fake()->unique()->safeEmail(),
                    'use' => 'home',
                ],
            ],
            'address' => [
                [
                    'use' => 'home',
                    'line' => [fake()->streetAddress()],
                    'city' => fake()->city(),
                    'state' => fake()->state(),
                    'postalCode' => fake()->postcode(),
                    'country' => 'ZA',
                ],
            ],
            'emergency_contact' => [
                'name' => fake()->name(),
                'relationship' => fake()->randomElement(['Parent', 'Guardian', 'Spouse', 'Sibling']),
                'phone' => fake()->phoneNumber(),
            ],
            'is_high_risk_mental_health' => false,
        ];
    }

    /**
     * Indicate that the patient has given POPIA consent.
     */
    public function withConsent(): static
    {
        return $this->state(fn (array $attributes) => [
            'popia_consent_given' => true,
            'popia_consent_at' => now(),
        ]);
    }

    /**
     * Indicate that the patient has NOT given POPIA consent.
     */
    public function withoutConsent(): static
    {
        return $this->state(fn (array $attributes) => [
            'popia_consent_given' => false,
            'popia_consent_at' => null,
        ]);
    }

    /**
     * Indicate that the patient is high-risk mental health.
     */
    public function highRiskMentalHealth(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_high_risk_mental_health' => true,
        ]);
    }
}