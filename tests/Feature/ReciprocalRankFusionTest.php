<?php

use App\Retrieval\Fusion\ReciprocalRankFusion;
use App\Retrieval\Models\SearchResult;

test('fuses two result sets with correct RRF ordering', function () {
    $fusion = new ReciprocalRankFusion;

    $result1 = new SearchResult(
        items: [
            ['id' => 'a', 'title' => 'Alpha'],
            ['id' => 'b', 'title' => 'Beta'],
            ['id' => 'c', 'title' => 'Gamma'],
        ],
        totalCount: 3,
        providerName: 'provider1',
    );

    $result2 = new SearchResult(
        items: [
            ['id' => 'b', 'title' => 'Beta'],
            ['id' => 'd', 'title' => 'Delta'],
            ['id' => 'a', 'title' => 'Alpha'],
        ],
        totalCount: 3,
        providerName: 'provider2',
    );

    $fused = $fusion->fuse([$result1, $result2]);

    expect($fused)->toHaveCount(4)
        ->and($fused[0]['id'])->toBe('b')   // b appears at rank 1 in p1 + rank 0 in p2 → highest combined score
        ->and($fused[1]['id'])->toBe('a')   // a at rank 0 in p1 + rank 2 in p2
        ->and($fused[2]['id'])->toBe('d')   // d at rank 1 in p2
        ->and($fused[3]['id'])->toBe('c');  // c only in p1 at rank 2
});

test('deduplication preserves first occurrence', function () {
    $fusion = new ReciprocalRankFusion;

    $result1 = new SearchResult(
        items: [
            ['id' => 'x', 'title' => 'First Occurrence', 'score' => 10],
        ],
        totalCount: 1,
        providerName: 'provider1',
    );

    $result2 = new SearchResult(
        items: [
            ['id' => 'x', 'title' => 'Second Occurrence', 'score' => 5],
        ],
        totalCount: 1,
        providerName: 'provider2',
    );

    $fused = $fusion->fuse([$result1, $result2]);

    expect($fused)->toHaveCount(1)
        ->and($fused[0]['title'])->toBe('First Occurrence')
        ->and($fused[0]['score'])->toBe(10);
});

test('handles items without id via md5 fallback', function () {
    $fusion = new ReciprocalRankFusion;

    $result1 = new SearchResult(
        items: [
            ['title' => 'No ID Here'],
        ],
        totalCount: 1,
        providerName: 'provider1',
    );

    $result2 = new SearchResult(
        items: [
            ['title' => 'Different No ID'],
        ],
        totalCount: 1,
        providerName: 'provider2',
    );

    $fused = $fusion->fuse([$result1, $result2]);

    // Both items have different content → different md5 → both preserved
    expect($fused)->toHaveCount(2);
});

test('handles empty result sets gracefully', function () {
    $fusion = new ReciprocalRankFusion;

    $result1 = new SearchResult(
        items: [],
        totalCount: 0,
        providerName: 'empty1',
    );

    $result2 = new SearchResult(
        items: [],
        totalCount: 0,
        providerName: 'empty2',
    );

    $fused = $fusion->fuse([$result1, $result2]);

    expect($fused)->toBeEmpty();
});

test('handles single result set', function () {
    $fusion = new ReciprocalRankFusion;

    $result = new SearchResult(
        items: [
            ['id' => '1', 'title' => 'One'],
            ['id' => '2', 'title' => 'Two'],
            ['id' => '3', 'title' => 'Three'],
        ],
        totalCount: 3,
        providerName: 'solo',
    );

    $fused = $fusion->fuse([$result]);

    expect($fused)->toHaveCount(3)
        ->and($fused[0]['id'])->toBe('1')
        ->and($fused[1]['id'])->toBe('2')
        ->and($fused[2]['id'])->toBe('3');
});

