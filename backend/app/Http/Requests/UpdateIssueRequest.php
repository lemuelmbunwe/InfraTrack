<?php

namespace App\Http\Requests;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $issue = $this->route('issue');

        return $issue instanceof Issue
            && ($this->user()?->can('update', $issue) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();

        if ($user instanceof User && $user->role?->name === 'admin') {
            return [
                'status' => ['sometimes', 'required', 'string', Rule::in(['reported', 'assigned', 'in_progress', 'resolved'])],
                'severity' => ['sometimes', 'required', 'string', Rule::in(['low', 'medium', 'high'])],
                'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            ];
        }

        return [
            'photo' => ['sometimes', 'required', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
            'latitude' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'address_text' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'severity' => ['sometimes', 'required', 'string', Rule::in(['low', 'medium', 'high'])],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();
            $allowedFields = $user instanceof User && $user->role?->name === 'admin'
                ? ['status', 'severity', 'description']
                : ['photo', 'latitude', 'longitude', 'address', 'address_text', 'description', 'severity'];
            $providedFields = array_diff(array_keys($this->all()), ['_method', '_token']);

            foreach (array_diff($providedFields, $allowedFields) as $field) {
                $validator->errors()->add($field, 'This field cannot be changed for this account.');
            }

            if (array_intersect($allowedFields, $providedFields) === []) {
                $validator->errors()->add('issue', 'Provide at least one editable issue field.');
            }
        }];
    }
}
