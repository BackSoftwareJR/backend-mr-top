<p>Ciao {{ $recipientName }},</p>

@if ($approved)
<p>Il contenuto editoriale <strong>{{ $contentTitle }}</strong> è stato <strong>approvato</strong> e pubblicato.</p>
@else
<p>Il contenuto editoriale <strong>{{ $contentTitle }}</strong> <strong>non è stato approvato</strong> in questa revisione.</p>
@endif

@if ($note)
<p><strong>Nota del revisore:</strong> {{ $note }}</p>
@endif

<p>Apri il contenuto per eventuali modifiche:</p>
<p><a href="{{ $contentUrl }}">{{ $contentUrl }}</a></p>

<p>— Wenando Editorial</p>
