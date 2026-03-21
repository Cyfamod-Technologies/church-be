<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateHomecellScheduleRequest;
use App\Http\Requests\Api\UpdateChurchProfileRequest;
use App\Http\Requests\Api\UpdateServiceScheduleRequest;
use App\Http\Requests\Api\UpdateChurchSetupRequest;
use App\Models\Church;
use App\Models\ServiceSchedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChurchController extends Controller
{
    public function show(Church $church): JsonResponse
    {
        return response()->json([
            'data' => $church->load(['users', 'serviceSchedules']),
        ]);
    }

    public function serviceSchedules(Church $church): JsonResponse
    {
        return response()->json([
            'data' => $church->serviceSchedules()->get(),
        ]);
    }

    public function updateProfile(UpdateChurchProfileRequest $request, Church $church): JsonResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated, $church): array {
            $churchData = $validated['church'];
            $pastorData = $validated['pastor'];
            $settings = $validated['settings'];
            $adminData = $validated['admin'];

            $church->update([
                'name' => $churchData['name'],
                'code' => ($churchData['code'] ?? null) ?: $church->code ?: $this->generateChurchCode($churchData['name']),
                'address' => $churchData['address'] ?? null,
                'city' => $churchData['city'] ?? null,
                'state' => $churchData['state'] ?? null,
                'district_area' => $churchData['district_area'] ?? null,
                'email' => $churchData['email'] ?? null,
                'phone' => $churchData['phone'] ?? null,
                'status' => $churchData['status'] ?? $church->status,
                'pastor_name' => $pastorData['name'],
                'pastor_phone' => $pastorData['phone'],
                'pastor_email' => $pastorData['email'] ?? null,
                'finance_enabled' => (bool) $settings['finance_enabled'],
            ]);

            $admin = $this->resolveAdminUser($church, $adminData);
            $admin->fill([
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'phone' => $adminData['phone'],
                'role' => $admin->role ?: 'church_admin',
            ]);

            if (!empty($adminData['password'])) {
                $admin->password = $adminData['password'];
            }

            $admin->save();

            return [
                'church' => $church->fresh()->load(['users', 'serviceSchedules']),
                'admin' => $admin->fresh(),
            ];
        });

        return response()->json([
            'message' => 'Church profile updated successfully.',
            'data' => $result,
        ]);
    }

    public function updateServiceSchedules(UpdateServiceScheduleRequest $request, Church $church): JsonResponse
    {
        $serviceData = $request->validated()['services'];

        $result = DB::transaction(function () use ($serviceData, $church): array {
            $church->update([
                'special_services_enabled' => (bool) $serviceData['special_services_enabled'],
            ]);

            $church->serviceSchedules()->delete();
            $this->syncServiceSchedules($church, $serviceData);

            return [
                'church' => $church->fresh()->load(['users', 'serviceSchedules']),
            ];
        });

        return response()->json([
            'message' => 'Service schedule updated successfully.',
            'data' => $result,
        ]);
    }

    public function updateHomecellSchedule(UpdateHomecellScheduleRequest $request, Church $church): JsonResponse
    {
        $schedule = $request->validated()['homecell_schedule'];

        $church = DB::transaction(function () use ($church, $schedule): Church {
            $church->update([
                'homecell_schedule_locked' => (bool) $schedule['locked'],
                'homecell_default_day' => $schedule['default_day'] ?? null,
                'homecell_default_time' => $schedule['default_time'] ?? null,
                'homecell_monthly_dates' => array_values(array_unique($schedule['monthly_dates'] ?? [])),
            ]);

            if ($church->homecell_schedule_locked && $church->homecell_default_day && $church->homecell_default_time) {
                $church->homecells()->update([
                    'meeting_day' => $church->homecell_default_day,
                    'meeting_time' => $church->homecell_default_time,
                ]);
            }

            return $church->fresh()->load(['users', 'serviceSchedules']);
        });

        return response()->json([
            'message' => 'Homecell schedule updated successfully.',
            'data' => [
                'church' => $church,
            ],
        ]);
    }

    public function update(UpdateChurchSetupRequest $request, Church $church): JsonResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated, $church): array {
            $churchData = $validated['church'];
            $pastorData = $validated['pastor'];
            $serviceData = $validated['services'];
            $settings = $validated['settings'];
            $adminData = $validated['admin'];

            $church->update([
                'name' => $churchData['name'],
                'code' => ($churchData['code'] ?? null) ?: $church->code ?: $this->generateChurchCode($churchData['name']),
                'address' => $churchData['address'] ?? null,
                'city' => $churchData['city'] ?? null,
                'state' => $churchData['state'] ?? null,
                'district_area' => $churchData['district_area'] ?? null,
                'email' => $churchData['email'] ?? null,
                'phone' => $churchData['phone'] ?? null,
                'status' => $churchData['status'] ?? $church->status,
                'pastor_name' => $pastorData['name'],
                'pastor_phone' => $pastorData['phone'],
                'pastor_email' => $pastorData['email'] ?? null,
                'finance_enabled' => (bool) $settings['finance_enabled'],
                'special_services_enabled' => (bool) $serviceData['special_services_enabled'],
            ]);

            $admin = $this->resolveAdminUser($church, $adminData);
            $admin->fill([
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'phone' => $adminData['phone'],
                'role' => $admin->role ?: 'church_admin',
            ]);

            if (!empty($adminData['password'])) {
                $admin->password = $adminData['password'];
            }

            $admin->save();

            $church->serviceSchedules()->delete();
            $this->syncServiceSchedules($church, $serviceData);

            return [
                'church' => $church->fresh()->load(['users', 'serviceSchedules']),
                'admin' => $admin->fresh(),
            ];
        });

        return response()->json([
            'message' => 'Church setup updated successfully.',
            'data' => $result,
        ]);
    }

    private function resolveAdminUser(Church $church, array $adminData): User
    {
        if (!empty($adminData['id'])) {
            return $church->users()->findOrFail($adminData['id']);
        }

        return $church->users()
            ->where('role', 'church_admin')
            ->first() ?? $church->users()->firstOrFail();
    }

    private function syncServiceSchedules(Church $church, array $serviceData): void
    {
        foreach (array_values($serviceData['sunday_times']) as $index => $time) {
            if ($index >= (int) $serviceData['sunday_count']) {
                break;
            }

            ServiceSchedule::create([
                'church_id' => $church->id,
                'service_type' => 'sunday',
                'label' => ($index + 1).$this->ordinalSuffix($index + 1).' Service',
                'day_name' => 'Sunday',
                'service_time' => $time,
                'sort_order' => $index + 1,
            ]);
        }

        if ($serviceData['wednesday_enabled']) {
            ServiceSchedule::create([
                'church_id' => $church->id,
                'service_type' => 'wednesday',
                'label' => 'Wednesday Service',
                'day_name' => 'Wednesday',
                'service_time' => $serviceData['wednesday_time'],
                'sort_order' => 50,
            ]);
        }

        if ($serviceData['wose_enabled']) {
            $days = [
                'wednesday' => ['label' => 'WOSE Wednesday', 'sort_order' => 60],
                'thursday' => ['label' => 'WOSE Thursday', 'sort_order' => 61],
                'friday' => ['label' => 'WOSE Friday', 'sort_order' => 62],
            ];

            foreach ($days as $day => $meta) {
                ServiceSchedule::create([
                    'church_id' => $church->id,
                    'service_type' => 'wose',
                    'label' => $meta['label'],
                    'day_name' => Str::title($day),
                    'service_time' => $serviceData['wose_times'][$day],
                    'sort_order' => $meta['sort_order'],
                ]);
            }
        }

        foreach (array_values($serviceData['custom_services'] ?? []) as $index => $service) {
            ServiceSchedule::create([
                'church_id' => $church->id,
                'service_type' => 'special',
                'label' => $service['label'],
                'day_name' => $service['day_name'] ?? null,
                'service_time' => $service['service_time'],
                'recurrence_type' => $service['recurrence_type'],
                'recurrence_detail' => $service['recurrence_detail'] ?? null,
                'sort_order' => 100 + $index,
            ]);
        }
    }

    private function generateChurchCode(string $churchName): string
    {
        $prefix = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $churchName), 0, 8));

        return trim($prefix ?: 'LFC').'-'.Str::upper(Str::random(6));
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
}
