<?php

declare(strict_types=1);

namespace Bangsamu\Master\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class SyncUserStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email_id')
            ?? $this->input('email')
            ?? $this->input('person_id')
            ?? $this->input('person_email');

        $status = $this->input('status') ?? $this->input('active');

        $token = $this->input('token') ?? $this->bearerToken();

        $merged = [];
        if ($email !== null) {
            $merged['email_id'] = trim((string) $email);
        }
        if ($status !== null) {
            $merged['status'] = $status;
        }
        if ($token !== null) {
            $merged['token'] = trim((string) $token);
        }

        if (! empty($merged)) {
            $this->merge($merged);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email_id' => ['required', 'string', 'email'],
            'status' => ['required'],
            'token' => ['required', 'string'],
        ];
    }

    /**
     * Custom message for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email_id.required' => 'User identifier (email_id) is required.',
            'email_id.email' => 'User identifier (email_id) must be a valid email address.',
            'status.required' => 'The status parameter is required (1 for active, 0 for inactive).',
            'token.required' => 'Security token is required.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'status' => false,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
