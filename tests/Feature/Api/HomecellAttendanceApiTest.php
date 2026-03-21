<?php

namespace Tests\Feature\Api;

use App\Models\Church;
use App\Models\Homecell;
use App\Models\HomecellAttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomecellAttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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
            ->assertJsonPath('data.branch.id', $branchId)
            ->assertJsonPath('data.recorded_by.name', $user->name);

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

    public function test_homecell_attendance_cannot_be_recorded_twice_for_the_same_homecell_on_the_same_day(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $homecell = Homecell::create([
            'church_id' => $church->id,
            'name' => 'Faith Cell',
            'status' => 'active',
        ]);

        $payload = [
            'church_id' => $church->id,
            'homecell_id' => $homecell->id,
            'recorded_by_user_id' => $user->id,
            'meeting_date' => '2026-03-21',
            'male_count' => 10,
            'female_count' => 12,
            'children_count' => 3,
        ];

        $this->postJson('/api/homecell-attendance', $payload)->assertCreated();

        $response = $this->postJson('/api/homecell-attendance', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['meeting_date']);
    }

    public function test_homecell_attendance_record_can_be_retrieved(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $homecell = Homecell::create([
            'church_id' => $church->id,
            'name' => 'Dominion Cell',
            'status' => 'active',
        ]);

        $record = HomecellAttendanceRecord::create([
            'church_id' => $church->id,
            'homecell_id' => $homecell->id,
            'recorded_by_user_id' => $user->id,
            'meeting_date' => '2026-03-21',
            'male_count' => 9,
            'female_count' => 11,
            'children_count' => 4,
            'total_count' => 24,
            'notes' => 'Retrieved record.',
        ]);

        $this->getJson('/api/homecell-attendance/'.$record->id)
            ->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.homecell.id', $homecell->id)
            ->assertJsonPath('data.recorded_by.name', $user->name)
            ->assertJsonPath('data.notes', 'Retrieved record.');
    }

    public function test_homecell_attendance_can_be_updated(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $homecell = Homecell::create([
            'church_id' => $church->id,
            'name' => 'Grace Cell',
            'status' => 'active',
        ]);

        $record = HomecellAttendanceRecord::create([
            'church_id' => $church->id,
            'homecell_id' => $homecell->id,
            'recorded_by_user_id' => $user->id,
            'meeting_date' => '2026-03-21',
            'male_count' => 10,
            'female_count' => 8,
            'children_count' => 2,
            'total_count' => 20,
            'first_timers_count' => 1,
            'new_converts_count' => 0,
            'notes' => 'Original record.',
        ]);

        $response = $this->putJson('/api/homecell-attendance/'.$record->id, [
            'church_id' => $church->id,
            'homecell_id' => $homecell->id,
            'recorded_by_user_id' => $user->id,
            'meeting_date' => '2026-03-21',
            'male_count' => 12,
            'female_count' => 14,
            'children_count' => 4,
            'first_timers_count' => 2,
            'new_converts_count' => 1,
            'notes' => 'Updated record.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total_count', 30)
            ->assertJsonPath('data.notes', 'Updated record.')
            ->assertJsonPath('data.recorded_by.name', $user->name);

        $this->assertDatabaseHas('homecell_attendance_records', [
            'id' => $record->id,
            'male_count' => 12,
            'female_count' => 14,
            'children_count' => 4,
            'total_count' => 30,
            'notes' => 'Updated record.',
        ]);
    }

    public function test_homecell_attendance_can_only_be_added_for_the_next_locked_schedule_date(): void
    {
        Carbon::setTestNow('2026-03-21 10:00:00');

        $church = Church::factory()->create([
            'homecell_schedule_locked' => true,
            'homecell_monthly_dates' => ['2026-03-22', '2026-03-29'],
        ]);

        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $homecell = Homecell::create([
            'church_id' => $church->id,
            'name' => 'Hope Cell',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/homecell-attendance', [
            'church_id' => $church->id,
            'homecell_id' => $homecell->id,
            'recorded_by_user_id' => $user->id,
            'meeting_date' => '2026-03-21',
            'male_count' => 8,
            'female_count' => 10,
            'children_count' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['meeting_date']);

        $this->postJson('/api/homecell-attendance', [
            'church_id' => $church->id,
            'homecell_id' => $homecell->id,
            'recorded_by_user_id' => $user->id,
            'meeting_date' => '2026-03-22',
            'male_count' => 8,
            'female_count' => 10,
            'children_count' => 2,
        ])->assertCreated();
    }

    public function test_existing_homecell_attendance_record_can_still_be_edited_after_the_locked_date_passes(): void
    {
        Carbon::setTestNow('2026-03-30 10:00:00');

        $church = Church::factory()->create([
            'homecell_schedule_locked' => true,
            'homecell_monthly_dates' => ['2026-03-29'],
        ]);

        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $homecell = Homecell::create([
            'church_id' => $church->id,
            'name' => 'River Cell',
            'status' => 'active',
        ]);

        $record = HomecellAttendanceRecord::create([
            'church_id' => $church->id,
            'homecell_id' => $homecell->id,
            'recorded_by_user_id' => $user->id,
            'meeting_date' => '2026-03-22',
            'male_count' => 7,
            'female_count' => 9,
            'children_count' => 1,
            'total_count' => 17,
        ]);

        $this->putJson('/api/homecell-attendance/'.$record->id, [
            'church_id' => $church->id,
            'homecell_id' => $homecell->id,
            'recorded_by_user_id' => $user->id,
            'meeting_date' => '2026-03-22',
            'male_count' => 10,
            'female_count' => 11,
            'children_count' => 2,
        ])->assertOk()
            ->assertJsonPath('data.total_count', 23);
    }
}
