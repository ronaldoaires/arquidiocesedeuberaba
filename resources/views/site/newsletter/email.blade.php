<!doctype html>
<html lang="pt-BR">
<body>
    <h1>Boletim da Arquidiocese de Uberaba</h1>
    @if($active)
        <p>Sua inscrição está ativa. Use os links abaixo para gerenciar seus dados.</p>
        <p><a href="{{ $actionUrl }}">Atualizar meus dados</a></p>
        <p><a href="{{ $cancelUrl }}">Cancelar inscrição</a></p>
    @else
        <p>Você pediu para receber o boletim da Arquidiocese de Uberaba. Para concluir sua inscrição, acesse o link abaixo.</p>
        <p><a href="{{ $actionUrl }}">Concluir minha inscrição</a></p>
    @endif
    <p>Os links pessoais desta mensagem valem por 24 horas. Para receber outro e-mail, informe seu endereço novamente no rodapé do site.</p>
    <p>Se você não fez essa solicitação, pode ignorar esta mensagem.</p>
</body>
</html>
