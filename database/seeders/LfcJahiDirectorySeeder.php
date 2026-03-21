<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchAssignmentHistory;
use App\Models\BranchTag;
use App\Models\Church;
use App\Models\Homecell;
use App\Models\HomecellLeader;
use App\Models\ServiceSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LfcJahiDirectorySeeder extends Seeder
{
    /**
     * @var array<string, bool>
     */
    private array $reservedPhones = [];

    public function run(): void
    {
        $payload = json_decode(
            file_get_contents(database_path('seeders/data/lfcjahi-directory.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $churchData = $payload['church'] ?? [];
        $pastorData = $churchData['pastorInCharge'] ?? [];
        $adminData = $payload['primaryAdmin'] ?? [];
        $settings = $payload['settings'] ?? [];
        $services = $payload['services'] ?? [];
        $districts = $payload['districts'] ?? [];
        $defaultAdminPassword = (string) data_get($payload, 'defaults.adminPassword', '12345678');
        $defaultLeaderPassword = (string) data_get($payload, 'defaults.homecellLeaderPassword', '12345678');
        $defaultHomecellScheduleLocked = (bool) data_get($payload, 'defaults.homecellScheduleLocked', false);
        $defaultHomecellMeetingDay = (string) data_get($payload, 'defaults.homecellMeetingDay', 'Saturday');
        $defaultHomecellMeetingTime = (string) data_get($payload, 'defaults.homecellMeetingTime', '17:00');
        $defaultHomecellMonthlyDates = array_values(array_unique((array) data_get($payload, 'defaults.homecellMonthlyDates', [])));

        BranchTag::ensureDefaults();

        $createdAt = Carbon::createFromFormat('d/m/Y', (string) ($churchData['created'] ?? now()->format('d/m/Y')))
            ->startOfDay();

        $church = $this->upsertChurch(
            $churchData,
            $pastorData,
            $settings,
            [
                'locked' => $defaultHomecellScheduleLocked,
                'default_day' => $defaultHomecellMeetingDay,
                'default_time' => $defaultHomecellMeetingTime,
                'monthly_dates' => $defaultHomecellMonthlyDates,
            ],
            $createdAt
        );
        $admin = $this->upsertAdmin($church, $adminData, $defaultAdminPassword, $createdAt);
        $this->syncServiceSchedules($church, $services, $createdAt);

        $this->reservedPhones = User::query()
            ->whereNotNull('phone')
            ->pluck('phone')
            ->filter(fn (?string $phone) => filled($phone))
            ->mapWithKeys(fn (string $phone) => [$phone => true])
            ->all();

        if ($admin->phone) {
            $this->reservedPhones[$admin->phone] = true;
        }

        $districtTag = BranchTag::query()->whereNull('church_id')->where('slug', 'district')->firstOrFail();
        $zoneTag = BranchTag::query()->whereNull('church_id')->where('slug', 'zone')->firstOrFail();
        $leaderEmailDomain = $this->emailDomain((string) ($church->email ?: $admin->email ?: 'lfcjahi.com'));

        foreach (array_values($districts) as $districtIndex => $district) {
            $districtSort = (int) ($district['sortOrder'] ?? ($districtIndex + 1));
            $districtPastor = $this->parsePerson(
                $district['outreachPastor'] ?? $district['homeCellPastors'][0] ?? null
            );

            $districtBranch = $this->upsertBranch(
                code: sprintf('%s-D%02d', $church->code, $districtSort),
                tag: $districtTag,
                church: $church,
                admin: $admin,
                name: (string) ($district['name'] ?? 'District '.$districtSort),
                address: $district['outreachLocation'] ?? $church->address,
                pastor: $districtPastor,
                parentChurchId: $church->id,
                parentBranchId: null,
                createdAt: $createdAt
            );

            foreach (array_values($district['zones'] ?? []) as $zoneIndex => $zone) {
                $zoneSort = (int) ($zone['sortOrder'] ?? ($zoneIndex + 1));
                $zoneMinister = $this->parsePerson($zone['zoneMinister'] ?? null);

                $zoneBranch = $this->upsertBranch(
                    code: sprintf('%s-Z%02d%02d', $church->code, $districtSort, $zoneSort),
                    tag: $zoneTag,
                    church: $church,
                    admin: $admin,
                    name: (string) ($zone['name'] ?? 'Zone '.$zoneSort),
                    address: $district['coverageAreas'] ?? $districtBranch->address,
                    pastor: $zoneMinister,
                    parentChurchId: null,
                    parentBranchId: $districtBranch->id,
                    createdAt: $createdAt
                );

                foreach (array_values($zone['cells'] ?? []) as $cellIndex => $cell) {
                    $cellSort = (int) ($cell['sortOrder'] ?? ($cellIndex + 1));
                    $leader = $this->parsePerson(($cell['minister'] ?? null), $cell['phone'] ?? null);

                    $homecell = $this->upsertHomecell(
                        church: $church,
                        branch: $zoneBranch,
                        code: sprintf('%s-H%02d%02d%02d', $church->code, $districtSort, $zoneSort, $cellSort),
                        name: (string) ($cell['name'] ?? 'Homecell '.$cellSort),
                        meetingDay: $defaultHomecellMeetingDay,
                        meetingTime: $defaultHomecellMeetingTime,
                        cityArea: $district['coverageAreas'] ?? $church->city,
                        address: $cell['address'] ?? $districtBranch->address,
                        hostName: $leader['name'],
                        hostPhone: $leader['phone'],
                        notes: sprintf(
                            'Seeded from %s / %s directory.',
                            (string) ($district['name'] ?? 'District'),
                            (string) ($zone['name'] ?? 'Zone')
                        ),
                        createdAt: $createdAt
                    );

                    $leaderEmail = sprintf(
                        'homecell.%s@%s',
                        Str::lower(Str::slug($homecell->code, '.')),
                        $leaderEmailDomain
                    );

                    $leaderUser = $this->upsertLeaderUser(
                        church: $church,
                        branch: $zoneBranch,
                        email: $leaderEmail,
                        name: $leader['name'] ?: $homecell->name.' Leader',
                        phone: $leader['phone'],
                        password: $defaultLeaderPassword,
                        createdAt: $createdAt
                    );

                    HomecellLeader::query()->updateOrCreate(
                        [
                            'homecell_id' => $homecell->id,
                            'sort_order' => 1,
                        ],
                        [
                            'user_id' => $leaderUser->id,
                            'name' => $leader['name'] ?: $leaderUser->name,
                            'role' => 'Leader',
                            'phone' => $leader['phone'],
                            'email' => $leaderEmail,
                            'is_primary' => true,
                        ]
                    );
                }
            }
        }
    }

    private function upsertChurch(array $churchData, array $pastorData, array $settings, array $homecellSchedule, Carbon $createdAt): Church
    {
        $church = Church::query()->firstOrNew([
            'code' => (string) ($churchData['code'] ?? 'LFCJAHI-AERIPO'),
        ]);

        $church->fill([
            'name' => $churchData['name'] ?? 'LFC Jahi',
            'code' => $churchData['code'] ?? 'LFCJAHI-AERIPO',
            'address' => $churchData['address'] ?? null,
            'city' => $churchData['city'] ?? null,
            'state' => $churchData['state'] ?? null,
            'district_area' => $churchData['districtArea'] ?? null,
            'email' => $churchData['email'] ?? null,
            'phone' => $churchData['phone'] ?? null,
            'pastor_name' => $pastorData['fullName'] ?? null,
            'pastor_phone' => $pastorData['phone'] ?? null,
            'pastor_email' => $pastorData['email'] ?? null,
            'finance_enabled' => (bool) ($settings['financeTracking'] ?? false),
            'special_services_enabled' => (bool) ($settings['specialServices'] ?? false),
            'homecell_schedule_locked' => (bool) ($homecellSchedule['locked'] ?? false),
            'homecell_default_day' => $homecellSchedule['default_day'] ?? null,
            'homecell_default_time' => $homecellSchedule['default_time'] ?? null,
            'homecell_monthly_dates' => $homecellSchedule['monthly_dates'] ?? [],
            'status' => $churchData['status'] ?? 'active',
        ]);

        $church->timestamps = false;
        $church->created_at = $church->exists ? $church->created_at : $createdAt;
        $church->updated_at = $createdAt;
        $church->save();
        $church->timestamps = true;

        return $church->fresh();
    }

    private function upsertAdmin(Church $church, array $adminData, string $defaultPassword, Carbon $createdAt): User
    {
        $email = (string) ($adminData['email'] ?? 'admin@lfcjahi.com');

        $admin = User::query()->firstOrNew([
            'email' => $email,
        ]);

        $admin->fill([
            'church_id' => $church->id,
            'name' => $adminData['fullName'] ?? 'Primary Admin',
            'phone' => $adminData['phone'] ?? null,
            'role' => 'admin',
        ]);
        $admin->password = $defaultPassword;
        $admin->timestamps = false;
        $admin->created_at = $admin->exists ? $admin->created_at : $createdAt;
        $admin->updated_at = $createdAt;
        $admin->save();
        $admin->timestamps = true;

        return $admin->fresh();
    }

    private function upsertBranch(
        string $code,
        BranchTag $tag,
        Church $church,
        User $admin,
        string $name,
        ?string $address,
        array $pastor,
        ?int $parentChurchId,
        ?int $parentBranchId,
        Carbon $createdAt,
    ): Branch {
        $branch = Branch::query()->firstOrNew([
            'code' => $code,
        ]);

        $branch->fill([
            'name' => $name,
            'code' => $code,
            'branch_tag_id' => $tag->id,
            'pastor_name' => $pastor['name'],
            'pastor_phone' => $pastor['phone'],
            'address' => $address,
            'city' => $church->city,
            'state' => $church->state,
            'district_area' => $church->district_area,
            'status' => 'active',
            'created_by_church_id' => $church->id,
            'created_by_user_id' => $admin->id,
            'created_by_actor_type' => 'admin',
            'current_parent_church_id' => $parentChurchId,
            'current_parent_branch_id' => $parentBranchId,
            'last_assigned_by_church_id' => $church->id,
            'last_assigned_by_user_id' => $admin->id,
            'last_assigned_actor_type' => 'admin',
        ]);

        $branch->timestamps = false;
        $branch->created_at = $branch->exists ? $branch->created_at : $createdAt;
        $branch->updated_at = $createdAt;
        $branch->save();
        $branch->timestamps = true;

        BranchAssignmentHistory::query()->firstOrCreate(
            [
                'branch_id' => $branch->id,
                'action_type' => 'assigned',
                'to_parent_church_id' => $parentChurchId,
                'to_parent_branch_id' => $parentBranchId,
            ],
            [
                'changed_by_church_id' => $church->id,
                'changed_by_user_id' => $admin->id,
                'changed_by_actor_type' => 'admin',
                'note' => 'Seeded from LFC Jahi directory.',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]
        );

        return $branch->fresh();
    }

    private function upsertHomecell(
        Church $church,
        Branch $branch,
        string $code,
        string $name,
        string $meetingDay,
        string $meetingTime,
        ?string $cityArea,
        ?string $address,
        ?string $hostName,
        ?string $hostPhone,
        ?string $notes,
        Carbon $createdAt,
    ): Homecell {
        $homecell = Homecell::query()->firstOrNew([
            'code' => $code,
        ]);

        $homecell->fill([
            'church_id' => $church->id,
            'branch_id' => $branch->id,
            'name' => $name,
            'code' => $code,
            'meeting_day' => $meetingDay,
            'meeting_time' => $meetingTime,
            'host_name' => $hostName,
            'host_phone' => $hostPhone,
            'city_area' => $cityArea,
            'address' => $address,
            'notes' => $notes,
            'status' => 'active',
        ]);

        $homecell->timestamps = false;
        $homecell->created_at = $homecell->exists ? $homecell->created_at : $createdAt;
        $homecell->updated_at = $createdAt;
        $homecell->save();
        $homecell->timestamps = true;

        return $homecell->fresh();
    }

    private function upsertLeaderUser(
        Church $church,
        Branch $branch,
        string $email,
        string $name,
        ?string $phone,
        string $password,
        Carbon $createdAt,
    ): User {
        $user = User::query()->firstOrNew([
            'email' => $email,
        ]);

        $resolvedPhone = $this->reservePhone($user, $phone);

        $user->fill([
            'church_id' => $church->id,
            'branch_id' => $branch->id,
            'name' => $name,
            'phone' => $resolvedPhone,
            'role' => 'homecell_leader',
        ]);
        $user->password = $password;
        $user->timestamps = false;
        $user->created_at = $user->exists ? $user->created_at : $createdAt;
        $user->updated_at = $createdAt;
        $user->save();
        $user->timestamps = true;

        return $user->fresh();
    }

    private function syncServiceSchedules(Church $church, array $services, Carbon $createdAt): void
    {
        ServiceSchedule::query()->where('church_id', $church->id)->delete();

        foreach (array_values($services['sundayTimes'] ?? []) as $index => $time) {
            if ($index >= (int) ($services['sundayCount'] ?? 0)) {
                break;
            }

            ServiceSchedule::query()->create([
                'church_id' => $church->id,
                'service_type' => 'sunday',
                'label' => ($index + 1).$this->ordinalSuffix($index + 1).' Service',
                'day_name' => 'Sunday',
                'service_time' => $time,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        if ($services['wednesdayEnabled'] ?? false) {
            ServiceSchedule::query()->create([
                'church_id' => $church->id,
                'service_type' => 'wednesday',
                'label' => 'Wednesday Service',
                'day_name' => 'Wednesday',
                'service_time' => $services['wednesdayTime'] ?? null,
                'sort_order' => 50,
                'is_active' => true,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        if ($services['woseEnabled'] ?? false) {
            $woseDays = [
                'wednesday' => ['label' => 'WOSE Wednesday', 'sort_order' => 60],
                'thursday' => ['label' => 'WOSE Thursday', 'sort_order' => 61],
                'friday' => ['label' => 'WOSE Friday', 'sort_order' => 62],
            ];

            foreach ($woseDays as $day => $meta) {
                ServiceSchedule::query()->create([
                    'church_id' => $church->id,
                    'service_type' => 'wose',
                    'label' => $meta['label'],
                    'day_name' => Str::title($day),
                    'service_time' => data_get($services, "woseTimes.{$day}"),
                    'sort_order' => $meta['sort_order'],
                    'is_active' => true,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }

        foreach (array_values($services['customServices'] ?? []) as $index => $service) {
            ServiceSchedule::query()->create([
                'church_id' => $church->id,
                'service_type' => 'special',
                'label' => $service['label'],
                'day_name' => $service['dayName'] ?? null,
                'service_time' => $service['serviceTime'],
                'recurrence_type' => $service['recurrenceType'],
                'recurrence_detail' => $service['recurrenceDetail'] ?? null,
                'sort_order' => 100 + $index,
                'is_active' => true,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    /**
     * @return array{name:?string, phone:?string}
     */
    private function parsePerson(mixed $value, mixed $fallbackPhone = null): array
    {
        $name = null;
        $phone = $this->sanitizePhone($fallbackPhone);

        if (is_string($value) && preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/', trim($value), $matches)) {
            $name = $this->sanitizeName($matches[1]);
            $phone = $phone ?: $this->sanitizePhone($matches[2]);
        } else {
            $name = $this->sanitizeName(is_string($value) ? $value : null);
        }

        return [
            'name' => $name,
            'phone' => $phone,
        ];
    }

    private function sanitizeName(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || in_array(Str::lower($value), ['tbd', '—', '-'], true)) {
            return null;
        }

        return $value;
    }

    private function sanitizePhone(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || in_array($value, ['—', '-'], true)) {
            return null;
        }

        return $value;
    }

    private function ordinalSuffix(int $value): string
    {
        return match ($value) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    private function emailDomain(string $email): string
    {
        $parts = explode('@', $email);

        return $parts[1] ?? 'lfcjahi.com';
    }

    private function reservePhone(User $user, ?string $phone): ?string
    {
        $phone = $this->sanitizePhone($phone);

        if (! $phone) {
            return null;
        }

        if ($user->exists && $user->phone === $phone) {
            $this->reservedPhones[$phone] = true;

            return $phone;
        }

        $alreadyUsedByAnotherUser = User::query()
            ->where('phone', $phone)
            ->when($user->exists, fn ($query) => $query->where('id', '!=', $user->id))
            ->exists();

        if ($alreadyUsedByAnotherUser || isset($this->reservedPhones[$phone])) {
            return null;
        }

        $this->reservedPhones[$phone] = true;

        return $phone;
    }
}
