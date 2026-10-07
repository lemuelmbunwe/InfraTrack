<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->is_active
            && $user->super
            && $user->role?->name === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
    * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', 'string', Rule::in(['admin', 'inspector', 'contractor'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'super' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('role') || $validator->errors()->has('super')) {
                return;
            }

            if ($this->boolean('super') && $this->input('role') !== 'admin') {
                $validator->errors()->add('super', 'Only an Admin account can be a super admin.');
            }
        }];
    }
}
