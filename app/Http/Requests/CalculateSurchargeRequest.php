<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateSurchargeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999.99'
            ],
            'payment_method_type' => [
                'required',
                'string',
                'in:card,bank_account'
            ],
            'card_type' => [
                'nullable',
                'required_if:payment_method_type,card',
                'string',
                'in:credit,debit'
            ],
            'state_code' => [
                'nullable',
                'string',
                'size:2',
                'regex:/^[A-Z]{2}$/' // US state codes are 2 uppercase letters
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Amount is required',
            'amount.min' => 'Amount must be at least $0.01',
            'amount.max' => 'Amount cannot exceed $999,999.99',
            'payment_method_type.required' => 'Please select a payment method type',
            'payment_method_type.in' => 'Payment method type must be card or bank_account',
            'card_type.required_if' => 'Card type is required when paying with card',
            'card_type.in' => 'Card type must be credit or debit',
            'state_code.size' => 'State code must be 2 characters',
            'state_code.regex' => 'State code must be 2 uppercase letters (e.g., CA, NY)'
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Normalize state code to uppercase
        if ($this->has('state_code')) {
            $this->merge([
                'state_code' => strtoupper($this->state_code)
            ]);
        }
    }
}
