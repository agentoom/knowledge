<?php

namespace App\DocumentPipeline\Services;

use App\Contracts\ChunkingStrategy;
use App\DocumentPipeline\Chunking\FixedSizeChunking;
use App\DocumentPipeline\Chunking\MarkdownChunking;
use App\DocumentPipeline\Chunking\SemanticChunking;
use App\DocumentPipeline\Chunking\SlidingWindowChunking;

class ChunkingStrategyRegistry
{
    /**
     * Resolve the best chunking strategy for a given document.
     *
     * Selection logic:
     * - Markdown → heading-aware MarkdownChunking
     * - Plain text / prose → paragraph-aware SemanticChunking
     * - Code / structured data → SlidingWindowChunking (overlapping context)
     * - Everything else → FixedSizeChunking (safe fallback)
     */
    public function resolve(string $mimeType, string $filename): ChunkingStrategy
    {
        if ($this->isMarkdown($mimeType, $filename)) {
            return new MarkdownChunking;
        }

        if ($this->isPlainText($mimeType, $filename)) {
            return new SemanticChunking;
        }

        if ($this->isCode($mimeType, $filename)) {
            return new SlidingWindowChunking(windowSize: 1500, stride: 1000);
        }

        return new FixedSizeChunking(chunkSize: 1000);
    }

    /**
     * Get all available strategy names for UI/admin display.
     *
     * @return array<int, string>
     */
    public function available(): array
    {
        return ['markdown', 'semantic', 'sliding_window', 'fixed_size'];
    }

    private function isMarkdown(string $mimeType, string $filename): bool
    {
        return str_contains($mimeType, 'markdown')
            || str_ends_with($filename, '.md')
            || str_ends_with($filename, '.mdx');
    }

    private function isPlainText(string $mimeType, string $filename): bool
    {
        $textTypes = ['text/plain', 'text/html', 'application/pdf', 'application/msword'];
        $textExtensions = ['.txt', '.html', '.htm', '.rst', '.adoc', '.tex'];

        if (in_array($mimeType, $textTypes, true)) {
            return true;
        }

        foreach ($textExtensions as $ext) {
            if (str_ends_with($filename, $ext)) {
                return true;
            }
        }

        return false;
    }

    private function isCode(string $mimeType, string $filename): bool
    {
        $codeExtensions = [
            '.php', '.js', '.ts', '.jsx', '.tsx', '.py', '.rb', '.go',
            '.rs', '.java', '.c', '.cpp', '.h', '.cs', '.swift', '.kt',
            '.scala', '.sh', '.bash', '.zsh', '.sql', '.xml', '.json',
            '.yaml', '.yml', '.toml', '.ini', '.cfg', '.conf', '.env',
        ];

        foreach ($codeExtensions as $ext) {
            if (str_ends_with($filename, $ext)) {
                return true;
            }
        }

        $codeMimes = ['application/json', 'application/xml', 'text/xml',
            'application/x-yaml', 'text/yaml', 'application/x-httpd-php',
            'text/x-python', 'text/x-go', 'text/x-rust',
        ];

        return in_array($mimeType, $codeMimes, true);
    }
}
