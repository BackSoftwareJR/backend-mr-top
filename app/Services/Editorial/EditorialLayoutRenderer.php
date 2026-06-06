<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use Illuminate\Support\Str;

class EditorialLayoutRenderer
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{level: int, text: string, anchor: string}>  $toc
     * @param  list<array{question: string, answer: string}>  $faqItems
     */
    public function render(array $data, array &$toc, array &$faqItems): string
    {
        $templateId = (string) ($data['template_id'] ?? '');
        $slots = is_array($data['slots'] ?? null) ? $data['slots'] : [];

        return match ($templateId) {
            'hero-coral' => $this->renderHeroCoral($slots),
            'prose-block' => $this->renderProseBlock($slots),
            'split-text-image' => $this->renderSplitTextImage($slots, $toc),
            'highlight-band' => $this->renderHighlightBand($slots, $toc),
            'faq-band' => $this->renderFaqBand($slots, $toc, $faqItems),
            'quote-spotlight' => $this->renderQuoteSpotlight($slots),
            'stats-row' => $this->renderStatsRow($slots),
            'cta-coral' => $this->renderCtaCoral($slots, $toc),
            'interview-qa' => $this->renderInterviewQa($slots, $toc),
            'event-card' => $this->renderEventCard($slots, $toc),
            'checklist-band' => $this->renderChecklistBand($slots, $toc),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $slots
     * @return list<string>
     */
    public function extractPlainTextFromSlots(array $slots): array
    {
        $parts = [];

        foreach ($slots as $value) {
            if (is_string($value)) {
                $text = trim(strip_tags($value));

                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return $parts;
    }

    /**
     * @param  array<string, mixed>  $slots
     */
    private function renderHeroCoral(array $slots): string
    {
        $eyebrow = trim(strip_tags((string) ($slots['eyebrow'] ?? '')));
        $title = trim(strip_tags((string) ($slots['title'] ?? '')));
        $subtitle = trim(strip_tags((string) ($slots['subtitle'] ?? '')));
        $cta = trim(strip_tags((string) ($slots['cta'] ?? '')));

        if ($title === '' && $subtitle === '') {
            return '';
        }

        $html = '<section class="editorial-layout editorial-layout--hero-coral">';

        if ($eyebrow !== '') {
            $html .= '<p class="editorial-layout__eyebrow">'.e($eyebrow).'</p>';
        }

        if ($title !== '') {
            $html .= '<h2 class="editorial-layout__hero-title">'.e($title).'</h2>';
        }

        if ($subtitle !== '') {
            $html .= '<p class="editorial-layout__hero-subtitle">'.nl2br(e($subtitle)).'</p>';
        }

        if ($cta !== '') {
            $html .= '<p class="editorial-layout__hero-cta">'.e($cta).'</p>';
        }

        $html .= '</section>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $slots
     */
    private function renderProseBlock(array $slots): string
    {
        $body = trim(strip_tags((string) ($slots['body'] ?? '')));

        if ($body === '') {
            return '';
        }

        $paragraphs = preg_split("/\n\s*\n/u", $body) ?: [$body];
        $html = '<section class="editorial-layout editorial-layout--prose">';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph !== '') {
                $html .= '<p class="editorial-paragraph">'.nl2br(e($paragraph)).'</p>';
            }
        }

        $html .= '</section>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $slots
     * @param  list<array{level: int, text: string, anchor: string}>  $toc
     */
    private function renderSplitTextImage(array $slots, array &$toc): string
    {
        $title = trim(strip_tags((string) ($slots['title'] ?? '')));
        $body = trim(strip_tags((string) ($slots['body'] ?? '')));
        $imageUrl = trim((string) ($slots['image_url'] ?? ''));
        $imageAlt = trim(strip_tags((string) ($slots['image_alt'] ?? '')));

        if ($title === '' && $body === '' && $imageUrl === '') {
            return '';
        }

        if ($title !== '') {
            $anchor = Str::slug($title);
            $toc[] = ['level' => 3, 'text' => $title, 'anchor' => $anchor !== '' ? $anchor : 'section'];
        }

        $html = '<section class="editorial-layout editorial-layout--split"><div class="editorial-layout__split-text">';

        if ($title !== '') {
            $html .= '<h3 id="'.e($toc[array_key_last($toc)]['anchor']).'" class="editorial-heading editorial-heading--h3">'.e($title).'</h3>';
        }

        if ($body !== '') {
            $html .= '<p class="editorial-paragraph">'.nl2br(e($body)).'</p>';
        }

        $html .= '</div><div class="editorial-layout__split-media">';

        if ($imageUrl !== '') {
            $html .= '<figure class="editorial-figure"><img src="'.e($imageUrl).'" alt="'.e($imageAlt).'" loading="lazy" class="editorial-image"></figure>';
        }

        $html .= '</div></section>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $slots
     * @param  list<array{level: int, text: string, anchor: string}>  $toc
     */
    private function renderHighlightBand(array $slots, array &$toc): string
    {
        $title = trim(strip_tags((string) ($slots['title'] ?? '')));
        $items = [];

        foreach (['item1', 'item2', 'item3'] as $key) {
            $item = trim(strip_tags((string) ($slots[$key] ?? '')));

            if ($item !== '') {
                $items[] = $item;
            }
        }

        if ($title === '' && $items === []) {
            return '';
        }

        if ($title !== '') {
            $anchor = Str::slug($title);
            $toc[] = ['level' => 3, 'text' => $title, 'anchor' => $anchor !== '' ? $anchor : 'highlight'];
        }

        $html = '<aside class="editorial-layout editorial-layout--highlight" role="note">';

        if ($title !== '') {
            $html .= '<h3 class="editorial-layout__highlight-title">'.e($title).'</h3>';
        }

        if ($items !== []) {
            $html .= '<ul class="editorial-layout__highlight-list">';

            foreach ($items as $item) {
                $html .= '<li>'.e($item).'</li>';
            }

            $html .= '</ul>';
        }

        $html .= '</aside>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $slots
     * @param  list<array{level: int, text: string, anchor: string}>  $toc
     * @param  list<array{question: string, answer: string}>  $faqItems
     */
    private function renderFaqBand(array $slots, array &$toc, array &$faqItems): string
    {
        $title = trim(strip_tags((string) ($slots['title'] ?? '')));
        $pairs = [
            ['q1', 'a1'],
            ['q2', 'a2'],
            ['q3', 'a3'],
        ];

        $hasContent = $title !== '';

        foreach ($pairs as [$qKey, $aKey]) {
            if (trim((string) ($slots[$qKey] ?? '')) !== '') {
                $hasContent = true;
                break;
            }
        }

        if (! $hasContent) {
            return '';
        }

        if ($title !== '') {
            $anchor = Str::slug($title);
            $toc[] = ['level' => 3, 'text' => $title, 'anchor' => $anchor !== '' ? $anchor : 'faq'];
        }

        $html = '<section class="editorial-layout editorial-layout--faq" aria-label="Domande frequenti">';

        if ($title !== '') {
            $html .= '<h3 class="editorial-layout__faq-title">'.e($title).'</h3>';
        }

        foreach ($pairs as [$qKey, $aKey]) {
            $question = trim(strip_tags((string) ($slots[$qKey] ?? '')));
            $answer = trim(strip_tags((string) ($slots[$aKey] ?? '')));

            if ($question === '' || $answer === '') {
                continue;
            }

            $faqItems[] = ['question' => $question, 'answer' => $answer];

            $html .= '<details class="editorial-faq__item">';
            $html .= '<summary class="editorial-faq__question">'.e($question).'</summary>';
            $html .= '<div class="editorial-faq__answer"><p>'.nl2br(e($answer)).'</p></div>';
            $html .= '</details>';
        }

        $html .= '</section>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $slots
     */
    private function renderQuoteSpotlight(array $slots): string
    {
        $quote = trim(strip_tags((string) ($slots['quote'] ?? '')));
        $author = trim(strip_tags((string) ($slots['author'] ?? '')));

        if ($quote === '') {
            return '';
        }

        $html = '<blockquote class="editorial-layout editorial-layout--quote"><p>'.nl2br(e($quote)).'</p>';

        if ($author !== '') {
            $html .= '<footer>— '.e($author).'</footer>';
        }

        $html .= '</blockquote>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $slots
     */
    private function renderStatsRow(array $slots): string
    {
        $stats = [];

        foreach ([1, 2, 3] as $index) {
            $value = trim(strip_tags((string) ($slots["stat{$index}_value"] ?? '')));
            $label = trim(strip_tags((string) ($slots["stat{$index}_label"] ?? '')));

            if ($value !== '' || $label !== '') {
                $stats[] = ['value' => $value, 'label' => $label];
            }
        }

        if ($stats === []) {
            return '';
        }

        $html = '<section class="editorial-layout editorial-layout--stats" aria-label="Dati chiave"><div class="editorial-layout__stats-grid">';

        foreach ($stats as $stat) {
            $html .= '<div class="editorial-layout__stat">';
            $html .= '<p class="editorial-layout__stat-value">'.e($stat['value']).'</p>';
            $html .= '<p class="editorial-layout__stat-label">'.e($stat['label']).'</p>';
            $html .= '</div>';
        }

        $html .= '</div></section>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $slots
     * @param  list<array{level: int, text: string, anchor: string}>  $toc
     */
    private function renderCtaCoral(array $slots, array &$toc): string
    {
        $title = trim(strip_tags((string) ($slots['title'] ?? '')));
        $body = trim(strip_tags((string) ($slots['body'] ?? '')));
        $buttonLabel = trim(strip_tags((string) ($slots['button_label'] ?? '')));
        $buttonUrl = trim((string) ($slots['button_url'] ?? ''));

        if ($title === '' && $body === '') {
            return '';
        }

        if ($title !== '') {
            $anchor = Str::slug($title);
            $toc[] = ['level' => 3, 'text' => $title, 'anchor' => $anchor !== '' ? $anchor : 'cta'];
        }

        $html = '<section class="editorial-layout editorial-layout--cta">';

        if ($title !== '') {
            $html .= '<h3 class="editorial-layout__cta-title">'.e($title).'</h3>';
        }

        if ($body !== '') {
            $html .= '<p class="editorial-layout__cta-body">'.nl2br(e($body)).'</p>';
        }

        if ($buttonLabel !== '' && $buttonUrl !== '') {
            $html .= '<p class="editorial-layout__cta-action"><a href="'.e($buttonUrl).'" class="editorial-layout__cta-button" rel="noopener">'.e($buttonLabel).'</a></p>';
        }

        $html .= '</section>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $slots
     * @param  list<array{level: int, text: string, anchor: string}>  $toc
     */
    private function renderInterviewQa(array $slots, array &$toc): string
    {
        $title = trim(strip_tags((string) ($slots['title'] ?? '')));
        $intro = trim(strip_tags((string) ($slots['intro'] ?? '')));
        $pairs = [
            ['q1', 'a1'],
            ['q2', 'a2'],
            ['q3', 'a3'],
        ];

        $hasContent = $title !== '' || $intro !== '';

        foreach ($pairs as [$qKey, $aKey]) {
            if (trim((string) ($slots[$qKey] ?? '')) !== '') {
                $hasContent = true;
                break;
            }
        }

        if (! $hasContent) {
            return '';
        }

        if ($title !== '') {
            $anchor = Str::slug($title);
            $toc[] = ['level' => 2, 'text' => $title, 'anchor' => $anchor !== '' ? $anchor : 'interview'];
        }

        $html = '<section class="editorial-layout editorial-layout--interview-qa" aria-label="Intervista">';

        if ($title !== '') {
            $html .= '<h2 class="editorial-layout__interview-title">'.e($title).'</h2>';
        }

        if ($intro !== '') {
            $html .= '<p class="editorial-layout__interview-intro">'.nl2br(e($intro)).'</p>';
        }

        $html .= '<dl class="editorial-layout__interview-qa">';

        foreach ($pairs as [$qKey, $aKey]) {
            $question = trim(strip_tags((string) ($slots[$qKey] ?? '')));
            $answer = trim(strip_tags((string) ($slots[$aKey] ?? '')));

            if ($question === '') {
                continue;
            }

            if ($question !== '') {
                $anchor = Str::slug($question);
                $toc[] = ['level' => 3, 'text' => $question, 'anchor' => $anchor !== '' ? $anchor : 'question'];
            }

            $html .= '<div class="editorial-layout__interview-item">';
            $html .= '<dt class="editorial-layout__interview-question"><h3>'.e($question).'</h3></dt>';

            if ($answer !== '') {
                $html .= '<dd class="editorial-layout__interview-answer"><p>'.nl2br(e($answer)).'</p></dd>';
            }

            $html .= '</div>';
        }

        $html .= '</dl></section>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $slots
     * @param  list<array{level: int, text: string, anchor: string}>  $toc
     */
    private function renderEventCard(array $slots, array &$toc): string
    {
        $title = trim(strip_tags((string) ($slots['title'] ?? '')));
        $eventDate = trim(strip_tags((string) ($slots['event_date'] ?? '')));
        $eventTime = trim(strip_tags((string) ($slots['event_time'] ?? '')));
        $eventLocation = trim(strip_tags((string) ($slots['event_location'] ?? '')));
        $description = trim(strip_tags((string) ($slots['description'] ?? '')));
        $ctaLabel = trim(strip_tags((string) ($slots['cta_label'] ?? '')));
        $ctaUrl = trim((string) ($slots['cta_url'] ?? ''));

        if ($title === '' && $eventDate === '' && $description === '') {
            return '';
        }

        if ($title !== '') {
            $anchor = Str::slug($title);
            $toc[] = ['level' => 2, 'text' => $title, 'anchor' => $anchor !== '' ? $anchor : 'event'];
        }

        $html = '<article class="editorial-layout editorial-layout--event-card" itemscope itemtype="https://schema.org/Event">';

        if ($title !== '') {
            $html .= '<h2 class="editorial-layout__event-title" itemprop="name">'.e($title).'</h2>';
        }

        $html .= '<div class="editorial-layout__event-meta">';

        if ($eventDate !== '') {
            $html .= '<p class="editorial-layout__event-date"><span class="editorial-layout__event-label">Data</span> <time itemprop="startDate">'.e($eventDate).'</time></p>';
        }

        if ($eventTime !== '') {
            $html .= '<p class="editorial-layout__event-time"><span class="editorial-layout__event-label">Orario</span> '.e($eventTime).'</p>';
        }

        if ($eventLocation !== '') {
            $html .= '<p class="editorial-layout__event-location" itemprop="location">'.e($eventLocation).'</p>';
        }

        $html .= '</div>';

        if ($description !== '') {
            $html .= '<p class="editorial-layout__event-description" itemprop="description">'.nl2br(e($description)).'</p>';
        }

        if ($ctaLabel !== '' && $ctaUrl !== '') {
            $html .= '<p class="editorial-layout__event-cta"><a href="'.e($ctaUrl).'" class="editorial-layout__event-button" rel="noopener">'.e($ctaLabel).'</a></p>';
        }

        $html .= '</article>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $slots
     * @param  list<array{level: int, text: string, anchor: string}>  $toc
     */
    private function renderChecklistBand(array $slots, array &$toc): string
    {
        $title = trim(strip_tags((string) ($slots['title'] ?? '')));
        $intro = trim(strip_tags((string) ($slots['intro'] ?? '')));
        $items = [];

        foreach (['item1', 'item2', 'item3', 'item4', 'item5'] as $key) {
            $item = trim(strip_tags((string) ($slots[$key] ?? '')));

            if ($item !== '') {
                $items[] = $item;
            }
        }

        if ($title === '' && $intro === '' && $items === []) {
            return '';
        }

        if ($title !== '') {
            $anchor = Str::slug($title);
            $toc[] = ['level' => 3, 'text' => $title, 'anchor' => $anchor !== '' ? $anchor : 'checklist'];
        }

        $html = '<aside class="editorial-layout editorial-layout--checklist" role="note" aria-label="Checklist">';

        if ($title !== '') {
            $html .= '<h3 class="editorial-layout__checklist-title">'.e($title).'</h3>';
        }

        if ($intro !== '') {
            $html .= '<p class="editorial-layout__checklist-intro">'.nl2br(e($intro)).'</p>';
        }

        if ($items !== []) {
            $html .= '<ul class="editorial-layout__checklist-list" role="list">';

            foreach ($items as $item) {
                $html .= '<li>'.e($item).'</li>';
            }

            $html .= '</ul>';
        }

        $html .= '</aside>';

        return $html;
    }
}
