<?php

namespace App\Http\Requests;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssignmentRequest extends FormRequest
{
    public const ISSUE_ALREADY_ASSIGNED_MESSAGE = 'This issue has already been assigned. Refresh your browser and check its status again.';

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->is_active
            && $user->role?->name === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'contractor_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'issue_ids' => ['required', 'array', 'min:1'],
            'issue_ids.*' => ['required', 'integer', 'distinct', Rule::exists('issues', 'id')],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $contractor = User::query()
                ->with('role')
                ->find($this->integer('contractor_id'));

            if ($contractor === null || ! $contractor->is_active || $contractor->role?->name !== 'contractor') {
                $validator->errors()->add('contractor_id', 'Select an active Contractor account.');

                return;
            }

            $issueIds = $this->input('issue_ids');
            $issues = Issue::query()
                ->whereIn('id', $issueIds)
                ->get(['id', 'status'])
                ->keyBy('id');
            $alreadyAllocatedIds = Issue::query()
                ->whereIn('id', $issueIds)
                ->whereHas('activeAssignment')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach ($issueIds as $index => $issueId) {
                $issue = $issues->get($issueId);

                if ($issue !== null && in_array((int) $issue->id, $alreadyAllocatedIds, true)) {
                    $validator->errors()->add(
                        "issue_ids.$index",
                        self::ISSUE_ALREADY_ASSIGNED_MESSAGE,
                    );
                } elseif ($issue === null || $issue->status !== 'reported') {
                    $validator->errors()->add(
                        "issue_ids.$index",
                        'Each selected issue must be reported and have no current allocation.',
                    );
                }
            }
        }];
    }
}
