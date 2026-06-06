<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Models\EditorialMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditorialBlockRenderer
{
    /**
     * @param  list<array<string, mixed>>|null  $blocks
     * @return array{
     *     html: string,
     *     toc: list<array{level: int, text: string, anchor: string}>,
     *     faq_items: list<array{question: string, answer: string}>,
     * }
     */
    public function render(?array $blocks): array
    {
        if ($blocks === null || $blocks === []) {
            return [
                'html' => '',
                'toc' => [],
                'faq_items' => [],
            ];
        }

        $html = '';
        $toc = [];
        $faqItems = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            $rendered = match ($type) {
                'heading' => $this->renderHeading($data, $toc),
                'paragraph' => $this->renderParagraph($data),
                'image' => $this->renderImage($data),
                'callout' => $this->renderCallout($data),
                'faq' => $this->renderFaq($data, $faqItems),
                'quote' => $this->renderQuote($data),
                'list' => $this->renderList($data),
                default => '',
            };

            $html .= $rendered;
        }

        return [
            'html' => $html,
            'toc' => $toc,
            'faq_items' => $faqItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{level: int, text: string, anchor: string}>  $toc
     */
    private function renderHeading(array $data, array &$toc): string
    {
        $level = (int) ($data['level'] ?? 2);
        if (! in_array($level, [2, 3, 4], true)) {
            $level = 2;
        }

        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '') {
            return '';
        }

        $anchor = $this->sanitizeAnchor((string) ($data['anchor'] ?? ''), $text);

        if ($level <= 3) {
            $toc[] = [
                'level' => $level,
                'text' => $text,
                'anchor' => $anchor,
            ];
        }

        $tag = 'h'.$level;

        return sprintf(
            '<%1$s id="%2$s" class="editorial-heading editorial-heading--h%3$d">%4$s</%1$s>',
            $tag,
            e($anchor),
            $level,
            e($text),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderParagraph(array $data): string
    {
        $raw = (string) ($data['html'] ?? $data['text'] ?? '');
        $text = trim(strip_tags($raw));

        if ($text === '') {
            return '';
        }

        return '<p class="editorial-paragraph">'.nl2br(e($text)).'</p>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderImage(array $data): string
    {
        $url = $this->resolveImageUrl($data);
        if ($url === null) {
            return '';
        }

        $alt = e(trim((string) ($data['alt'] ?? $data['alt_text'] ?? '')));
        $caption = trim(strip_tags((string) ($data['caption'] ?? '')));
        $credit = trim(strip_tags((string) ($data['credit'] ?? '')));

        $width = isset($data['width']) ? (int) $data['width'] : null;
        $height = isset($data['height']) ? (int) $data['height'] : null;

        $dimensionAttrs = '';
        if ($width !== null && $width > 0) {
            $dimensionAttrs .= ' width="'.e((string) $width).'"';
        }
        if ($height !== null && $height > 0) {
            $dimensionAttrs .= ' height="'.e((string) $height).'"';
        }

        $html = '<figure class="editorial-figure">';
        $html .= '<img src="'.e($url).'" alt="'.$alt.'" loading="lazy"'.$dimensionAttrs.' class="editorial-image">';

        if ($caption !== '') {
            $html .= '<figcaption class="editorial-figcaption">'.e($caption);
            if ($credit !== '') {
                $html .= ' <span class="editorial-credit">'.e($credit).'</span>';
            }
            $html .= '</figcaption>';
        }

        $html .= '</figure>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderCallout(array $data): string
    {
        $variant = $this->sanitizeCalloutVariant((string) ($data['variant'] ?? 'info'));
        $title = trim(strip_tags((string) ($data['title'] ?? '')));
        $body = trim(strip_tags((string) ($data['html'] ?? $data['text'] ?? '')));

        if ($title === '' && $body === '') {
            return '';
        }

        $html = '<aside class="editorial-callout editorial-callout--'.e($variant).'" role="note">';

        if ($title !== '') {
            $html .= '<p class="editorial-callout__title"><strong>'.e($title).'</strong></p>';
        }

        if ($body !== '') {
            $html .= '<p class="editorial-callout__body">'.nl2br(e($body)).'</p>';
        }

        $html .= '</aside>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{question: string, answer: string}>  $faqItems
     */
    private function renderFaq(array $data, array &$faqItems): string
    {
        $items = $data['items'] ?? [];
        if (! is_array($items) || $items === []) {
            return '';
        }

        $html = '<section class="editorial-faq" aria-label="Domande frequenti">';

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = trim(strip_tags((string) ($item['question'] ?? '')));
            $answer = trim(strip_tags((string) ($item['answer'] ?? '')));

            if ($question === '' || $answer === '') {
                continue;
            }

            $faqItems[] = [
                'question' => $question,
                'answer' => $answer,
            ];

            $html .= '<details class="editorial-faq__item">';
            $html .= '<summary class="editorial-faq__question">'.e($question).'</summary>';
            $html .= '<div class="editorial-faq__answer"><p>'.nl2br(e($answer)).'</p></div>';
            $html .= '</details>';
        }

        $html .= '</section>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderQuote(array $data): string
    {
        $text = trim(strip_tags((string) ($data['text'] ?? $data['html'] ?? '')));
        if ($text === '') {
            return '';
        }

        $cite = trim(strip_tags((string) ($data['cite'] ?? $data['author'] ?? '')));

        $html = '<blockquote class="editorial-quote"><p>'.nl2br(e($text)).'</p>';
        if ($cite !== '') {
            $html .= '<footer>— '.e($cite).'</footer>';
        }
        $html .= '</blockquote>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderList(array $data): string
    {
        $items = $data['items'] ?? [];
        if (! is_array($items) || $items === []) {
            return '';
        }

        $ordered = (bool) ($data['ordered'] ?? ($data['style'] ?? '') === 'ordered');
        $tag = $ordered ? 'ol' : 'ul';

        $html = '<'.$tag.' class="editorial-list">';

        foreach ($items as $item) {
            $text = trim(strip_tags(is_string($item) ? $item : (string) ($item['text'] ?? '')));
            if ($text === '') {
                continue;
            }

            $html .= '<li>'.e($text).'</li>';
        }

        $html .= '</'.$tag.'>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveImageUrl(array $data): ?string
    {
        if (! empty($data['url']) && is_string($data['url'])) {
            return $data['url'];
        }

        if (! empty($data['src']) && is_string($data['src'])) {
            return $data['src'];
        }

        $mediaId = $data['media_id'] ?? null;
        if ($mediaId === null) {
            return null;
        }

        $media = EditorialMedia::query()->find($mediaId);
        if ($media === null || $media->path === null || $media->path === '') {
            return null;
        }

        return Storage::disk($media->disk)->url($media->path);
    }

    private function sanitizeAnchor(string $anchor, string $fallbackText): string
    {
        $anchor = Str::slug($anchor !== '' ? $anchor : $fallbackText);

        return $anchor !== '' ? $anchor : 'section';
    }

    private function sanitizeCalloutVariant(string $variant): string
    {
        return in_array($variant, ['info', 'warning', 'tip', 'danger'], true) ? $variant : 'info';
    }
}
