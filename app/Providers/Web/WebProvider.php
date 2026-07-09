<?php

namespace App\Providers\Web;

use App\Contracts\KnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use League\HTMLToMarkdown\HtmlConverter;

class WebProvider implements KnowledgeProvider
{
    /** @var array<int, string> */
    private array $urls;

    public function __construct(array $urls = [])
    {
        $this->urls = $urls;
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            capabilities: ['search', 'list_resources'],
            searchableResources: ['web_pages'],
            searchableFields: ['url', 'content', 'title'],
            namespace: 'web',
            supportedOperations: ['full_text', 'semantic'],
        );
    }

    public function search(SearchQuery $query): SearchResult
    {
        $results = [];

        foreach ($this->urls as $url) {
            try {
                $content = $this->fetch($url);

                if ($content === '') {
                    continue;
                }

                $matched = $query->query === ''
                    || stripos($content, $query->query) !== false
                    || stripos($url, $query->query) !== false;

                if ($matched) {
                    $excerpt = strlen($content) > 500
                        ? substr($content, 0, 500).'...'
                        : $content;

                    $results[] = [
                        'id' => md5($url),
                        'url' => $url,
                        'content' => $excerpt,
                        'filename' => basename(parse_url($url, PHP_URL_PATH)) ?: $url,
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return new SearchResult(
            items: $results,
            totalCount: count($results),
            providerName: 'web',
        );
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->metadata()->supportedOperations);
    }

    /**
     * @return Collection<int, array{path: string, filename: string, size: int}>
     */
    public function discoverFiles(): Collection
    {
        return collect($this->urls)->map(function ($url) {
            return [
                'path' => $url,
                'filename' => basename($url) ?: $url,
                'size' => 0,
            ];
        });
    }

    /**
     * Fetch the content of a URL and return Markdown-formatted text.
     */
    public function fetch(string $url): string
    {
        try {
            $response = Http::timeout(30)
                ->withUserAgent('AgentoomKnowledge/1.0')
                ->withHeaders(['Accept' => 'text/html, application/xhtml+xml'])
                ->get($url);

            if (! $response->successful()) {
                return '';
            }

            $html = $response->body();
            $html = $this->extractMainContent($html);

            $converter = new HtmlConverter([
                'strip_tags' => true,
                'remove_nodes' => 'nav footer header script style noscript iframe',
                'hard_break' => true,
                'preserve_comments' => false,
            ]);

            $markdown = $converter->convert($html);
            $markdown = $this->normalizeWhitespace($markdown);

            return trim($markdown);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Extract the main content from HTML, removing navigation and boilerplate.
     */
    private function extractMainContent(string $html): string
    {
        $html = preg_replace('#<nav[^>]*>.*?</nav>#is', '', $html) ?? $html;
        $html = preg_replace('#<footer[^>]*>.*?</footer>#is', '', $html) ?? $html;
        $html = preg_replace('#<header[^>]*>.*?</header>#is', '', $html) ?? $html;
        $html = preg_replace('#<aside[^>]*>.*?</aside>#is', '', $html) ?? $html;
        $html = preg_replace('#<script[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<style[^>]*>.*?</style>#is', '', $html) ?? $html;
        $html = preg_replace('#<noscript[^>]*>.*?</noscript>#is', '', $html) ?? $html;
        $html = preg_replace('#<!--.*?-->#s', '', $html) ?? $html;

        return $html;
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
