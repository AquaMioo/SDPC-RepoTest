<?php

namespace App\Http\Requests\Messaging;

use App\Models\MessageReaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactToMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Participation is checked against the thread in the controller.
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
            // A closed set. Anything goes here and the column becomes a place
            // to put arbitrary text next to someone else's message.
            'emoji' => ['required', 'string', Rule::in(MessageReaction::ALLOWED)],
        ];
    }
}
