<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\ChurchUnit;
use App\Models\Church;
use App\Models\GuestResponseEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestResponseEntryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_response_entry_can_be_created(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $branch = Branch::create([
            'name' => 'Grace Branch',
            'created_by_church_id' => $church->id,
            'created_by_user_id' => $user->id,
            'created_by_actor_type' => 'user',
            'current_parent_church_id' => $church->id,
        ]);

        $choir = ChurchUnit::create([
            'church_id' => $church->id,
            'name' => 'Choir',
            'status' => 'active',
        ]);

        $ushers = ChurchUnit::create([
            'church_id' => $church->id,
            'name' => 'Ushering',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/guest-response-entries', [
            'church_id' => $church->id,
            'branch_id' => $branch->id,
            'recorded_by_user_id' => $user->id,
            'entry_type' => 'first_timer',
            'full_name' => 'Sarah Johnson',
            'phone' => '+2348001112222',
            'email' => 'sarah@example.com',
            'gender' => 'female',
            'service_date' => '2026-03-21',
            'invited_by' => 'Bro. James',
            'address' => 'Lekki, Lagos',
            'notes' => 'Needs follow-up.',
            'foundation_class_completed' => true,
            'baptism_completed' => false,
            'holy_ghost_baptism_completed' => true,
            'wofbi_completed' => true,
            'wofbi_levels' => ['BCC', 'LCC'],
            'church_unit_ids' => [$choir->id, $ushers->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.entry_type', 'first_timer')
            ->assertJsonPath('data.full_name', 'Sarah Johnson')
            ->assertJsonPath('data.branch.id', $branch->id)
            ->assertJsonPath('data.recorded_by.name', $user->name)
            ->assertJsonPath('data.foundation_class_completed', true)
            ->assertJsonPath('data.holy_ghost_baptism_completed', true)
            ->assertJsonPath('data.wofbi_completed', true)
            ->assertJsonPath('data.wofbi_levels.0', 'BCC')
            ->assertJsonPath('data.wofbi_levels.1', 'LCC')
            ->assertJsonPath('data.church_units.0.name', 'Choir')
            ->assertJsonPath('data.church_units.1.name', 'Ushering');

        $this->assertDatabaseHas('guest_response_entries', [
            'church_id' => $church->id,
            'branch_id' => $branch->id,
            'entry_type' => 'first_timer',
            'full_name' => 'Sarah Johnson',
            'foundation_class_completed' => true,
            'holy_ghost_baptism_completed' => true,
            'wofbi_level' => 'BCC, LCC',
        ]);
    }

    public function test_guest_response_entries_can_be_filtered(): void
    {
        $church = Church::factory()->create();

        GuestResponseEntry::create([
            'church_id' => $church->id,
            'entry_type' => 'first_timer',
            'full_name' => 'Anna Paul',
            'service_date' => '2026-03-20',
        ]);

        GuestResponseEntry::create([
            'church_id' => $church->id,
            'entry_type' => 'new_convert',
            'full_name' => 'Daniel Hope',
            'service_date' => '2026-03-21',
        ]);

        $response = $this->getJson('/api/guest-response-entries?church_id='.$church->id.'&entry_type=new_convert&search=Daniel');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entry_type', 'new_convert')
            ->assertJsonPath('data.0.full_name', 'Daniel Hope');
    }

    public function test_guest_response_entry_can_be_retrieved(): void
    {
        $church = Church::factory()->create();

        $entry = GuestResponseEntry::create([
            'church_id' => $church->id,
            'entry_type' => 'first_timer',
            'full_name' => 'Ruth Daniel',
            'service_date' => '2026-03-21',
        ]);

        $response = $this->getJson('/api/guest-response-entries/'.$entry->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $entry->id)
            ->assertJsonPath('data.full_name', 'Ruth Daniel');
    }

    public function test_guest_response_entry_can_be_updated(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => 'church_admin',
        ]);

        $entry = GuestResponseEntry::create([
            'church_id' => $church->id,
            'recorded_by_user_id' => $user->id,
            'entry_type' => 'rededication',
            'full_name' => 'Peace Samuel',
            'service_date' => '2026-03-21',
        ]);

        $unit = ChurchUnit::create([
            'church_id' => $church->id,
            'name' => 'Protocol',
            'status' => 'active',
        ]);

        $response = $this->putJson('/api/guest-response-entries/'.$entry->id, [
            'church_id' => $church->id,
            'recorded_by_user_id' => $user->id,
            'entry_type' => 'new_convert',
            'full_name' => 'Peace Samuel',
            'phone' => '+2348003334444',
            'service_date' => '2026-03-21',
            'notes' => 'Updated from altar call review.',
            'foundation_class_completed' => true,
            'baptism_completed' => true,
            'holy_ghost_baptism_completed' => true,
            'wofbi_completed' => true,
            'wofbi_levels' => ['BCC', 'LCC', 'LDC'],
            'church_unit_ids' => [$unit->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.entry_type', 'new_convert')
            ->assertJsonPath('data.phone', '+2348003334444')
            ->assertJsonPath('data.notes', 'Updated from altar call review.')
            ->assertJsonPath('data.foundation_class_completed', true)
            ->assertJsonPath('data.baptism_completed', true)
            ->assertJsonPath('data.holy_ghost_baptism_completed', true)
            ->assertJsonPath('data.wofbi_levels.2', 'LDC')
            ->assertJsonPath('data.church_units.0.name', 'Protocol');

        $this->assertDatabaseHas('guest_response_entries', [
            'id' => $entry->id,
            'entry_type' => 'new_convert',
            'phone' => '+2348003334444',
            'baptism_completed' => true,
            'holy_ghost_baptism_completed' => true,
            'wofbi_level' => 'BCC, LCC, LDC',
        ]);
    }
}
