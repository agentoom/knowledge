<?php

use App\Models\SynonymGroup;
use App\Retrieval\Services\QueryRewriter;
use App\Retrieval\Services\SynonymService;
use App\Settings\Facades\Settings;

beforeEach(function () {
    // Seed some basic synonym groups for testing
    Settings::set('knowledge.synonym_expansion_enabled', true, 'boolean');

    $this->service = app(SynonymService::class);

    // Clean slate
    SynonymGroup::query()->delete();
});

test('rewrites a single matching word', function () {
    $this->service->create(['car', 'automobile', 'vehicle']);
    $rewriter = new QueryRewriter($this->service);

    $result = $rewriter->rewrite('fast car');

    // The original query is preserved; synonym terms are appended.
    // "fast (car OR ...)" → "fast car automobile vehicle"
    expect($result)->toContain('fast')
        ->toContain('car')
        ->toContain('automobile')
        ->toContain('vehicle');
});

test('rewrites multiple matching words independently', function () {
    $this->service->create(['car', 'auto']);
    $this->service->create(['quick', 'fast']);
    $rewriter = new QueryRewriter($this->service);

    $result = $rewriter->rewrite('quick car');

    // Both tokens expand: quick→fast, car→auto
    expect($result)->toContain('quick')
        ->toContain('fast')
        ->toContain('car')
        ->toContain('auto');
});

test('preserves original query when no synonyms match', function () {
    $rewriter = new QueryRewriter($this->service);

    $result = $rewriter->rewrite('unique phrase');

    expect($result)->toBe('unique phrase');
});

test('returns original query when expansion is disabled via settings', function () {
    Settings::set('knowledge.synonym_expansion_enabled', false, 'boolean');
    $this->service->create(['hello', 'hi']);
    $rewriter = new QueryRewriter($this->service);

    $result = $rewriter->rewrite('hello world');

    expect($result)->toBe('hello world');
});

test('returns original query when no synonym groups exist', function () {
    $rewriter = new QueryRewriter($this->service);

    $result = $rewriter->rewrite('any query');

    expect($result)->toBe('any query');
});

test('isEnabled reads from settings', function () {
    Settings::set('knowledge.synonym_expansion_enabled', true, 'boolean');
    $rewriter = new QueryRewriter($this->service);

    expect($rewriter->isEnabled())->toBeTrue();

    Settings::set('knowledge.synonym_expansion_enabled', false, 'boolean');
    $rewriter = new QueryRewriter($this->service);

    expect($rewriter->isEnabled())->toBeFalse();
});

test('expand decomposes the query into original and expansion terms', function () {
    $this->service->create(['car', 'automobile', 'vehicle']);
    $rewriter = new QueryRewriter($this->service);

    $expanded = $rewriter->expand('fast car');

    expect($expanded->original)->toBe('fast car')
        ->and($expanded->rewritten)->toContain('fast')
        ->and($expanded->rewritten)->toContain('automobile')
        ->and($expanded->originalTerms)->toBe(['fast', 'car'])
        ->and($expanded->expansionTerms)->toBe(['automobile', 'vehicle'])
        ->and($expanded->hasExpansion())->toBeTrue();
});

test('expand keeps the original query when disabled or unmatched', function () {
    $rewriter = new QueryRewriter($this->service);

    $expanded = $rewriter->expand('unique phrase');

    expect($expanded->original)->toBe('unique phrase')
        ->and($expanded->rewritten)->toBe('unique phrase')
        ->and($expanded->expansionTerms)->toBe([])
        ->and($expanded->hasExpansion())->toBeFalse();

    Settings::set('knowledge.synonym_expansion_enabled', false, 'boolean');
    $this->service->create(['hello', 'hi']);
    $rewriter = new QueryRewriter($this->service);

    $disabled = $rewriter->expand('hello world');

    expect($disabled->rewritten)->toBe('hello world')
        ->and($disabled->expansionTerms)->toBe([])
        ->and($disabled->hasExpansion())->toBeFalse();
});

test('rewrite delegates to expand', function () {
    $this->service->create(['car', 'automobile']);
    $rewriter = new QueryRewriter($this->service);

    $expanded = $rewriter->expand('fast car');

    expect($rewriter->rewrite('fast car'))->toBe($expanded->rewritten);
});
