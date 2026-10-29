<?php

namespace App\Http\Requests;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $issue = $this->route('issue');

        return $issue instanceof Issue
            && ($this->user()?->can('assign', $issue) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contractor_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('contractor_id')) {
                return;
            }

            $contractor = User::query()
                ->with('role')
                ->find($this->integer('contractor_id'));

            if ($contractor === null || ! $contractor->is_active || $contractor->role?->name !== 'contractor') {
                $validator->errors()->add('contractor_id', 'Select an active Contractor account.');
            }

            $issue = $this->route('issue');
            if ($issue) {
                $activeAssignment = $issue->activeAssignment()->first();
                if ($activeAssignment && (int) $activeAssignment->contractor_id === $this->integer('contractor_id')) {
                    $validator->errors()->add('contractor_id', 'This contractor is already assigned to this issue.');
                }
            }
        }];
    }
}
