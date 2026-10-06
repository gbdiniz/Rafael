<?php

namespace Database\Factories;

use App\Enums\RecordKind;
use App\Models\Record;
use App\Models\User;
use App\Models\VoiceTurn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Record>
 */
class RecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => RecordKind::Appointment,
            'title' => fake()->sentence(3),
            'scheduled_at' => now(),
            'last_transcript' => fake()->sentence(),
            'last_voice_turn_id' => null,
        ];
    }

    public function appointment(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => RecordKind::Appointment,
            'scheduled_at' => $attributes['scheduled_at'] ?? now(),
        ]);
    }

    public function task(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => RecordKind::Task,
        ]);
    }

    public function withoutDueDate(): static
    {
        return $this->task()->state(fn (array $attributes) => [
            'scheduled_at' => null,
        ]);
    }

    public function deleted(): static
    {
        return $this->afterCreating(function (Record $record): void {
            $record->delete();
        });
    }

    public function forVoiceTurn(VoiceTurn $voiceTurn): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $voiceTurn->user_id,
            'last_voice_turn_id' => $voiceTurn->id,
            'last_transcript' => $voiceTurn->transcript ?? fake()->sentence(),
        ]);
    }
}
