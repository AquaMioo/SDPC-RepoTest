<?php

namespace App\Http\Requests\Agreements;

use App\Enums\AgreementParty;
use App\Enums\ContractRequestType;
use App\Models\ContractRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The answer to a denial or a termination request. A client agreeing to a
 * termination the student asked for rates the work as they agree; a decline
 * may say why.
 */
class AnswerContractRequestRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $request = $this->route('contractRequest');
        $clientRates = $this->routeIs('agreements.requests.accept')
            && $request instanceof ContractRequest
            && $request->type === ContractRequestType::Termination
            && $request->party === AgreementParty::Student;

        return [
            'rating' => $clientRates ? ['required', 'integer', 'between:1,5'] : ['nullable'],
            'feedback' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
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
            'rating.required' => __('Rate the work (1 to 5 stars). Ending the collaboration completes the project.'),
        ];
    }
}
