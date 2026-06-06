<p>Ciao {{ $recipientName }},</p>

<p>Ecco il riepilogo giornaliero delle revisioni editoriali:</p>

<ul>
<li><strong>{{ $pendingModeration }}</strong> contenuti struttura in attesa di moderazione</li>
<li><strong>{{ $seoAttention }}</strong> generazioni SEO da rivedere (basso punteggio o rifiutate)</li>
</ul>

<p>Accedi alla coda revisioni:</p>
<p><a href="{{ $reviewUrl }}">{{ $reviewUrl }}</a></p>

<p>— Wenando Editorial</p>
