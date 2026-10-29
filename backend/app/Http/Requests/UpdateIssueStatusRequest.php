<?php

namespace App\Http\Requests;

use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateIssueStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $issue = $this->route('issue');

        return $issue instanceof Issue
            && ($this->user()?->can('updateStatus', $issue) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['reported', 'assigned', 'in_progress', 'resolved'])],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $issue = $this->route('issue');

            if (
                $issue instanceof Issue
                && $this->input('status') === 'reported'
                && $issue->status !== 'reported'
                && $issue->assignments()->exists()
            ) {
                $validator->errors()->add('status', 'An assigned issue cannot be returned to reported status.');
            }
        }];
    }
}
