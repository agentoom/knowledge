<?php

namespace App\Jobs\DocumentPipeline;

use App\Knowledge\Models\KnowledgeSource;
use App\Providers\Web\CrawlConfig;
use App\Providers\Web\RobotsTxt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use League\HTMLToMarkdown\HtmlConverter;

class CrawlWebSource implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $urls
     */
    public int $pagesProcessed;

    public function __construct(
        public readonly int $knowledgeSourceId,
        public readonly array $urls,
        public readonly int $depth = 0,
        int $pagesProcessed = 0,
    ) {
        $this->pagesProcessed = $pagesProcessed;
    }

    public function handle(): void
    {
        $source = KnowledgeSource::findOrFail($this->knowledgeSourceId);

        $config = CrawlConfig::fromConfig($source->provider_config);

        if (! $config->isCrawlEnabled()) {
            return;
        }

        $robots = new RobotsTxt;

        foreach ($this->urls as $url) {
            if ($this->pagesProcessed >= $config->maxPages) {
                Log::info('CrawlWebSource: max pages reached.', [
                    'source_id' => $source->id,
                    'pages' => $this->pagesProcessed,
                ]);

                break;
            }

            if ($config->isExcluded($url)) {
                continue;
            }

            if ($config->respectRobotsTxt && ! $robots->isAllowed($url)) {
                Log::debug('CrawlWebSource: URL disallowed by robots.txt.', ['url' => $url]);

                continue;
            }

            $this->fetchAndStore($url, $source);
        }

        // Dispatch next depth level
        if ($this->depth < $config->maxDepth && $this->pagesProcessed < $config->maxPages) {
            $discoveredUrls = $this->discoverLinks($this->urls);

            if ($discoveredUrls !== []) {
                static::dispatch(
                    knowledgeSourceId: $source->id,
                    urls: $discoveredUrls,
                    depth: $this->depth + 1,
                    pagesProcessed: $this->pagesProcessed,
                )->delay(now()->addMilliseconds($config->politenessDelayMs));
            }
        }
    }

    private function fetchAndStore(string $url, KnowledgeSource $source): void
    {
        try {
            $response = Http::timeout(30)
                ->withUserAgent('AgentoomKnowledge/1.0')
                ->withHeaders(['Accept' => 'text/html, application/xhtml+xml'])
                ->get($url);

            if (! $response->successful()) {
                return;
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
            $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown) ?? $markdown;

            $source->documents()->updateOrCreate(
                ['path' => $url],
                [
                    'filename' => basename(parse_url($url, PHP_URL_PATH)) ?: $url,
                    'mime_type' => 'text/html',
                    'size_bytes' => strlen($markdown),
                    'content' => trim($markdown),
                    'status' => 'parsed',
                    'parsed_at' => now(),
                    'metadata' => [
                        'source_url' => $url,
                        'crawl_depth' => $this->depth,
                    ],
                ]
            );

            $this->pagesProcessed++;
        } catch (\Throwable $e) {
            Log::debug('CrawlWebSource: failed to fetch URL.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract links from previously fetched pages for the next depth level.
     *
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function discoverLinks(array $urls): array
    {
        $config = CrawlConfig::fromConfig(
            KnowledgeSource::find($this->knowledgeSourceId)?->provider_config
        );

        $discovered = [];
        $robots = new RobotsTxt;

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(15)
                    ->withUserAgent('AgentoomKnowledge/1.0')
                    ->get($url);

                if (! $response->successful()) {
                    continue;
                }

                preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"#][^"]*)"/i', $response->body(), $matches);

                foreach ($matches[1] as $link) {
                    $absoluteUrl = $this->resolveUrl($link, $url);

                    if ($absoluteUrl === null) {
                        continue;
                    }

                    if (! $config->isDomainAllowed($absoluteUrl)) {
                        continue;
                    }

                    if ($config->isExcluded($absoluteUrl)) {
                        continue;
                    }

                    if ($config->respectRobotsTxt && ! $robots->isAllowed($absoluteUrl)) {
                        continue;
                    }

                    $discovered[$absoluteUrl] = true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return array_keys($discovered);
    }

    private function resolveUrl(string $link, string $baseUrl): ?string
    {
        $parsed = parse_url($link);

        if ($parsed === false) {
            return null;
        }

        // Already absolute
        if (isset($parsed['scheme'])) {
            if (! in_array($parsed['scheme'], ['http', 'https'], true)) {
                return null;
            }

            return $link;
        }

        // Relative URL: resolve against base
        $base = parse_url($baseUrl);

        if ($base === false || ! isset($base['scheme'], $base['host'])) {
            return null;
        }

        $scheme = $base['scheme'];
        $host = $base['host'];
        $path = $base['path'] ?? '/';

        if (str_starts_with($link, '/')) {
            return "{$scheme}://{$host}{$link}";
        }

        $dir = dirname($path);

        return "{$scheme}://{$host}".rtrim($dir, '/').'/'.$link;
    }

    private function extractMainContent(string $html): string
    {
        $html = preg_replace('#<nav[^>]*>.*?</nav>#is', '', $html) ?? $html;
        $html = preg_replace('#<footer[^>]*>.*?</footer>#is', '', $html) ?? $html;
        $html = preg_replace('#<header[^>]*>.*?</header>#is', '', $html) ?? $html;
        $html = preg_replace('#<aside[^>]*>.*?</aside>#is', '', $html) ?? $html;
        $html = preg_replace('#<script[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<style[^>]*>.*?</style>#is', '', $html) ?? $html;

        return preg_replace('#<!--.*?-->#s', '', $html) ?? $html;
    }
}
