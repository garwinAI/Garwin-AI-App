<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use RuntimeException;

final class DeveloperController
{
    public function index(): void
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user['is_admin']) {
            redirect('/admin');
        }

        $guidePath = dirname(__DIR__, 3) . '/docs/developer-platform.md';
        $markdown = file_get_contents($guidePath);
        if (!is_string($markdown)) {
            throw new RuntimeException('Developer documentation could not be loaded.');
        }

        render('developer/index', [
            'title' => 'Developer App',
            'user' => $user,
            'guideHtml' => $this->renderMarkdown($markdown),
        ]);
    }

    private function renderMarkdown(string $markdown): string
    {
        $html = '';
        $paragraph = [];
        $inList = false;
        $inCode = false;
        $codeLines = [];

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (str_starts_with($line, '```')) {
                if ($inCode) {
                    $html .= '<pre><code>' . e(implode("\n", $codeLines)) . '</code></pre>';
                    $codeLines = [];
                    $inCode = false;
                } else {
                    $this->flushParagraph($html, $paragraph);
                    if ($inList) {
                        $html .= '</ul>';
                        $inList = false;
                    }
                    $inCode = true;
                }
                continue;
            }

            if ($inCode) {
                $codeLines[] = $line;
                continue;
            }

            if (preg_match('/\A(#{1,3})\s+(.+)\z/', $line, $heading) === 1) {
                $this->flushParagraph($html, $paragraph);
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $level = strlen($heading[1]);
                $html .= '<h' . $level . '>' . $this->renderInline($heading[2]) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/\A-\s+(.+)\z/', $line, $listItem) === 1) {
                $this->flushParagraph($html, $paragraph);
                if (!$inList) {
                    $html .= '<ul>';
                    $inList = true;
                }
                $html .= '<li>' . $this->renderInline($listItem[1]) . '</li>';
                continue;
            }

            if (trim($line) === '') {
                $this->flushParagraph($html, $paragraph);
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                continue;
            }

            $paragraph[] = trim($line);
        }

        if ($inCode) {
            $html .= '<pre><code>' . e(implode("\n", $codeLines)) . '</code></pre>';
        }
        $this->flushParagraph($html, $paragraph);
        if ($inList) {
            $html .= '</ul>';
        }

        return $html;
    }

    /** @param list<string> $paragraph */
    private function flushParagraph(string &$html, array &$paragraph): void
    {
        if ($paragraph !== []) {
            $html .= '<p>' . $this->renderInline(implode(' ', $paragraph)) . '</p>';
            $paragraph = [];
        }
    }

    private function renderInline(string $text): string
    {
        $parts = preg_split('/(`[^`]+`|\*\*[^*]+\*\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $html = '';
        foreach ($parts as $part) {
            if (str_starts_with($part, '`') && str_ends_with($part, '`')) {
                $html .= '<code>' . e(substr($part, 1, -1)) . '</code>';
            } elseif (str_starts_with($part, '**') && str_ends_with($part, '**')) {
                $html .= '<strong>' . e(substr($part, 2, -2)) . '</strong>';
            } else {
                $html .= e($part);
            }
        }

        return $html;
    }
}
