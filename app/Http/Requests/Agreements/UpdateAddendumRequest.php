<?php

namespace App\Http\Requests\Agreements;

use App\Models\Addendum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Section IV's target amount: the only number either party sets.
 *
 * Whole pesos, at most Addendum::MAX_AMOUNT (₱20,000, owner, 2026-10-10). The
 * two milestones are cut from it (30% down, 70% on completion), so they are
 * never typed.
 */
class UpdateAddendumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $addendum = $this->route('addendum');

        return $addendum instanceof Addendum && Gate::allows('edit', $addendum);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'total_amount' => ['required', 'integer', 'min:'.Addendum::MIN_AMOUNT, 'max:'.Addendum::MAX_AMOUNT],
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
            'total_amount.required' => __('Enter the target amount.'),
            'total_amount.integer' => __('Enter the target amount in whole pesos.'),
            'total_amount.min' => __('The target amount is at least ₱:min.', ['min' => number_format(Addendum::MIN_AMOUNT)]),
            'total_amount.max' => __('The target amount can be at most ₱:max.', ['max' => number_format(Addendum::MAX_AMOUNT)]),
        ];
    }
}
