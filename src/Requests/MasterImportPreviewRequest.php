<?php

declare(strict_types=1);

namespace Bangsamu\Master\Requests;

use Bangsamu\Master\Services\Import\MasterImportService;
use Illuminate\Foundation\Http\FormRequest;

class MasterImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = app(MasterImportService::class);

        return $service->canCreate($service->definition((string) $this->route('entity')));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480', 'mimes:xls,xlsx,txt,csv'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File import wajib dipilih.',
            'file.mimes' => 'File harus berformat xls, xlsx, atau csv.',
            'file.max' => 'Ukuran file maksimal 20 MB.',
        ];
    }
}
