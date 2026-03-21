<?php

namespace App\Support;

use App\Models\Church;
use Illuminate\Support\Carbon;

class HomecellScheduleGate
{
    public static function normalizeMonthlyDates(?array $dates): array
    {
        return collect($dates ?? [])
            ->filter(fn ($value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))
            ->map(function (string $value): ?string {
                try {
                    return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public static function nextOpenDate(?array $dates, ?Carbon $today = null): ?string
    {
        $anchor = ($today ?: now())->toDateString();

        return collect(self::normalizeMonthlyDates($dates))
            ->first(fn (string $date) => $date >= $anchor);
    }

    public static function validationMessage(Church $church, string $meetingDate, ?Carbon $today = null): ?string
    {
        if (! $church->homecell_schedule_locked) {
            return null;
        }

        $normalizedDates = self::normalizeMonthlyDates($church->homecell_monthly_dates);

        if ($normalizedDates === []) {
            return 'Homecell attendance is locked right now. Wait till the next homecell date is set by admin.';
        }

        $nextOpenDate = self::nextOpenDate($normalizedDates, $today);

        if (! $nextOpenDate) {
            return 'Homecell attendance is locked right now. Wait till the next homecell date is set by admin.';
        }

        if ($meetingDate !== $nextOpenDate) {
            return sprintf(
                'Homecell attendance is locked for now. Wait till %s.',
                Carbon::parse($nextOpenDate)->format('l, d M Y')
            );
        }

        return null;
    }
}
