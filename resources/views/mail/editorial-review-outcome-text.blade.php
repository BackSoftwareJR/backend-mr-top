Ciao {{ $recipientName }},

@if ($approved)
Il contenuto editoriale "{{ $contentTitle }}" è stato approvato e pubblicato.
@else
Il contenuto editoriale "{{ $contentTitle }}" non è stato approvato in questa revisione.
@endif

@if ($note)
Nota del revisore: {{ $note }}
@endif

Apri il contenuto:
{{ $contentUrl }}

— Wenando Editorial
