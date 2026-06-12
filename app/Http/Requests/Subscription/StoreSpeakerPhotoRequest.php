<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpeakerPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'], // 5 MB
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'A foto é obrigatória.',
            'photo.image' => 'O arquivo deve ser uma imagem (jpeg, jpg, png ou webp).',
            'photo.mimes' => 'O arquivo deve ser uma imagem (jpeg, jpg, png ou webp).',
            'photo.max' => 'A foto não pode exceder 5 MB.',
        ];
    }
}
