<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
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
             * Either half makes a message: a picture on its own is worth
             * sending, and so are words on their own. What is not allowed is
             * neither, which is what required_without states on both.
             */
            'body' => ['required_without:image', 'nullable', 'string', 'max:4000'],
            'image' => ['required_without:body', 'nullable', 'image', 'max:'.config('uploads.max_image_kilobytes')],
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
            'body.required_without' => 'Write something, or attach a picture.',
            'image.required_without' => 'Write something, or attach a picture.',
            'image.uploaded' => 'That picture is too large for the server to accept. This machine allows uploads up to '.ini_get('upload_max_filesize').'.',
        ];
    }
}
