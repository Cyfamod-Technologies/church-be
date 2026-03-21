<?php

namespace Tests\Feature\Api;

use App\Models\Church;
use App\Models\ServiceSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_church_registration_creates_church_admin_and_service_schedule_records(): void
    {
        $response = $this->postJson('/api/churches/register', [
            'church' => [
                'name' => 'Living Faith Lekki',
                'address' => '12 Covenant Avenue',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'district_area' => 'Lekki',
                'email' => 'info@lfclekki.test',
                'phone' => '+2348012345678',
            ],
            'pastor' => [
                'name' => 'Pst. Samuel Adeyemi',
                'phone' => '+2348031112233',
                'email' => 'pastor@lfclekki.test',
            ],
            'services' => [
                'sunday_count' => 2,
                'sunday_times' => ['07:00', '09:00'],
                'wednesday_enabled' => true,
                'wednesday_time' => '17:30',
                'wose_enabled' => true,
                'wose_times' => [
                    'wednesday' => '17:30',
                    'thursday' => '17:30',
                    'friday' => '17:30',
                ],
                'special_services_enabled' => true,
            ],
            'settings' => [
                'finance_enabled' => true,
            ],
            'admin' => [
                'name' => 'Church Admin',
                'email' => 'admin@lfclekki.test',
                'phone' => '+2348038769000',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.church.name', 'Living Faith Lekki')
            ->assertJsonPath('data.admin.email', 'admin@lfclekki.test');

        $this->assertDatabaseHas('churches', [
            'name' => 'Living Faith Lekki',
            'finance_enabled' => 1,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@lfclekki.test',
            'role' => 'church_admin',
        ]);

        $this->assertDatabaseCount('service_schedules', 6);
    }

    public function test_login_accepts_email_or_phone(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create([
            'church_id' => $church->id,
            'email' => 'admin@test.com',
            'phone' => '+2348038769000',
            'password' => 'password123',
        ]);

        $byEmail = $this->postJson('/api/auth/login', [
            'login' => $user->email,
            'password' => 'password123',
        ]);

        $byPhone = $this->postJson('/api/auth/login', [
            'login' => $user->phone,
            'password' => 'password123',
        ]);

        $byEmail->assertOk()->assertJsonPath('data.user.email', 'admin@test.com');
        $byPhone->assertOk()->assertJsonPath('data.user.phone', '+2348038769000');
    }

    public function test_homecell_leader_login_returns_homecell_context(): void
    {
        $church = Church::factory()->create(['name' => 'Living Faith Yaba']);
        $homecellResponse = $this->postJson('/api/homecells', [
            'church_id' => $church->id,
            'name' => 'Dominion Cell',
            'meeting_day' => 'Saturday',
            'meeting_time' => '17:30',
            'leaders' => [
                [
                    'name' => 'Leader Faith',
                    'role' => 'Leader',
                    'email' => 'faith.leader@test.com',
                    'phone' => '+2348003334444',
                    'password' => 'leaderpass123',
                    'is_primary' => true,
                ],
            ],
        ]);

        $leaderUser = User::query()->where('email', 'faith.leader@test.com')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'login' => 'faith.leader@test.com',
            'password' => 'leaderpass123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('data.user.id', $leaderUser->id)
            ->assertJsonPath('data.user.role', 'homecell_leader')
            ->assertJsonPath('data.homecell.name', 'Dominion Cell')
            ->assertJsonPath('data.homecell_leader.email', 'faith.leader@test.com')
            ->assertJsonPath('data.church.name', 'Living Faith Yaba');
    }

    public function test_church_setup_can_be_loaded_and_updated(): void
    {
        $church = Church::factory()->create([
            'name' => 'Living Faith Central',
            'state' => 'Lagos',
            'district_area' => 'Ikeja',
            'pastor_name' => 'Pst. Samuel Adeyemi',
            'pastor_phone' => '+2348031112233',
            'pastor_email' => 'pastor@lfc.test',
            'finance_enabled' => false,
        ]);

        $admin = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
            'name' => 'Initial Admin',
            'email' => 'initial-admin@test.com',
            'phone' => '+2348000000001',
        ]);

        ServiceSchedule::create([
            'church_id' => $church->id,
            'service_type' => 'sunday',
            'label' => '1st Service',
            'day_name' => 'Sunday',
            'service_time' => '07:00',
            'sort_order' => 1,
        ]);

        $showResponse = $this->getJson('/api/churches/'.$church->id);

        $showResponse->assertOk()
            ->assertJsonPath('data.name', 'Living Faith Central')
            ->assertJsonPath('data.users.0.email', 'initial-admin@test.com');

        $updateResponse = $this->putJson('/api/churches/'.$church->id, [
            'church' => [
                'name' => 'Living Faith Church Lekki',
                'code' => 'LFC-LEKKI-001',
                'address' => '12 Covenant Avenue',
                'city' => 'Lekki',
                'state' => 'Lagos',
                'district_area' => 'Eti-Osa',
                'email' => 'info@lfclekki.test',
                'phone' => '+2348012345678',
                'status' => 'active',
            ],
            'pastor' => [
                'name' => 'Pst. David Aina',
                'phone' => '+2348031112234',
                'email' => 'david.aina@lfc.test',
            ],
            'services' => [
                'sunday_count' => 2,
                'sunday_times' => ['07:00', '09:00'],
                'wednesday_enabled' => true,
                'wednesday_time' => '17:30',
                'wose_enabled' => true,
                'wose_times' => [
                    'wednesday' => '17:30',
                    'thursday' => '17:30',
                    'friday' => '17:30',
                ],
                'special_services_enabled' => true,
            ],
            'settings' => [
                'finance_enabled' => true,
            ],
            'admin' => [
                'id' => $admin->id,
                'name' => 'Updated Admin',
                'email' => 'updated-admin@test.com',
                'phone' => '+2348000000002',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ],
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.church.name', 'Living Faith Church Lekki')
            ->assertJsonPath('data.admin.email', 'updated-admin@test.com')
            ->assertJsonPath('data.church.finance_enabled', true);

        $this->assertDatabaseHas('churches', [
            'id' => $church->id,
            'name' => 'Living Faith Church Lekki',
            'district_area' => 'Eti-Osa',
            'finance_enabled' => 1,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Updated Admin',
            'email' => 'updated-admin@test.com',
        ]);

        $this->assertDatabaseCount('service_schedules', 6);
    }

    public function test_church_profile_can_be_updated_from_profile_endpoint(): void
    {
        $church = Church::factory()->create([
            'name' => 'Living Faith Central',
            'finance_enabled' => false,
        ]);

        $admin = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
            'email' => 'profile-admin@test.com',
        ]);

        $response = $this->putJson('/api/churches/'.$church->id.'/profile', [
            'church' => [
                'name' => 'Living Faith Church Lekki',
                'code' => 'LFC-LEKKI-002',
                'address' => '15 Dominion Road',
                'city' => 'Lekki',
                'state' => 'Lagos',
                'district_area' => 'Eti-Osa',
                'email' => 'lekki@lfc.test',
                'phone' => '+2348010000000',
                'status' => 'active',
            ],
            'pastor' => [
                'name' => 'Pst. David Aina',
                'phone' => '+2348031112234',
                'email' => 'david.aina@lfc.test',
            ],
            'settings' => [
                'finance_enabled' => true,
            ],
            'admin' => [
                'id' => $admin->id,
                'name' => 'Updated Admin',
                'email' => 'updated-profile-admin@test.com',
                'phone' => '+2348000001111',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.church.name', 'Living Faith Church Lekki')
            ->assertJsonPath('data.admin.email', 'updated-profile-admin@test.com')
            ->assertJsonPath('data.church.finance_enabled', true);

        $this->assertDatabaseHas('churches', [
            'id' => $church->id,
            'name' => 'Living Faith Church Lekki',
            'finance_enabled' => 1,
        ]);
    }

    public function test_service_schedule_can_be_updated_from_service_schedule_endpoint(): void
    {
        $church = Church::factory()->create([
            'special_services_enabled' => false,
        ]);

        ServiceSchedule::create([
            'church_id' => $church->id,
            'service_type' => 'sunday',
            'label' => '1st Service',
            'day_name' => 'Sunday',
            'service_time' => '07:00',
            'sort_order' => 1,
        ]);

        $response = $this->putJson('/api/churches/'.$church->id.'/service-schedules', [
            'services' => [
                'sunday_count' => 3,
                'sunday_times' => ['06:30', '08:30', '10:30'],
                'wednesday_enabled' => true,
                'wednesday_time' => '17:00',
                'wose_enabled' => false,
                'wose_times' => [
                    'wednesday' => null,
                    'thursday' => null,
                    'friday' => null,
                ],
                'custom_services' => [
                    [
                        'label' => 'Night Vigil',
                        'day_name' => 'Friday',
                        'service_time' => '22:00',
                        'recurrence_type' => 'monthly',
                        'recurrence_detail' => 'Last Friday of every month',
                    ],
                    [
                        'label' => 'Thanksgiving Service',
                        'day_name' => 'Sunday',
                        'service_time' => '08:30',
                        'recurrence_type' => 'yearly',
                        'recurrence_detail' => 'December 14',
                    ],
                ],
                'special_services_enabled' => true,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.church.special_services_enabled', true)
            ->assertJsonCount(6, 'data.church.service_schedules');

        $this->assertDatabaseHas('churches', [
            'id' => $church->id,
            'special_services_enabled' => 1,
        ]);

        $this->assertDatabaseHas('service_schedules', [
            'church_id' => $church->id,
            'service_type' => 'special',
            'label' => 'Night Vigil',
            'recurrence_type' => 'monthly',
            'recurrence_detail' => 'Last Friday of every month',
        ]);
    }
}
