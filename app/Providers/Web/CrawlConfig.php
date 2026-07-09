<?php

namespace App\Providers\Web;

class CrawlConfig
{
    /**
     * @param  array<int, string>  $seedUrls
     * @param  array<int, string>  $allowedDomains
     * @param  array<int, string>  $excludePatterns
     */
    public function __construct(
        public readonly array $seedUrls = [],
        public readonly int $maxDepth = 3,
        public readonly int $maxPages = 100,
        public readonly int $politenessDelayMs = 1000,
        public readonly array $allowedDomains = [],
        public readonly bool $respectRobotsTxt = true,
        public readonly array $excludePatterns = [],
    ) {}

    public static function fromConfig(?array $config): self
    {
        if ($config === null || $config === []) {
            return new self;
        }

        return new self(
            seedUrls: array_filter(array_map('trim', $config['urls'] ?? [])),
            maxDepth: (int) ($config['crawl_max_depth'] ?? 3),
            maxPages: (int) ($config['crawl_max_pages'] ?? 100),
            politenessDelayMs: (int) ($config['crawl_politeness_ms'] ?? 1000),
            allowedDomains: array_filter(array_map('trim', $config['crawl_allowed_domains'] ?? [])),
            respectRobotsTxt: (bool) ($config['crawl_respect_robots_txt'] ?? true),
            excludePatterns: array_filter(array_map('trim', $config['crawl_exclude_patterns'] ?? [])),
        );
    }

    public function isCrawlEnabled(): bool
    {
        return $this->maxDepth > 0 && $this->seedUrls !== [];
    }

    public function getBaseDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === false || $host === null) {
            return '';
        }

        // Strip 'www.' prefix for consistent comparison
        return str_starts_with($host, 'www.')
            ? substr($host, 4)
            : $host;
    }

    public function isDomainAllowed(string $url): bool
    {
        if ($this->allowedDomains === []) {
            return true;
        }

        $domain = $this->getBaseDomain($url);

        return in_array($domain, $this->allowedDomains, true);
    }

    public function isExcluded(string $url): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (str_contains($url, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
