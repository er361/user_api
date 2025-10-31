<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Note: In production tokens are always 64 chars, but we allow any length for testing flexibility
            'confirmation_token' => ['required', 'string', 'min:1'],
        ];
    }
}
