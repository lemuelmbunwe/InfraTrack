<?php

namespace App\Http\Requests;

use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
}
