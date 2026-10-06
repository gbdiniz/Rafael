<?php

namespace App\Models;

use App\Enums\VoiceTurnStatus;
use Database\Factories\VoiceTurnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'status',
    'locale',
    'disk',
    'audio_path',
    'audio_deleted_at',
    'transcript',
    'error_message',
    'duration_ms',
    'consumed_at',
])]
class VoiceTurn extends Model
{
    /** @use HasFactory<VoiceTurnFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VoiceTurnStatus::class,
            'audio_deleted_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Record, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(Record::class, 'last_voice_turn_id');
    }
}
