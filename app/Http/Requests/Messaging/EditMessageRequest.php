<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EditMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Ownership is checked against the resolved message in the controller.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // An edit cannot empty a message. Removing is the way to do that,
            // and it says so in the thread rather than leaving a blank bubble.
            'body' => ['required', 'string', 'max:4000'],
        ];
    }
}
