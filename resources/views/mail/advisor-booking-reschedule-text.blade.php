Ciao {{ $clientName }},

La tua chiamata gratuita con {{ $advisorName }} è stata spostata a una nuova data.

Nuova data: {{ $scheduledDate }}
Nuovo orario: {{ $scheduledTime }}
@if(!empty($leadTitle))
Ricerca: {{ $leadTitle }}
@endif

Ti chiameremo al numero indicato in fase di registrazione. Se devi modificare i dettagli, rispondi a questa email o visita la sezione Aiuto nel tuo account.

{{ $helpUrl }}

---
Operazioni Wenando — coda consulenze advisor: {{ $adminBookingsUrl }}
