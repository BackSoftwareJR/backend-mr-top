<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Benvenuto su Wenando Pro</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1a1a1a;">
    <p>Ciao {{ $recipientName }},</p>
    <p>Benvenuto su Wenando Pro. Il tuo account partner è stato creato correttamente.</p>
    <p>Per iniziare a ricevere lead qualificati, completa l'onboarding: carica la documentazione richiesta e invia il profilo in revisione.</p>
    <p><a href="{{ $onboardingUrl }}">Vai all'onboarding</a></p>
    <p style="font-size: 12px; color: #666;">Se il link non funziona, copia e incolla questo indirizzo nel browser: {{ $onboardingUrl }}</p>
</body>
</html>
