<?php

namespace Tests\Feature\Api;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_tags_can_be_listed_and_custom_tag_created(): void
    {
        $church = Church::factory()->create();

        $listResponse = $this->getJson('/api/branch-tags?church_id='.$church->id);
        $createResponse = $this->postJson('/api/branch-tags', [
            'church_id' => $church->id,
            'name' => 'Province',
        ]);

        $listResponse->assertOk()
            ->assertJsonFragment(['name' => 'District'])
            ->assertJsonFragment(['name' => 'Zone']);

        $createResponse->assertCreated()
            ->assertJsonPath('data.name', 'Province');
    }

    public function test_branches_can_be_created_reassigned_and_loaded_with_history(): void
    {
        $church = Church::factory()->create(['name' => 'Church Root']);
        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $districtTag = $this->postJson('/api/branch-tags', [
            'church_id' => $church->id,
            'name' => 'District',
        ])->json('data');

        $zoneTag = $this->postJson('/api/branch-tags', [
            'church_id' => $church->id,
            'name' => 'Zone North',
        ])->json('data');

        $branchAResponse = $this->postJson('/api/branches', [
            'name' => 'Church A',
            'branch_tag_id' => $districtTag['id'],
            'pastor_name' => 'Pst. A',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'created_by_church_id' => $church->id,
            'created_by_user_id' => $user->id,
            'created_by_actor_type' => 'user',
        ]);

        $branchBResponse = $this->postJson('/api/branches', [
            'name' => 'Church B',
            'branch_tag_id' => $zoneTag['id'],
            'pastor_name' => 'Pst. B',
            'city' => 'Abuja',
            'state' => 'FCT',
            'created_by_church_id' => $church->id,
            'created_by_user_id' => $user->id,
            'created_by_actor_type' => 'user',
        ]);

        $branchAId = $branchAResponse->json('data.id');
        $branchBId = $branchBResponse->json('data.id');

        $reassignResponse = $this->postJson('/api/branches/'.$branchBId.'/reassign', [
            'to_parent_branch_id' => $branchAId,
            'changed_by_church_id' => $church->id,
            'changed_by_user_id' => $user->id,
            'changed_by_actor_type' => 'user',
            'note' => 'Assigned Church B under Church A.',
        ]);

        $branchCResponse = $this->postJson('/api/branches', [
            'name' => 'Church C',
            'branch_tag_id' => $districtTag['id'],
            'pastor_name' => 'Pst. C',
            'city' => 'Port Harcourt',
            'state' => 'Rivers',
            'created_by_church_id' => $church->id,
            'created_by_user_id' => $user->id,
            'created_by_actor_type' => 'user',
        ]);

        $branchCId = $branchCResponse->json('data.id');

        $secondReassignResponse = $this->postJson('/api/branches/'.$branchAId.'/reassign', [
            'to_parent_branch_id' => $branchCId,
            'changed_by_church_id' => $church->id,
            'changed_by_user_id' => $user->id,
            'changed_by_actor_type' => 'user',
            'note' => 'Moved Church A under Church C.',
        ]);

        $showResponse = $this->getJson('/api/branches/'.$branchAId);
        $listResponse = $this->getJson('/api/branches?church_id='.$church->id);

        $branchAResponse->assertCreated()
            ->assertJsonPath('data.current_parent.type', 'church')
            ->assertJsonPath('data.creator_church.name', 'Church Root');

        $reassignResponse->assertOk()
            ->assertJsonPath('data.current_parent.type', 'branch')
            ->assertJsonPath('data.current_parent.id', $branchAId);

        $secondReassignResponse->assertOk()
            ->assertJsonPath('data.current_parent.id', $branchCId);

        $showResponse->assertOk()
            ->assertJsonPath('data.creator_church.name', 'Church Root')
            ->assertJsonPath('data.current_parent.id', $branchCId)
            ->assertJsonPath('data.assignment_history.0.changed_by_actor_type', 'user');

        $listResponse->assertOk()
            ->assertJsonPath('meta.stats.total_branches', 3);
    }

    public function test_branch_cannot_be_reassigned_under_its_descendant(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $tagId = $this->postJson('/api/branch-tags', [
            'church_id' => $church->id,
            'name' => 'Area',
        ])->json('data.id');

        $branchAId = $this->postJson('/api/branches', [
            'name' => 'Church A',
            'branch_tag_id' => $tagId,
            'created_by_church_id' => $church->id,
            'created_by_user_id' => $user->id,
            'created_by_actor_type' => 'user',
        ])->json('data.id');

        $branchBId = $this->postJson('/api/branches', [
            'name' => 'Church B',
            'branch_tag_id' => $tagId,
            'created_by_church_id' => $church->id,
            'created_by_user_id' => $user->id,
            'created_by_actor_type' => 'user',
        ])->json('data.id');

        $this->postJson('/api/branches/'.$branchBId.'/reassign', [
            'to_parent_branch_id' => $branchAId,
            'changed_by_church_id' => $church->id,
            'changed_by_user_id' => $user->id,
            'changed_by_actor_type' => 'user',
        ])->assertOk();

        $this->postJson('/api/branches/'.$branchAId.'/reassign', [
            'to_parent_branch_id' => $branchBId,
            'changed_by_church_id' => $church->id,
            'changed_by_user_id' => $user->id,
            'changed_by_actor_type' => 'user',
        ])->assertStatus(422);
    }

    public function test_branch_can_have_its_own_local_admin_and_branch_admin_can_log_in(): void
    {
        $church = Church::factory()->create(['name' => 'Church Root']);
        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $tagId = $this->postJson('/api/branch-tags', [
            'church_id' => $church->id,
            'name' => 'District',
        ])->json('data.id');

        $response = $this->postJson('/api/branches', [
            'name' => 'Church A',
            'branch_tag_id' => $tagId,
            'created_by_church_id' => $church->id,
            'created_by_user_id' => $user->id,
            'created_by_actor_type' => 'user',
            'admin' => [
                'name' => 'Branch Admin',
                'email' => 'branch-admin@example.com',
                'phone' => '+2348000000000',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
        ]);

        $branchId = $response->json('data.id');

        $response->assertCreated()
            ->assertJsonPath('data.local_admin.email', 'branch-admin@example.com');

        $this->assertDatabaseHas('users', [
            'branch_id' => $branchId,
            'church_id' => $church->id,
            'role' => 'branch_admin',
            'email' => 'branch-admin@example.com',
        ]);

        $this->postJson('/api/auth/login', [
            'login' => 'branch-admin@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.user.role', 'branch_admin')
            ->assertJsonPath('data.church.id', $church->id)
            ->assertJsonPath('data.branch.id', $branchId)
            ->assertJsonPath('data.branch.name', 'Church A');
    }

    public function test_branch_local_admin_can_be_updated_without_replacing_password(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $tagId = $this->postJson('/api/branch-tags', [
            'church_id' => $church->id,
            'name' => 'Area',
        ])->json('data.id');

        $branchId = $this->postJson('/api/branches', [
            'name' => 'Church B',
            'branch_tag_id' => $tagId,
            'created_by_church_id' => $church->id,
            'created_by_user_id' => $user->id,
            'created_by_actor_type' => 'user',
            'admin' => [
                'name' => 'Initial Admin',
                'email' => 'initial-admin@example.com',
                'phone' => '+2348111111111',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
        ])->json('data.id');

        $this->putJson('/api/branches/'.$branchId, [
            'name' => 'Church B',
            'branch_tag_id' => $tagId,
            'status' => 'active',
            'admin' => [
                'name' => 'Updated Admin',
                'email' => 'updated-admin@example.com',
                'phone' => '+2348222222222',
            ],
        ])->assertOk()
            ->assertJsonPath('data.local_admin.name', 'Updated Admin')
            ->assertJsonPath('data.local_admin.email', 'updated-admin@example.com');

        $this->assertDatabaseHas('users', [
            'branch_id' => $branchId,
            'role' => 'branch_admin',
            'name' => 'Updated Admin',
            'email' => 'updated-admin@example.com',
            'phone' => '+2348222222222',
        ]);

        $this->postJson('/api/auth/login', [
            'login' => 'updated-admin@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.branch.id', $branchId);
    }
}
