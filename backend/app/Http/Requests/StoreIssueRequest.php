<?php

namespace App\Http\Requests;

use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Issue::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address_text' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'severity' => ['required', 'string', Rule::in(['low', 'medium', 'high'])],
        ];
    }
}
