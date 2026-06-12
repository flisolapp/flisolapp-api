<?php

namespace App\Http\Requests\Subscription;

class StoreSpeakerRequest extends PeopleRequest
{
    public function rules(): array
    {
        return [
            'edition_id' => ['required', 'integer', 'exists:editions,id'],

            // ── Speakers array ────────────────────────────────────────────────
            'speakers' => ['required', 'array', 'min:1'],
            'speakers.*.name' => ['required', 'string', 'max:255'],
            'speakers.*.email' => ['required', 'email', 'max:255'],
            'speakers.*.federal_code' => ['required', 'string', 'max:14'],
            'speakers.*.phone' => ['required', 'string', 'max:20'],
//            'speakers.*.photo'            => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'], // 5 MB
            'speakers.*.photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:20480'], // 20 MB
            'speakers.*.bio' => ['nullable', 'string', 'max:5000'],
            'speakers.*.site' => ['nullable', 'url', 'max:255'],

            // ── Talks array ───────────────────────────────────────────────────
            'talks' => ['required', 'array', 'min:1'],
            'talks.*.title' => ['required', 'string', 'max:255'],
            'talks.*.description' => ['required', 'string', 'max:5000'],
            'talks.*.shift' => ['required', 'in:M,A,W'],
            'talks.*.kind' => ['required', 'in:T,W'],
            'talks.*.talk_subject_id' => ['nullable', 'integer', 'exists:talk_subjects,id'],
            'talks.*.slide_file' => ['nullable', 'file', 'mimes:pdf,ppt,pptx,odp', 'max:51200'], // 50 MB
            'talks.*.slide_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'edition_id.required' => 'A edição é obrigatória.',
            'edition_id.exists' => 'A edição informada não existe.',

            'speakers.required' => 'É necessário informar ao menos um palestrante.',
            'speakers.min' => 'É necessário informar ao menos um palestrante.',
            'speakers.*.name.required' => 'O nome do palestrante é obrigatório.',
            'speakers.*.email.required' => 'O e-mail do palestrante é obrigatório.',
            'speakers.*.email.email' => 'Informe um e-mail válido para o palestrante.',
            'speakers.*.federal_code.required' => 'O CPF do palestrante é obrigatório.',
            'speakers.*.phone.required' => 'O telefone do palestrante é obrigatório.',
            'speakers.*.photo.image' => 'A foto deve ser uma imagem (jpeg, jpg, png ou webp).',
            'speakers.*.photo.max' => 'A foto não pode exceder 5 MB.',
            'speakers.*.site.url' => 'Informe uma URL válida para o site do palestrante.',

            'talks.required' => 'É necessário informar ao menos uma palestra.',
            'talks.min' => 'É necessário informar ao menos uma palestra.',
            'talks.*.title.required' => 'O título da palestra é obrigatório.',
            'talks.*.description.required' => 'A descrição da palestra é obrigatória.',
            'talks.*.shift.required' => 'O turno é obrigatório.',
            'talks.*.shift.in' => 'O turno deve ser M (Manhã), A (Tarde) ou W (Sem preferência).',
            'talks.*.kind.required' => 'O tipo da atividade é obrigatório.',
            'talks.*.kind.in' => 'The activity type must be T (Talk) or W (Workshop).',
            'talks.*.slide_file.mimes' => 'O slide deve ser um arquivo PDF, PPT, PPTX ou ODP.',
            'talks.*.slide_file.max' => 'O slide não pode exceder 50 MB.',
            'talks.*.slide_url.url' => 'Informe uma URL válida para o slide.',
        ];
    }
}
