Ciao {{ $clientName }},

La tua chiamata gratuita con {{ $advisorName }} è stata cancellata.

Data originale: {{ $scheduledDate }}
Orario originale: {{ $scheduledTime }}
@if(!empty($leadTitle))
Ricerca: {{ $leadTitle }}
@endif

Se vuoi prenotare un nuovo appuntamento, visita la sezione Aiuto nel tuo account o rispondi a questa email.

{{ $helpUrl }}

---
Operazioni Wenando — coda consulenze advisor: {{ $adminBookingsUrl }}
