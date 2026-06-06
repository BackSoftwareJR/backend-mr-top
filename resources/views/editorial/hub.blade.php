<!DOCTYPE html>
<html lang="it-IT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Magazine | Wenando</title>
    <meta name="description" content="Guide, storie e approfondimenti sull'assistenza agli anziani in Italia.">
    <link rel="canonical" href="{{ rtrim(config('editorial.site_url', config('app.url')), '/') }}/magazine">
    <style>
        :root {
            --cream: #FDFBF7;
            --coral: #E07A5F;
            --ink: #1F2937;
            --muted: #6B7280;
            --border: #E5E7EB;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--cream);
            color: var(--ink);
            line-height: 1.5;
        }

        .hub-shell {
            max-width: 960px;
            margin: 0 auto;
            padding: 2rem 1.25rem 4rem;
        }

        h1 {
            font-family: Georgia, "Times New Roman", serif;
            font-size: 2rem;
            margin: 0 0 0.5rem;
        }

        .hub-intro {
            color: var(--muted);
            margin: 0 0 2rem;
        }

        .hub-grid {
            display: grid;
            gap: 1rem;
        }

        .hub-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
        }

        .hub-card a {
            color: inherit;
            text-decoration: none;
        }

        .hub-card a:hover h2 {
            color: var(--coral);
        }

        .hub-card__rubric {
            color: var(--coral);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
            margin: 0 0 0.35rem;
        }

        .hub-card h2 {
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.25rem;
            margin: 0 0 0.5rem;
        }

        .hub-card p {
            margin: 0;
            color: var(--muted);
            font-size: 0.9375rem;
        }
    </style>
</head>
<body>
    <div class="hub-shell">
        <h1>Magazine Wenando</h1>
        <p class="hub-intro">Guide, storie e approfondimenti sull'assistenza agli anziani.</p>

        <div class="hub-grid">
            @forelse ($contents as $item)
                @php
                    $rubricSlug = $item->rubric?->slug ?? $item->rubric_slug ?? 'magazine';
                @endphp
                <article class="hub-card">
                    <a href="/magazine/{{ $rubricSlug }}/{{ $item->slug }}">
                        <p class="hub-card__rubric">{{ $item->rubric?->name ?? $item->rubric_slug }}</p>
                        <h2>{{ $item->title }}</h2>
                        @if ($item->excerpt)
                            <p>{{ $item->excerpt }}</p>
                        @endif
                    </a>
                </article>
            @empty
                <p>Nessun contenuto pubblicato al momento.</p>
            @endforelse
        </div>
    </div>
</body>
</html>
