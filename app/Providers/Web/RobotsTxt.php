<?php

namespace App\Providers\Web;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RobotsTxt
{
    /** @var array<string, array<int, string>> */
    private array $cache = [];

    /**
     * Check if a URL is allowed to be crawled by parsing the domain's robots.txt.
     */
    public function isAllowed(string $url, string $userAgent = 'AgentoomKnowledge'): bool
    {
        $domain = parse_url($url, PHP_URL_HOST);

        if ($domain === false || $domain === null) {
            return false;
        }

        $rules = $this->fetchRules($domain);

        if ($rules === []) {
            return true; // No robots.txt = allowed
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        // Check rules for our user agent first, then fall back to wildcard
        $disallowedPaths = array_merge(
            $rules[$userAgent] ?? [],
            $rules['*'] ?? [],
        );

        foreach ($disallowedPaths as $disallowed) {
            if ($disallowed === '/' || $disallowed === '' || $disallowed === '0') {
                return false;
            }

            if (str_starts_with($path, $disallowed)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fetch and parse robots.txt for a domain.
     *
     * @return array<string, array<int, string>> user-agent → disallowed paths
     */
    private function fetchRules(string $domain): array
    {
        if (isset($this->cache[$domain])) {
            /** @var array<string, array<int, string>> $result */
            $result = $this->cache[$domain];

            return $result;
        }

        try {
            $response = Http::timeout(10)
                ->withUserAgent('AgentoomKnowledge/1.0')
                ->get("http://{$domain}/robots.txt");

            if (! $response->successful()) {
                $this->cache[$domain] = [];

                return [];
            }

            $rules = $this->parse($response->body());
            $this->cache[$domain] = $rules;

            return $rules;
        } catch (\Throwable $e) {
            Log::debug('Failed to fetch robots.txt.', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            $this->cache[$domain] = [];

            return [];
        }
    }

    /**
     * Parse robots.txt content into a structured rules array.
     *
     * @return array<string, array<int, string>>
     */
    private function parse(string $content): array
    {
        $rules = [];
        $currentAgent = null;
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if ($line === '' || $line === '0' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse "User-agent: xxx"
            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches)) {
                $currentAgent = trim($matches[1]);
                if (! isset($rules[$currentAgent])) {
                    $rules[$currentAgent] = [];
                }

                continue;
            }

            // Parse "Disallow: /path"
            if ($currentAgent !== null && preg_match('/^Disallow:\s*(.*)$/i', $line, $matches)) {
                $path = trim($matches[1]);
                $rules[$currentAgent][] = $path;
            }
        }

        return $rules;
    }
}
