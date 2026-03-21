<?php

namespace App\Http\Requests\Api;

use App\Models\Branch;
use App\Models\Church;
use App\Models\Homecell;
use App\Models\HomecellAttendanceRecord;
use App\Support\HomecellScheduleGate;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHomecellAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'church_id' => ['required', 'integer', Rule::exists(Church::class, 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')],
            'homecell_id' => ['required', 'integer', Rule::exists(Homecell::class, 'id')],
            'recorded_by_user_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'meeting_date' => ['required', 'date'],
            'male_count' => ['required', 'integer', 'min:0'],
            'female_count' => ['required', 'integer', 'min:0'],
            'children_count' => ['required', 'integer', 'min:0'],
            'first_timers_count' => ['nullable', 'integer', 'min:0'],
            'new_converts_count' => ['nullable', 'integer', 'min:0'],
            'offering_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $churchId = $this->integer('church_id');
            $homecellId = $this->integer('homecell_id');
            $branchId = $this->integer('branch_id');
            $recordedByUserId = $this->integer('recorded_by_user_id');

            if (! $churchId || ! $homecellId) {
                return;
            }

            $homecell = Homecell::query()->with(['branch:id', 'church:id,homecell_schedule_locked,homecell_monthly_dates'])->find($homecellId);

            if (! $homecell || $homecell->church_id !== $churchId) {
                $validator->errors()->add('homecell_id', 'Select a homecell that belongs to this church.');
                return;
            }

            if ($branchId && (int) $homecell->branch_id !== $branchId) {
                $validator->errors()->add('branch_id', 'Select the branch currently assigned to this homecell.');
            }

            if ($recordedByUserId) {
                $userBelongsToChurch = User::query()
                    ->whereKey($recordedByUserId)
                    ->where('church_id', $churchId)
                    ->exists();

                if (! $userBelongsToChurch) {
                    $validator->errors()->add('recorded_by_user_id', 'Select a user that belongs to this church.');
                }
            }

            if ($this->filled('meeting_date') && HomecellAttendanceRecord::query()
                ->where('church_id', $churchId)
                ->where('homecell_id', $homecellId)
                ->whereDate('meeting_date', $this->input('meeting_date'))
                ->exists()) {
                $validator->errors()->add('meeting_date', 'This homecell already has an attendance record for the selected date.');
            }

            if ($this->filled('meeting_date') && $homecell->church) {
                $scheduleMessage = HomecellScheduleGate::validationMessage($homecell->church, $this->input('meeting_date'));

                if ($scheduleMessage) {
                    $validator->errors()->add('meeting_date', $scheduleMessage);
                }
            }
        });
    }
}
