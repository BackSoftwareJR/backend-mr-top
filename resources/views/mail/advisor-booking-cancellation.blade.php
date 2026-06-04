<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cancellazione prenotazione</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1a1a1a;">
    <p>Ciao {{ $clientName }},</p>
    <p>La tua chiamata gratuita con {{ $advisorName }} è stata cancellata.</p>
    <p>
        <strong>Data originale:</strong> {{ $scheduledDate }}<br>
        <strong>Orario originale:</strong> {{ $scheduledTime }}
    </p>
    @if(!empty($leadTitle))
        <p><strong>Ricerca:</strong> {{ $leadTitle }}</p>
    @endif
    <p>Se vuoi prenotare un nuovo appuntamento, visita la sezione Aiuto nel tuo account o rispondi a questa email.</p>
    <p><a href="{{ $helpUrl }}">Vai alla sezione Aiuto</a></p>
    <hr style="border: none; border-top: 1px solid #e5e5e5; margin: 24px 0;">
    <p style="font-size: 12px; color: #666;">
        Operazioni Wenando — <a href="{{ $adminBookingsUrl }}">coda consulenze advisor</a>
    </p>
</body>
</html>
