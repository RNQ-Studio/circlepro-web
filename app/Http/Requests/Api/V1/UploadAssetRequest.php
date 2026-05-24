<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // Normalisasi default boolean agar 'is_protected' tidak wajib dikirim klien.
        $this->merge([
            'is_protected' => $this->boolean('is_protected'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:51200', // 50 MB dalam kilobyte
                'mimes:jpeg,jpg,png,webp,gif,svg,mp4,mov,avi,webm,mp3,wav,ogg,m4a,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv',
            ],
            'morphable_type' => ['required', 'string', 'max:255'],
            'morphable_id' => ['required', 'integer'],
            'retain_until' => ['nullable', 'date'], // tidak diisi → NULL → file permanen
            'is_protected' => ['boolean'],
        ];
    }
}
