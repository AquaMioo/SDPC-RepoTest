<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use App\Models\AgreementTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class SubmitTaskRequest extends FormRequest
{
    /**
     * The largest proof file accepted, in kilobytes.
     */
    public const MAX_FILE_KILOBYTES = 10240;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Checking a task off is the signing student's move. It does not complete
     * the task — it hands it to the client for review.
     */
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');

        return $agreement instanceof Agreement && Gate::allows('manageTasks', $agreement);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proof_note' => ['nullable', 'string', 'max:2000'],
            'proof_url' => ['nullable', 'url:http,https', 'max:2048'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.self::MAX_FILE_KILOBYTES],
            'remove_file' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Require some proof: a note, a link or a file.
     *
     * A file already attached from an earlier submission still counts, unless
     * the student is removing it in this same request — sent-back work is
     * usually resubmitted with the same screenshot and a better note.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $task = $this->route('task');

                $keepsExistingFile = $task instanceof AgreementTask
                    && $task->proof_path !== null
                    && ! $this->boolean('remove_file');

                if (blank($this->input('proof_note'))
                    && blank($this->input('proof_url'))
                    && ! $this->hasFile('proof_file')
                    && ! $keepsExistingFile) {
                    $validator->errors()->add('proof_note', __('Add a note, a link or a file as proof before submitting.'));
                }
            },
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
            'proof_file.mimes' => __('Proof files must be an image (JPG, PNG, WebP) or a PDF.'),
            'proof_file.max' => __('Proof files can be at most 10 MB.'),
        ];
    }
}
