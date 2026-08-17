<?php

namespace App\Http\Requests\Agreements;

use App\Models\Agreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SignAgreementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');

        return $agreement instanceof Agreement && Gate::allows('sign', $agreement);
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
             * Typed by the signatory rather than copied from their profile.
             * A name they had to write themselves is the point of a signature.
             */
            'signed_name' => ['required', 'string', 'max:120'],
            'acknowledgements' => ['required', 'array'],
            'acknowledgements.*' => [
                'required',
                'string',
                Rule::in(array_keys((array) config('agreements.acknowledgements', []))),
            ],
        ];
    }

    /**
     * Get the acknowledgement keys the signatory ticked.
     *
     * @return list<string>
     */
    public function acknowledgements(): array
    {
        /** @var list<string> $acknowledgements */
        $acknowledgements = array_values(array_unique($this->array('acknowledgements')));

        return $acknowledgements;
    }
}
