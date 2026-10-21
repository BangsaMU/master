<?php

declare(strict_types=1);

namespace Bangsamu\Master\Requests;

use Bangsamu\Master\Services\Import\MasterImportService;
use Illuminate\Foundation\Http\FormRequest;

class MasterImportCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = app(MasterImportService::class);
        $definition = $service->definition((string) $this->route('entity'));

        if (! $service->canCreate($definition)) {
            return false;
        }

        return ! $this->boolean('allow_update') || $service->canUpdate($definition);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'allow_update' => ['nullable', 'boolean'],
            'update_reason' => ['nullable', 'required_if_accepted:allow_update', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'update_reason.required_if_accepted' => 'Alasan update wajib diisi bila update data existing diizinkan.',
            'update_reason.min' => 'Alasan update minimal 5 karakter.',
            'update_reason.max' => 'Alasan update maksimal 500 karakter.',
        ];
    }
}
