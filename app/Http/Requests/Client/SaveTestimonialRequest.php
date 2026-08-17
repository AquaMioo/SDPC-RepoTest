<?php

namespace App\Http\Requests\Client;

use App\Models\ClientProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveTestimonialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Speaking for the business is the same permission as editing its profile:
     * whoever may describe the business publicly may also quote it.
     */
    public function authorize(): bool
    {
        $profile = $this->user()?->currentTeam?->clientProfile;

        return $profile instanceof ClientProfile && Gate::allows('update', $profile);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /*
             * Long enough to say something, short enough to sit in a card
             * next to two others without dwarfing them.
             */
            'body' => ['required', 'string', 'min:40', 'max:400'],
            'author_title' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.min' => 'Tell visitors a little more — at least 40 characters.',
            'body.max' => 'Keep it under 400 characters so it fits the card.',
        ];
    }
}
