<?php

namespace App\Http\Requests\Subscription;

class StoreParticipantRequest extends PeopleRequest
{
    public function rules(): array
    {
        return array_merge($this->peopleRules(), [
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
        ]);
    }

    public function messages(): array
    {
        return array_merge($this->peopleMessages(), [
            'edition_id.required' => 'A edição é obrigatória.',
            'edition_id.exists' => 'A edição informada não existe.',
        ]);
    }
}
