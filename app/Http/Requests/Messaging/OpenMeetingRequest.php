<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OpenMeetingRequest extends FormRequest
{
    /**
     * Participation is checked against the thread in the controller, which is
     * where the conversation is resolved.
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
            /*
             * Absent means "start the call now"; a time means "invite them to
             * one later". Those are the same endpoint because they create the
             * same row — the only difference is whether anybody is in it yet.
             *
             * Capped at ninety days out. A meeting further away than a term is
             * almost always a mistyped year, and the row would sit in the
             * thread until somebody noticed.
             */
            'scheduled_at' => ['nullable', 'date', 'after:now', 'before:'.now()->addDays(90)->toDateTimeString()],
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
            'scheduled_at.after' => 'Pick a time in the future.',
            'scheduled_at.before' => 'Pick a time within the next ninety days.',
        ];
    }
}
