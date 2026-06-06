<!DOCTYPE html>
<html lang="it-IT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seoTitle }} | Wenando</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta name="robots" content="index,follow">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Wenando">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    @if ($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
    <style>
        :root {
            --cream: #FDFBF7;
            --coral: #E07A5F;
            --ink: #1F2937;
            --muted: #6B7280;
            --border: #E5E7EB;
            --callout-info: #EFF6FF;
            --callout-warning: #FFF7ED;
            --callout-tip: #ECFDF5;
            --callout-danger: #FEF2F2;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            background: var(--cream);
            color: var(--ink);
            line-height: 1.7;
        }

        a { color: var(--coral); }

        .editorial-shell {
            max-width: 760px;
            margin: 0 auto;
            padding: 2rem 1.25rem 4rem;
        }

        .editorial-nav {
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }

        .editorial-nav a {
            text-decoration: none;
        }

        .editorial-nav a:hover {
            text-decoration: underline;
        }

        .editorial-disclaimer {
            font-family: system-ui, -apple-system, sans-serif;
            background: #FFF4EC;
            border-left: 4px solid var(--coral);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.9375rem;
        }

        .geo-summary {
            font-family: system-ui, -apple-system, sans-serif;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 1.05rem;
        }

        .editorial-header {
            margin-bottom: 2rem;
        }

        .editorial-rubric {
            font-family: system-ui, -apple-system, sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.75rem;
            color: var(--coral);
            font-weight: 600;
            margin: 0 0 0.75rem;
        }

        .editorial-title {
            font-size: clamp(1.875rem, 4vw, 2.5rem);
            line-height: 1.2;
            margin: 0 0 0.75rem;
        }

        .editorial-subtitle {
            font-size: 1.125rem;
            color: var(--muted);
            margin: 0 0 1rem;
        }

        .editorial-meta {
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 0.875rem;
            color: var(--muted);
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.25rem;
        }

        .editorial-toc {
            font-family: system-ui, -apple-system, sans-serif;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 2rem;
        }

        .editorial-toc h2 {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 0 0 0.75rem;
        }

        .editorial-toc ol {
            margin: 0;
            padding-left: 1.25rem;
        }

        .editorial-toc li {
            margin: 0.35rem 0;
        }

        .editorial-toc a {
            text-decoration: none;
        }

        .editorial-toc a:hover {
            text-decoration: underline;
        }

        .editorial-body {
            font-size: 1.0625rem;
        }

        .editorial-heading {
            scroll-margin-top: 1rem;
        }

        .editorial-heading--h2 {
            font-size: 1.5rem;
            margin: 2rem 0 0.75rem;
        }

        .editorial-heading--h3 {
            font-size: 1.25rem;
            margin: 1.5rem 0 0.5rem;
        }

        .editorial-paragraph {
            margin: 0 0 1rem;
        }

        .editorial-figure {
            margin: 1.5rem 0;
        }

        .editorial-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            display: block;
        }

        .editorial-figcaption {
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 0.875rem;
            color: var(--muted);
            margin-top: 0.5rem;
        }

        .editorial-callout {
            font-family: system-ui, -apple-system, sans-serif;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin: 1.5rem 0;
        }

        .editorial-callout--info { background: var(--callout-info); }
        .editorial-callout--warning { background: var(--callout-warning); }
        .editorial-callout--tip { background: var(--callout-tip); }
        .editorial-callout--danger { background: var(--callout-danger); }

        .editorial-callout__title {
            margin: 0 0 0.35rem;
        }

        .editorial-callout__body {
            margin: 0;
        }

        .editorial-quote {
            border-left: 4px solid var(--coral);
            margin: 1.5rem 0;
            padding: 0.5rem 0 0.5rem 1.25rem;
            font-style: italic;
        }

        .editorial-quote footer {
            font-style: normal;
            font-size: 0.875rem;
            color: var(--muted);
            margin-top: 0.5rem;
        }

        /* Layout blocks (Wix-style tile sections) */
        .editorial-layout {
            font-family: system-ui, -apple-system, sans-serif;
            margin: 2rem 0;
        }

        .editorial-layout--hero-coral {
            background: linear-gradient(135deg, #FFF4EC 0%, var(--cream) 100%);
            border-radius: 16px;
            padding: 2.5rem 2rem;
            text-align: center;
        }

        .editorial-layout__eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.2em;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--coral);
            margin: 0 0 0.75rem;
        }

        .editorial-layout__hero-title {
            font-family: Georgia, serif;
            font-size: clamp(1.75rem, 4vw, 2.25rem);
            line-height: 1.2;
            margin: 0 0 0.75rem;
            color: var(--ink);
        }

        .editorial-layout__hero-subtitle {
            font-size: 1.0625rem;
            color: var(--muted);
            max-width: 36rem;
            margin: 0 auto 1rem;
        }

        .editorial-layout__hero-cta {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--coral);
            margin: 0;
        }

        .editorial-layout--prose {
            margin: 1.5rem 0;
        }

        .editorial-layout--split {
            display: grid;
            gap: 1.5rem;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
        }

        @media (min-width: 640px) {
            .editorial-layout--split {
                grid-template-columns: 1fr 1fr;
            }
        }

        .editorial-layout--highlight {
            background: linear-gradient(135deg, #FFF4EC, var(--cream));
            border: 1px solid rgba(224, 122, 95, 0.2);
            border-radius: 16px;
            padding: 1.75rem 2rem;
        }

        .editorial-layout__highlight-title {
            font-family: Georgia, serif;
            font-size: 1.25rem;
            margin: 0 0 1rem;
        }

        .editorial-layout__highlight-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .editorial-layout__highlight-list li {
            position: relative;
            padding-left: 1.25rem;
            margin: 0.5rem 0;
        }

        .editorial-layout__highlight-list li::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0.55em;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--coral);
        }

        .editorial-layout--faq {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.75rem 2rem;
        }

        .editorial-layout__faq-title {
            font-family: Georgia, serif;
            font-size: 1.25rem;
            margin: 0 0 1rem;
        }

        .editorial-layout--quote {
            background: var(--ink);
            color: #fff;
            border-left: none;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            font-size: 1.25rem;
        }

        .editorial-layout--quote footer {
            color: var(--coral);
            margin-top: 1rem;
        }

        .editorial-layout__stats-grid {
            display: grid;
            gap: 1rem;
        }

        @media (min-width: 640px) {
            .editorial-layout__stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .editorial-layout__stat {
            background: var(--cream);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
        }

        .editorial-layout__stat-value {
            font-family: Georgia, serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--coral);
            margin: 0;
        }

        .editorial-layout__stat-label {
            font-size: 0.875rem;
            color: var(--muted);
            margin: 0.25rem 0 0;
        }

        .editorial-layout--cta {
            background: var(--coral);
            color: #fff;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
        }

        .editorial-layout__cta-title {
            font-family: Georgia, serif;
            font-size: 1.5rem;
            margin: 0 0 0.75rem;
            color: #fff;
        }

        .editorial-layout__cta-body {
            margin: 0 0 1.25rem;
            opacity: 0.95;
        }

        .editorial-layout__cta-button {
            display: inline-block;
            background: #fff;
            color: var(--coral);
            font-weight: 600;
            padding: 0.65rem 1.5rem;
            border-radius: 999px;
            text-decoration: none;
        }

        .editorial-list {
            margin: 0 0 1rem;
            padding-left: 1.5rem;
        }

        .editorial-faq {
            margin: 2rem 0;
        }

        .editorial-faq__item {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            margin-bottom: 0.75rem;
            padding: 0.75rem 1rem;
        }

        .editorial-faq__question {
            cursor: pointer;
            font-weight: 600;
            font-family: system-ui, -apple-system, sans-serif;
        }

        .editorial-faq__answer {
            margin-top: 0.75rem;
            color: var(--ink);
        }
    </style>
