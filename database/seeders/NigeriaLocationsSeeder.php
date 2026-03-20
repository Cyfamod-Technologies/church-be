<?php

namespace Database\Seeders;

use App\Models\LocalGovernmentArea;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class NigeriaLocationsSeeder extends Seeder
{
    public function run(): void
    {
        $payload = json_decode(
            file_get_contents(database_path('seeders/data/nigeria-states-lgas.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $states = collect($payload['states'] ?? []);
        $lgas = collect($payload['lgas'] ?? []);

        $this->seedStates($states);
        $this->seedLgas($lgas);
    }

    private function seedStates(Collection $states): void
    {
        $states->each(function (array $state): void {
            State::query()->updateOrCreate(
                ['slug' => $state['slug']],
                [
                    'name' => $state['name'],
                    'display_order' => $state['display_order'],
                ]
            );
        });
    }

    private function seedLgas(Collection $lgas): void
    {
        $stateIdsBySlug = State::query()
            ->pluck('id', 'slug');

        $lgas->each(function (array $lga) use ($stateIdsBySlug): void {
            LocalGovernmentArea::query()->updateOrCreate(
                ['slug' => $lga['slug']],
                [
                    'state_id' => $stateIdsBySlug[$lga['state_slug']],
                    'name' => $lga['name'],
                    'headquarters' => $lga['headquarters'],
                    'display_order' => $lga['display_order'],
                ]
            );
        });
    }
}
