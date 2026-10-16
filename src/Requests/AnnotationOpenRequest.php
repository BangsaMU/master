<?php

namespace Bangsamu\Master\Requests;

use Bangsamu\Master\Services\AnnotationLaunchTokenService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AnnotationOpenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file_url' => ['required', 'string', 'max:2048'],
            'callback' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tokens = app(AnnotationLaunchTokenService::class);
            foreach (['file_url', 'callback'] as $field) {
                $value = $this->input($field);
                if (is_string($value) && $value !== '' && ! $tokens->isAllowedUrl($this->absolute($field), $this->getHost())) {
                    $validator->errors()->add($field, "Host {$field} tidak diizinkan.");
                }
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        abort(422, $validator->errors()->first());
    }

    public function fileUrl(): string
    {
        return $this->absolute('file_url');
    }

    public function callbackUrl(): string
    {
        return $this->filled('callback') ? $this->absolute('callback') : url('api/setAnnotationId');
    }

    protected function absolute(string $field): string
    {
        return app(AnnotationLaunchTokenService::class)->absoluteUrl((string) $this->input($field));
    }
}
