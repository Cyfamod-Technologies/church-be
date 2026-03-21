<?php

namespace Tests\Feature\Api;

use App\Models\Church;
use App\Models\ChurchUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurchUnitApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_church_unit_can_be_created_and_listed(): void
    {
        $church = Church::factory()->create();

        $createResponse = $this->postJson('/api/church-units', [
            'church_id' => $church->id,
            'name' => 'Choir',
            'description' => 'Worship and praise team.',
            'status' => 'active',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.name', 'Choir')
            ->assertJsonPath('data.status', 'active');

        $listResponse = $this->getJson('/api/church-units?church_id='.$church->id);

        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Choir')
            ->assertJsonPath('meta.stats.total_units', 1);
    }

    public function test_church_unit_can_be_updated(): void
    {
        $church = Church::factory()->create();

        $unit = ChurchUnit::create([
            'church_id' => $church->id,
            'name' => 'Media',
            'status' => 'active',
        ]);

        $response = $this->putJson('/api/church-units/'.$unit->id, [
            'name' => 'Media & Production',
            'description' => 'Streaming, camera, and projection team.',
            'status' => 'inactive',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Media & Production')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('church_units', [
            'id' => $unit->id,
            'name' => 'Media & Production',
            'status' => 'inactive',
        ]);
    }
}
