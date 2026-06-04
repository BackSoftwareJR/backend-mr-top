Nuova richiesta di cancellazione dati (diritto all'oblio — Art. 17 GDPR)

ID richiesta: {{ $erasureRequestId }}
Utente UUID: {{ $userUuid }}
Email utente: {{ $userEmail }}
Data richiesta: {{ $requestedAt ?? 'n/d' }}
Motivo: {{ $reason ?? '(non indicato)' }}

Verificare identità e approvare la richiesta in coda admin entro 30 giorni.
Dopo l'approvazione, ProcessDataErasureRequest esegue soft-delete, anonimizzazione lead e revoca token.
