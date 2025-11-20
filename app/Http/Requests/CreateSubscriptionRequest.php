<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by auth middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'string',
                'starts_with:price_', // Stripe price IDs start with price_
                'max:255'
            ],
            'payment_method_id' => [
                'required',
                'string',
                'starts_with:pm_', // Stripe payment method IDs start with pm_
                'max:255'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'Please select a subscription plan',
            'plan_id.starts_with' => 'Invalid plan ID format',
            'payment_method_id.required' => 'Please select a payment method',
            'payment_method_id.starts_with' => 'Invalid payment method ID format'
        ];
    }
}
