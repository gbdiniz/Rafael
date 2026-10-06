<?php

namespace App\Models;

use App\Enums\RecordKind;
use App\Records\LocalDay;
use Database\Factories\RecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable(['kind', 'title', 'scheduled_at', 'last_transcript', 'last_voice_turn_id'])]
class Record extends Model
{
    /** @use HasFactory<RecordFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => RecordKind::class,
            'scheduled_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Record>  $query
     * @return Builder<Record>
     */
    public function scopeAppointments(Builder $query): Builder
    {
        return $query->where('kind', RecordKind::Appointment);
    }

    /**
     * @param  Builder<Record>  $query
     * @return Builder<Record>
     */
    public function scopeTasks(Builder $query): Builder
    {
        return $query->where('kind', RecordKind::Task);
    }

    /**
     * @param  Builder<Record>  $query
     * @return Builder<Record>
     */
    public function scopeOnTheBooks(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * @param  Builder<Record>  $query
     * @return Builder<Record>
     */
    public function scopeScheduledOnLocalDay(Builder $query, string $timezone, Carbon $now): Builder
    {
        $localDay = LocalDay::forTimezone($timezone);

        return $query
            ->appointments()
            ->where('scheduled_at', '>=', $localDay->startOfDayUtc($now))
            ->where('scheduled_at', '<', $localDay->startOfNextDayUtc($now));
    }

    /**
     * @param  Builder<Record>  $query
     * @return Builder<Record>
     */
    public function scopeScheduledBeforeLocalDay(Builder $query, string $timezone, Carbon $now): Builder
    {
        $localDay = LocalDay::forTimezone($timezone);

        return $query
            ->appointments()
            ->where('scheduled_at', '<', $localDay->startOfDayUtc($now));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<VoiceTurn, $this>
     */
    public function lastVoiceTurn(): BelongsTo
    {
        return $this->belongsTo(VoiceTurn::class, 'last_voice_turn_id');
    }
}
