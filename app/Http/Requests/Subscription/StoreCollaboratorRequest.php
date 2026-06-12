<?php

namespace App\Http\Requests\Subscription;

class StoreCollaboratorRequest extends PeopleRequest
{
    public function rules(): array
    {
        return array_merge($this->peopleRules(), [
            'edition_id' => ['required', 'integer', 'exists:editions,id'],

            // At least one collaboration area must be selected
            'areas' => ['required', 'array', 'min:1'],
            'areas.*' => ['integer', 'exists:collaboration_areas,id'],

            // At least one availability shift must be selected
            'availabilities' => ['required', 'array', 'min:1'],
            'availabilities.*' => ['integer', 'exists:collaboration_shifts,id'],
        ]);
    }

    public function messages(): array
    {
        return array_merge($this->peopleMessages(), [
            'edition_id.required' => 'A edição é obrigatória.',
            'edition_id.exists' => 'A edição informada não existe.',
            'areas.required' => 'Selecione pelo menos uma área de colaboração.',
            'areas.min' => 'Selecione pelo menos uma área de colaboração.',
            'areas.*.exists' => 'Uma ou mais áreas informadas são inválidas.',
            'availabilities.required' => 'Selecione pelo menos um turno de disponibilidade.',
            'availabilities.min' => 'Selecione pelo menos um turno de disponibilidade.',
            'availabilities.*.exists' => 'Um ou mais turnos informados são inválidos.',
        ]);
    }
}
