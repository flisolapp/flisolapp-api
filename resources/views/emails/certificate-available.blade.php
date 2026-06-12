<x-mail::message>
# Seu certificado já está disponível

Olá, {{ $name }},

Agradecemos sua participação no evento. Foi um prazer contar com sua presença e contribuição para o sucesso desta iniciativa.

Seu certificado de participação já está disponível e pode ser acessado pelo link abaixo:

<x-mail::button :url="$certificateUrl">
    Acessar certificado
</x-mail::button>

Caso o botão não funcione, copie e cole este link no navegador:
{{ $certificateUrl }}

Recomendamos que faça o download e guarde uma cópia para seus registros.

Mais uma vez, agradecemos sua participação e esperamos encontrá-lo(a) em nossos próximos eventos.

Atenciosamente,
Equipe FLISoL
</x-mail::message>
