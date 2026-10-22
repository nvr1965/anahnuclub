<?php

namespace App\Http\Requests\Settings;

use App\Models\Language;
use App\Models\User;
use App\Support\BannedEmails;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
                BannedEmails::rule(),
            ],

            // The member's own language: the interface opens in it and texts are translated into it.
            'locale' => ['sometimes', 'string', Rule::in(Language::enabled()->pluck('code')->all())],
        ];
    }
}
