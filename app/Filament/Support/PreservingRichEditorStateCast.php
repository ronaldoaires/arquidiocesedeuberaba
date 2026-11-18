<?php

namespace App\Filament\Support;

use Filament\Forms\Components\RichEditor\StateCasts\RichEditorStateCast;

class PreservingRichEditorStateCast extends RichEditorStateCast
{
    public function set(mixed $state): array
    {
        return $this->normalizeMarks(parent::set($state));
    }

    private function normalizeMarks(array $node): array
    {
        if (isset($node['marks'])) {
            $marks = [];
            foreach ($node['marks'] as $mark) {
                $marks[$mark['type']] ??= $mark;
            }
            $node['marks'] = array_values($marks);
        }
        if (isset($node['content']) && is_array($node['content'])) {
            $node['content'] = array_map(fn (array $child): array => $this->normalizeMarks($child), $node['content']);
        }

        return $node;
    }

    public function get(mixed $state): string|array
    {
        $converted = parent::get(is_array($state) ? $this->normalizeMarks($state) : $state);
        if (! is_string($converted)) {
            return $converted;
        }
        $record = $this->richEditor->getRecord();
        $original = $record?->getRawOriginal($this->richEditor->getName());
        if (! is_string($original) || $original === '') {
            return $converted;
        }
        $normalizedOriginal = parent::get($this->set($original));
        if ($converted === $normalizedOriginal) {
            return $original;
        }
        $originalDocument = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $originalDocument->loadHTML('<?xml encoding="UTF-8"><div>'.$original.'</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        foreach ((new \DOMXPath($originalDocument))->query('//iframe|//audio|//video') as $node) {
            $fragment = $originalDocument->saveHTML($node);
            $source = $node->getAttribute('src');
            if (! str_contains($normalizedOriginal, $fragment) && ($source === '' || ! str_contains($converted, htmlspecialchars($source, ENT_QUOTES | ENT_HTML5)))) {
                $converted .= $fragment;
            }
        }

        return $converted;
    }
}
