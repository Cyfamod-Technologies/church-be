<?php

namespace Database\Seeders;

use App\Models\Homecell;
use App\Models\HomecellAttendanceRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LocalHomecellAttendanceTestSeeder extends Seeder
{
    public function run(): void
    {
        $meetingDate = $this->resolveMeetingDate();

        $homecells = Homecell::query()
            ->with([
                'leaders' => fn ($query) => $query
                    ->select('id', 'homecell_id', 'user_id', 'is_primary', 'sort_order')
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order'),
            ])
            ->orderBy('church_id')
            ->orderBy('branch_id')
            ->orderBy('name')
            ->get();

        $seededCount = 0;

        foreach ($homecells as $homecell) {
            $maleCount = 8 + ($homecell->id % 10);
            $femaleCount = 10 + (($homecell->id * 2) % 11);
            $childrenCount = 2 + (($homecell->id * 3) % 6);
            $firstTimersCount = $homecell->id % 4;
            $newConvertsCount = $homecell->id % 3;
            $totalCount = $maleCount + $femaleCount + $childrenCount;
            $recordedByUserId = $homecell->leaders->first()?->user_id;

            HomecellAttendanceRecord::query()->updateOrCreate(
                [
                    'church_id' => $homecell->church_id,
                    'homecell_id' => $homecell->id,
                    'meeting_date' => $meetingDate,
                ],
                [
                    'branch_id' => $homecell->branch_id,
                    'recorded_by_user_id' => $recordedByUserId,
                    'male_count' => $maleCount,
                    'female_count' => $femaleCount,
                    'children_count' => $childrenCount,
                    'total_count' => $totalCount,
                    'first_timers_count' => $firstTimersCount,
                    'new_converts_count' => $newConvertsCount,
                    'offering_amount' => $totalCount * 100,
                    'notes' => sprintf(
                        'Local test seed for homecell report checks on %s.',
                        $meetingDate
                    ),
                ]
            );

            $seededCount++;
        }

        if ($this->command) {
            $this->command->info(sprintf(
                'Seeded test attendance for %d homecells on %s.',
                $seededCount,
                $meetingDate
            ));
        }
    }

    private function resolveMeetingDate(): string
    {
        $today = now()->startOfDay();

        if ($today->dayOfWeek === Carbon::SATURDAY) {
            return $today->toDateString();
        }

        return $today->previous(Carbon::SATURDAY)->toDateString();
    }
}
