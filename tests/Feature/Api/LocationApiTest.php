<?php

namespace Tests\Feature\Api;

use Database\Seeders\NigeriaLocationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_nigeria_locations_seeder_loads_all_states_and_lgas(): void
    {
        $this->seed(NigeriaLocationsSeeder::class);

        $this->assertDatabaseCount('states', 37);
        $this->assertDatabaseCount('local_government_areas', 774);
        $this->assertDatabaseHas('states', ['name' => 'Lagos', 'slug' => 'lagos']);
        $this->assertDatabaseHas('local_government_areas', ['name' => 'Ikeja']);
    }

    public function test_locations_endpoints_return_states_and_filtered_lgas(): void
    {
        $this->seed(NigeriaLocationsSeeder::class);

        $statesResponse = $this->getJson('/api/locations/states');

        $statesResponse->assertOk()
            ->assertJsonCount(37, 'data');

        $lagosStateId = collect($statesResponse->json('data'))
            ->firstWhere('slug', 'lagos')['id'];

        $lagosLgasResponse = $this->getJson('/api/locations/lgas?state_id='.$lagosStateId);
        $stateRouteResponse = $this->getJson('/api/locations/states/'.$lagosStateId.'/lgas');

        $lagosLgasResponse->assertOk();
        $stateRouteResponse->assertOk();

        $lagosLgas = collect($lagosLgasResponse->json('data'));

        $this->assertTrue($lagosLgas->contains(fn (array $item) => $item['name'] === 'Ikeja'));
        $this->assertTrue($lagosLgas->contains(fn (array $item) => $item['name'] === 'Eti-Osa'));
    }
}
