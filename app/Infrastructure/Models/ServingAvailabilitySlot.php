<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ServingAvailabilitySlot extends Model
{
    protected $table = 'serving_availability_slots';

    protected $fillable = [
        'serving_id',
        'day_of_week',
        'date',
        'start_time',
        'end_time',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
    ];

    public function serving(): BelongsTo
    {
        return $this->belongsTo(Serving::class, 'serving_id');
    }

    /**
     * Check whether there is an existing overlapping slot for the same serving.
     *
     * Overlap condition: existing.start_time < new_end AND existing.end_time > new_start
     * This method checks slots that match either the given date or day_of_week.
     *
     * @param int $servingId
     * @param string $startTime (HH:MM:SS)
     * @param string $endTime (HH:MM:SS)
     * @param string|null $date (Y-m-d)
     * @param int|null $dayOfWeek (0-6)
     * @param int|null $excludeId
     * @return bool
     */
    public static function overlapsExist(int $servingId, string $startTime, string $endTime, ?string $date = null, ?int $dayOfWeek = null, ?int $excludeId = null): bool
    {
        $query = self::where('serving_id', $servingId)
            ->where(function ($q) use ($date, $dayOfWeek) {
                if ($date) {
                    $q->where('date', $date);
                }

                if ($dayOfWeek !== null) {
                    $q->orWhere('day_of_week', $dayOfWeek);
                }
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->whereRaw("start_time < ? AND end_time > ?", [$endTime, $startTime]);

        return $query->exists();
    }
}
