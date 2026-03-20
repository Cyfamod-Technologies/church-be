<?php

namespace Tests\Feature\Api;

use App\Models\Church;
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
}
