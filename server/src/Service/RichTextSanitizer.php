<?php

declare(strict_types=1);
namespace App\Service;

final class RichTextSanitizer
{
    public function sanitize(string $html): string
    {
        if (trim($html) === '') { return ''; }
        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $allowed = ['div', 'p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'a', 'img', 'h2', 'h3', 'blockquote'];
        $root = $document->getElementsByTagName('div')->item(0);
        if (!$root instanceof \DOMElement) { return ''; }
        $this->sanitizeNode($root, $allowed);
        $result = '';
        foreach ($root->childNodes as $child) { $result .= $document->saveHTML($child); }
        return trim($result);
    }

    /** @param list<string> $allowed */
    private function sanitizeNode(\DOMNode $node, array $allowed): void
    {
        for ($index = $node->childNodes->length - 1; $index >= 0; --$index) {
            $child = $node->childNodes->item($index);
            if (!$child instanceof \DOMElement) { continue; }
            if (in_array(strtolower($child->tagName), ['script','style','iframe','object','svg','math'], true)) { $node->removeChild($child); continue; }
            $this->sanitizeNode($child, $allowed);
            if (!in_array($child->tagName, $allowed, true)) {
                while ($child->firstChild) { $node->insertBefore($child->firstChild, $child); }
                $node->removeChild($child); continue;
            }
            $href = $child->tagName === 'a' ? trim($child->getAttribute('href')) : '';
            $src = $child->tagName === 'img' ? trim($child->getAttribute('src')) : '';
            $alt = $child->tagName === 'img' ? trim($child->getAttribute('alt')) : '';
            $width = $child->tagName === 'img' ? filter_var($child->getAttribute('width'), FILTER_VALIDATE_INT) : false;
            $height = $child->tagName === 'img' ? filter_var($child->getAttribute('height'), FILTER_VALIDATE_INT) : false;
            $attributes = [];
            foreach ($child->attributes as $attribute) { $attributes[] = $attribute->name; }
            foreach ($attributes as $attribute) { $child->removeAttribute($attribute); }
            if ($child->tagName === 'a') {
                if ($href !== '' && preg_match('#^(https?://|mailto:)#i', $href)) { $child->setAttribute('href', $href); }
            }
            if ($child->tagName === 'img' && $src !== '' && preg_match('#^https?://#i', $src)) {
                $child->setAttribute('src', $src);
                if ($alt !== '') { $child->setAttribute('alt', $alt); }
                if (is_int($width) && $width >= 120 && $width <= 1400) { $child->setAttribute('width', (string) $width); }
                if (is_int($height) && $height >= 80 && $height <= 1400) { $child->setAttribute('height', (string) $height); }
            }
        }
    }

}
