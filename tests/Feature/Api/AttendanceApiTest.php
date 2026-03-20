<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\ServiceSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_endpoint_persists_counts_and_finance_fields(): void
    {
        $church = Church::factory()->create(['finance_enabled' => true]);
        $user = User::factory()->create(['church_id' => $church->id]);
        $schedule = ServiceSchedule::create([
            'church_id' => $church->id,
            'service_type' => 'sunday',
            'label' => '1st Service',
            'day_name' => 'Sunday',
            'service_time' => '07:00',
            'sort_order' => 1,
        ]);

        $response = $this->postJson('/api/attendance', [
            'church_id' => $church->id,
            'service_schedule_id' => $schedule->id,
            'recorded_by_user_id' => $user->id,
            'service_date' => '2026-03-15',
            'service_type' => 'sunday',
            'sunday_service_number' => 1,
            'male_count' => 120,
            'female_count' => 150,
            'children_count' => 30,
            'first_timers_count' => 12,
            'new_converts_count' => 4,
            'rededications_count' => 2,
            'main_offering' => 50000,
            'tithe' => 20000,
            'special_offering' => 10000,
            'notes' => 'Strong second service turnout.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total_count', 300)
            ->assertJsonPath('data.first_timers_count', 12);

        $this->assertDatabaseHas('attendance_records', [
            'church_id' => $church->id,
            'service_type' => 'sunday',
            'total_count' => 300,
        ]);
    }

    public function test_attendance_summary_returns_totals_averages_and_highest_service(): void
    {
        $church = Church::factory()->create();

        AttendanceRecord::create([
            'church_id' => $church->id,
            'service_date' => '2026-03-16',
            'service_type' => 'sunday',
            'service_label' => '1st Service',
            'male_count' => 100,
            'female_count' => 120,
            'children_count' => 30,
            'total_count' => 250,
        ]);

        AttendanceRecord::create([
            'church_id' => $church->id,
            'service_date' => '2026-03-18',
            'service_type' => 'wednesday',
            'service_label' => 'Wednesday Service',
            'male_count' => 80,
            'female_count' => 100,
            'children_count' => 20,
            'total_count' => 200,
        ]);

        $response = $this->getJson('/api/attendance/summary?church_id='.$church->id.'&period=weekly&date=2026-03-18');

        $response->assertOk()
            ->assertJsonPath('data.total_attendance', 450)
            ->assertJsonPath('data.average_attendance', 225)
            ->assertJsonPath('data.highest_service.service_label', '1st Service');
    }

    public function test_attendance_can_be_linked_to_a_branch_and_summary_can_filter_by_branch(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $branchTagId = $this->postJson('/api/branch-tags', [
            'church_id' => $church->id,
            'name' => 'District',
        ])->json('data.id');
        $branchId = $this->postJson('/api/branches', [
            'name' => 'Attendance Branch',
            'branch_tag_id' => $branchTagId,
            'created_by_church_id' => $church->id,
            'created_by_user_id' => $user->id,
            'created_by_actor_type' => 'user',
        ])->json('data.id');

        $response = $this->postJson('/api/attendance', [
            'church_id' => $church->id,
            'branch_id' => $branchId,
            'recorded_by_user_id' => $user->id,
            'service_date' => '2026-03-20',
            'service_type' => 'special',
            'service_label' => 'Leadership Congress',
            'special_service_name' => 'Leadership Congress',
            'male_count' => 40,
            'female_count' => 55,
            'children_count' => 5,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.branch.id', $branchId)
            ->assertJsonPath('data.total_count', 100);

        $summaryResponse = $this->getJson('/api/attendance/summary?church_id='.$church->id.'&branch_id='.$branchId.'&period=weekly&date=2026-03-20');
        $listResponse = $this->getJson('/api/attendance?church_id='.$church->id.'&branch_id='.$branchId);

        $summaryResponse->assertOk()
            ->assertJsonPath('data.total_attendance', 100);

        $listResponse->assertOk()
            ->assertJsonPath('data.0.branch.id', $branchId);
    }
}
