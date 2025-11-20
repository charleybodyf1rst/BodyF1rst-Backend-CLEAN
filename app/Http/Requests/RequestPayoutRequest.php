<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestPayoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Verify user is a coach
        return $this->user() && ($this->user()->role === 'coach' || $this->user()->hasRole('coach'));
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
                'min:1', // Minimum $1 payout
                'max:100000' // Maximum $100,000 per payout
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Payout amount is required',
            'amount.numeric' => 'Payout amount must be a valid number',
            'amount.min' => 'Minimum payout amount is $1.00',
            'amount.max' => 'Maximum payout amount is $100,000.00'
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'Only coaches can request payouts'
        );
    }
}
