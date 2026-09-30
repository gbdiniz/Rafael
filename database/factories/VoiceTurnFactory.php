<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VoiceTurn;
use App\VoiceTurnStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VoiceTurn>
 */
class VoiceTurnFactory extends Factory
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
            'uuid' => (string) Str::uuid(),
            'status' => VoiceTurnStatus::Uploaded,
            'locale' => 'pt-BR',
            'disk' => 'local',
            'audio_path' => 'voice-turns/'.Str::uuid().'.webm',
            'audio_deleted_at' => null,
            'transcript' => null,
            'error_message' => null,
            'duration_ms' => null,
            'consumed_at' => null,
        ];
    }

    public function uploaded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoiceTurnStatus::Uploaded,
            'transcript' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoiceTurnStatus::Completed,
            'transcript' => fake()->sentence(),
            'audio_path' => null,
            'disk' => null,
            'audio_deleted_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VoiceTurnStatus::Failed,
            'transcript' => null,
            'error_message' => 'Transcription failed.',
            'audio_path' => null,
            'disk' => null,
            'audio_deleted_at' => now(),
        ]);
    }
}
