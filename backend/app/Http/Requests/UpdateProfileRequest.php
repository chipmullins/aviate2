<?php

namespace App\Http\Requests;

use App\Rules\AirlineEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:50', 'regex:/^[\pL][\pL\pM\s\'\-.]*$/u'],
            'last_name' => ['required', 'string', 'max:50', 'regex:/^[\pL][\pL\pM\s\'\-.]*$/u'],
            'email' => [
                'required', 'string', 'email:rfc', 'max:255', new AirlineEmail,
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.regex' => 'Names can only contain letters, spaces, hyphens, apostrophes and periods.',
            'last_name.regex' => 'Names can only contain letters, spaces, hyphens, apostrophes and periods.',
            'email.unique' => 'That email is already in use.',
        ];
    }
}
