<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Submit a claim over a guest slot — Sprint 13, task 13.1. Any authenticated
 * archer may claim ("Ini Saya"); the host gates it via approval (anti-abuse).
 * The message is an optional note to the host ("ini aku, Budi yang di bantalan
 * 3").
 */
class StoreScoringSessionClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:280'],
        ];
    }
}
