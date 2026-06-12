<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class StoreTalkSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slide_file' => ['required', 'file', 'mimes:pdf,ppt,pptx,odp', 'max:10240'], // 10 MB
        ];
    }

    public function messages(): array
    {
        return [
            'slide_file.required' => 'O arquivo de slide é obrigatório.',
            'slide_file.file' => 'O campo deve ser um arquivo.',
            'slide_file.mimes' => 'O slide deve ser um arquivo PDF, PPT, PPTX ou ODP.',
            'slide_file.max' => 'O slide não pode exceder 10 MB.',
        ];
    }
}