</head>
<body>
    <div class="editorial-shell">
        <nav class="editorial-nav" aria-label="Breadcrumb">
            <a href="/magazine">Magazine</a>
            @if ($content->rubric?->name ?? $content->rubric_slug)
                · <span>{{ $content->rubric?->name ?? $content->rubric_slug }}</span>
            @endif
        </nav>

        @if ($structureDisclaimer)
            <div class="editorial-disclaimer" role="note">
                {{ $structureDisclaimer }}
            </div>
        @endif

        <article itemscope itemtype="https://schema.org/Article">
            <header class="editorial-header">
                @if ($geoExcerpt !== '')
                    <p class="geo-summary" data-speakable="summary">{{ $geoExcerpt }}</p>
                @endif

                <p class="editorial-rubric">{{ $content->rubric?->name ?? $content->rubric_slug }}</p>
                <h1 class="editorial-title" itemprop="headline">{{ $content->title }}</h1>

                @if ($content->subtitle)
                    <p class="editorial-subtitle">{{ $content->subtitle }}</p>
                @endif

                <div class="editorial-meta">
                    @if ($authorName)
                        <span itemprop="author">{{ $authorName }}@if ($authorRole), {{ $authorRole }}@endif</span>
                    @endif
                    @if ($content->published_at)
                        <time datetime="{{ $content->published_at->toIso8601String() }}" itemprop="datePublished">
                            Pubblicato {{ $content->published_at->format('d/m/Y') }}
                        </time>
                    @endif
                    @if ($content->read_minutes)
                        <span>{{ $content->read_minutes }} min di lettura</span>
                    @endif
                </div>
            </header>

            @if (count($toc) > 0)
                <nav class="editorial-toc" aria-label="Indice">
                    <h2>Indice</h2>
                    <ol>
                        @foreach ($toc as $item)
                            <li>
                                <a href="#{{ $item['anchor'] }}">{{ $item['text'] }}</a>
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            <div class="editorial-body" itemprop="articleBody">
                {!! $bodyHtml !!}
            </div>
        </article>
    </div>
</body>
</html>
