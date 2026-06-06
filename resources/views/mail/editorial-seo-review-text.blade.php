Ciao {{ $recipientName }},

La generazione SEO per "{{ $contentTitle }}" richiede attenzione.

Punteggio: {{ $seoScore }}/100 (minimo {{ $minScore }})
Motivo: @if ($reason === 'rejected') revisione SEO rifiutata @else punteggio sotto la soglia @endif

Apri l'editor:
{{ $editUrl }}

— Wenando Editorial
