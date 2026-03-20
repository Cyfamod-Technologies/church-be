<?php

namespace Tests\Feature\Api;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomecellApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_homecell_can_be_created_with_branch_assignment_and_leaders(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $branchTagId = $this->postJson('/api/branch-tags', [
            'church_id' => $church->id,
            'name' => 'District',
        ])->json('data.id');

        $branchId = $this->postJson('/api/branches', [
            'name' => 'Grace Branch',
            'branch_tag_id' => $branchTagId,
            'created_by_church_id' => $church->id,
            'created_by_user_id' => $user->id,
            'created_by_actor_type' => 'user',
        ])->json('data.id');

        $response = $this->postJson('/api/homecells', [
            'church_id' => $church->id,
            'branch_id' => $branchId,
            'name' => 'Dominion Cell',
            'meeting_day' => 'Wednesday',
            'meeting_time' => '18:30',
            'host_name' => 'Mr. James',
            'city_area' => 'Lekki Phase 1',
            'address' => '12 Admiralty Way',
            'leaders' => [
                [
                    'name' => 'Sis. Mercy Eze',
                    'role' => 'Leader',
                    'phone' => '+2348011111111',
                    'email' => 'mercy@example.com',
                    'is_primary' => true,
                ],
                [
                    'name' => 'Bro. Daniel Obi',
                    'role' => 'Assistant Leader',
                    'phone' => '+2348022222222',
                    'email' => 'daniel@example.com',
                    'is_primary' => false,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Dominion Cell')
            ->assertJsonPath('data.branch.id', $branchId)
            ->assertJsonCount(2, 'data.leaders');

        $this->assertDatabaseHas('homecells', [
            'church_id' => $church->id,
            'branch_id' => $branchId,
            'name' => 'Dominion Cell',
        ]);

        $this->assertDatabaseHas('homecell_leaders', [
            'name' => 'Sis. Mercy Eze',
            'role' => 'Leader',
            'is_primary' => 1,
        ]);
    }

    public function test_homecell_can_be_updated_and_loaded_in_list(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $branchTagId = $this->postJson('/api/branch-tags', [
            'church_id' => $church->id,
            'name' => 'Zone',
        ])->json('data.id');

        $branchId = $this->postJson('/api/branches', [
            'name' => 'Victory Branch',
            'branch_tag_id' => $branchTagId,
            'created_by_church_id' => $church->id,
            'created_by_user_id' => $user->id,
            'created_by_actor_type' => 'user',
        ])->json('data.id');

        $homecellId = $this->postJson('/api/homecells', [
            'church_id' => $church->id,
            'name' => 'Grace Cell',
            'meeting_day' => 'Thursday',
            'meeting_time' => '19:00',
            'leaders' => [
                [
                    'name' => 'Sis. Ada',
                    'role' => 'Leader',
                    'is_primary' => true,
                ],
            ],
        ])->json('data.id');

        $updateResponse = $this->putJson('/api/homecells/'.$homecellId, [
            'branch_id' => $branchId,
            'name' => 'Grace Cell Updated',
            'meeting_day' => 'Friday',
            'meeting_time' => '18:00',
            'host_name' => 'Mrs. Grace',
            'city_area' => 'Gwarinpa',
            'status' => 'active',
            'leaders' => [
                [
                    'name' => 'Bro. John',
                    'role' => 'Leader',
                    'phone' => '+2348033333333',
                    'is_primary' => true,
                ],
            ],
        ]);

        $listResponse = $this->getJson('/api/homecells?church_id='.$church->id);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Grace Cell Updated')
            ->assertJsonPath('data.branch.id', $branchId)
            ->assertJsonPath('data.leaders.0.name', 'Bro. John');

        $listResponse->assertOk()
            ->assertJsonPath('meta.stats.total_homecells', 1)
            ->assertJsonPath('meta.stats.assigned_to_branches', 1)
            ->assertJsonPath('meta.stats.leaders_assigned', 1);
    }
}