test('skips result objects with missing or invalid items', function () {
    $fusion = new ReciprocalRankFusion;

    $validResult = new SearchResult(
        items: [['id' => 'a', 'title' => 'Valid']],
        totalCount: 1,
        providerName: 'valid',
    );

    // Create a result-like object with no items property
    $invalidResult = new class
    {
        public string $providerName = 'broken';
    };

    $fused = $fusion->fuse([$validResult, $invalidResult]);

    expect($fused)->toHaveCount(1)
        ->and($fused[0]['id'])->toBe('a');
});

test('computes correct RRF scores', function () {
    $fusion = new ReciprocalRankFusion;

    // Single provider: item at rank 0 → score = 1/(0+60) = 1/60 ≈ 0.01667
    // Item at rank 1 → score = 1/(1+60) = 1/61 ≈ 0.01639
    $result = new SearchResult(
        items: [
            ['id' => 'first', 'title' => 'First'],
            ['id' => 'second', 'title' => 'Second'],
        ],
        totalCount: 2,
        providerName: 'test',
    );

    $fused = $fusion->fuse([$result]);

    // Higher score (rank 0) should come first
    expect($fused)->toHaveCount(2)
        ->and($fused[0]['id'])->toBe('first')
        ->and($fused[1]['id'])->toBe('second');
});

test('name returns reciprocal_rank_fusion', function () {
    $fusion = new ReciprocalRankFusion;

    expect($fusion->name())->toBe('reciprocal_rank_fusion');
});

test('merges array-valued metadata from duplicate items', function () {
    $fusion = new ReciprocalRankFusion;

    $result1 = new SearchResult(
        items: [
            ['id' => 'doc-1', 'title' => 'Same Doc', 'tags' => ['server-a']],
        ],
        totalCount: 1,
        providerName: 'provider1',
    );

    $result2 = new SearchResult(
        items: [
            ['id' => 'doc-1', 'title' => 'Same Doc From B', 'tags' => ['server-b'], '_federation_source' => 'remote'],
        ],
        totalCount: 1,
        providerName: 'provider2',
    );

    $fused = $fusion->fuse([$result1, $result2]);

    expect($fused)->toHaveCount(1)
        ->and($fused[0]['title'])->toBe('Same Doc')                 // first occurrence wins for scalars
        ->and($fused[0]['tags'])->toBe(['server-a', 'server-b'])    // arrays are merged
        ->and($fused[0]['_federation_source'])->toBe('remote');     // new key from duplicate is added
});

test('adds keys from duplicate not present in first occurrence', function () {
    $fusion = new ReciprocalRankFusion;

    $result1 = new SearchResult(
        items: [
            ['id' => 'shared', 'name' => 'Primary'],
        ],
        totalCount: 1,
        providerName: 'p1',
    );

    $result2 = new SearchResult(
        items: [
            ['id' => 'shared', 'name' => 'Secondary', 'extra_field' => 42, 'another' => 'value'],
        ],
        totalCount: 1,
        providerName: 'p2',
    );

    $fused = $fusion->fuse([$result1, $result2]);

    expect($fused)->toHaveCount(1)
        ->and($fused[0]['name'])->toBe('Primary')          // first occurrence wins
        ->and($fused[0]['extra_field'])->toBe(42)           // new scalar from duplicate
        ->and($fused[0]['another'])->toBe('value');         // new scalar from duplicate
});

test('deduplication preserves first occurrence for scalar fields (unchanged)', function () {
    $fusion = new ReciprocalRankFusion;

    $result1 = new SearchResult(
        items: [
            ['id' => 'x', 'title' => 'First Occurrence', 'score' => 10],
        ],
        totalCount: 1,
        providerName: 'provider1',
    );

    $result2 = new SearchResult(
        items: [
            ['id' => 'x', 'title' => 'Second Occurrence', 'score' => 5],
        ],
        totalCount: 1,
        providerName: 'provider2',
    );

    $fused = $fusion->fuse([$result1, $result2]);

    // First occurrence wins for scalar fields; score still accumulates in ranking
    expect($fused)->toHaveCount(1)
        ->and($fused[0]['title'])->toBe('First Occurrence')
        ->and($fused[0]['score'])->toBe(10);
});
