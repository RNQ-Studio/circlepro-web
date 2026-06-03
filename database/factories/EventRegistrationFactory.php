<?php

namespace Database\Factories;

use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\User;
use App\Support\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventRegistration>
 */
class EventRegistrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_division_id' => EventDivision::factory(),
            'user_id' => User::factory(),
            'status' => RegistrationStatus::Confirmed,
            'bib_number' => 'BIB-' . fake()->unique()->bothify('??-###'),
            'qr_code' => 'REG-' . Str::random(10) . '-' . strtoupper(Str::random(4)),
            'checked_in_at' => null,
        ];
    }
}
