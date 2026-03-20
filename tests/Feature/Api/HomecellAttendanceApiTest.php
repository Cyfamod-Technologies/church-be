<?php

namespace Tests\Feature\Api;

use App\Models\Church;
use App\Models\Homecell;
use App\Models\HomecellAttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomecellAttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_homecell_attendance_can_be_recorded(): void
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

        $homecell = Homecell::create([
            'church_id' => $church->id,
            'branch_id' => $branchId,
            'name' => 'Dominion Cell',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/homecell-attendance', [
            'church_id' => $church->id,
            'branch_id' => $branchId,
            'homecell_id' => $homecell->id,
            'recorded_by_user_id' => $user->id,
            'meeting_date' => '2026-03-20',
            'male_count' => 12,
            'female_count' => 14,
            'children_count' => 5,
            'first_timers_count' => 2,
            'new_converts_count' => 1,
            'offering_amount' => 15000,
            'notes' => 'Good turnout.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total_count', 31)
            ->assertJsonPath('data.homecell.id', $homecell->id)
            ->assertJsonPath('data.branch.id', $branchId);

        $this->assertDatabaseHas('homecell_attendance_records', [
            'church_id' => $church->id,
            'branch_id' => $branchId,
            'homecell_id' => $homecell->id,
            'total_count' => 31,
        ]);
    }

    public function test_homecell_attendance_summary_can_be_filtered_by_branch(): void
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

        $homecell = Homecell::create([
            'church_id' => $church->id,
            'branch_id' => $branchId,
            'name' => 'Faith Cell',
            'status' => 'active',
        ]);

        HomecellAttendanceRecord::create([
            'church_id' => $church->id,
            'branch_id' => $branchId,
            'homecell_id' => $homecell->id,
            'meeting_date' => '2026-03-17',
            'male_count' => 10,
            'female_count' => 12,
            'children_count' => 3,
            'total_count' => 25,
        ]);

        HomecellAttendanceRecord::create([
            'church_id' => $church->id,
            'branch_id' => $branchId,
            'homecell_id' => $homecell->id,
            'meeting_date' => '2026-03-19',
            'male_count' => 14,
            'female_count' => 16,
            'children_count' => 5,
            'total_count' => 35,
        ]);

        $summaryResponse = $this->getJson('/api/homecell-attendance/summary?church_id='.$church->id.'&branch_id='.$branchId.'&period=weekly&date=2026-03-20');
        $listResponse = $this->getJson('/api/homecell-attendance?church_id='.$church->id.'&branch_id='.$branchId);

        $summaryResponse->assertOk()
            ->assertJsonPath('data.total_attendance', 60)
            ->assertJsonPath('data.reports_submitted', 2)
            ->assertJsonPath('data.homecells_covered', 1)
            ->assertJsonPath('data.pending_homecells', 0);

        $listResponse->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.branch.id', $branchId);
    }
}
