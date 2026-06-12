<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared People validation rules, reused by all three subscription requests.
 * Each concrete request extends this and adds its own type-specific rules.
 */
abstract class PeopleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // ── Shared People rules ───────────────────────────────────────────────────

    protected function peopleRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'federal_code' => ['required', 'string', 'max:14'],
            'phone' => ['required', 'string', 'max:20'],

            // Optional profile fields
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'site' => ['nullable', 'url', 'max:255'],
            'use_free' => ['nullable', 'boolean'],

            // Optional academic / location fields
            'distro_id' => ['nullable', 'integer', 'exists:distros,id'],
            'student_info_id' => ['nullable', 'integer', 'exists:student_infos,id'],
            'student_place' => ['nullable', 'string', 'max:255'],
            'student_course' => ['nullable', 'string', 'max:255'],
            'address_state' => ['nullable', 'string', 'max:10'],
        ];
    }

    // ── Shared People messages ────────────────────────────────────────────────

    protected function peopleMessages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'federal_code.required' => 'O CPF é obrigatório.',
            'phone.required' => 'O telefone é obrigatório.',
            'photo.image' => 'A foto deve ser uma imagem (jpeg, jpg, png ou webp).',
            'photo.max' => 'A foto não pode exceder 5 MB.',
            'site.url' => 'Informe uma URL válida.',
        ];
    }
}
