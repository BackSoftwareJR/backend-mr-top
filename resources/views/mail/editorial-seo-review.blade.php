<p>Ciao {{ $recipientName }},</p>

<p>La generazione SEO per <strong>{{ $contentTitle }}</strong> richiede attenzione.</p>

<p>
<strong>Punteggio:</strong> {{ $seoScore }}/100 (minimo {{ $minScore }})<br>
<strong>Motivo:</strong>
@if ($reason === 'rejected')
revisione SEO rifiutata
@else
punteggio sotto la soglia
@endif
</p>

<p>Apri l'editor per rivedere titolo, meta description e approvazione SEO:</p>
<p><a href="{{ $editUrl }}">{{ $editUrl }}</a></p>

<p>— Wenando Editorial</p>
