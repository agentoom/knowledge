<?php

use App\Knowledge\Models\Chunk;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Livewire\Admin\Chunks\Index;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $user = User::factory()->create();
    actingAs($user);
});

test('it renders the chunks index page', function () {
    $source = KnowledgeSource::factory()->create(['name' => 'Test Source']);
    $document = Document::factory()
        ->for($source, 'knowledgeSource')
        ->state(['filename' => 'chunkable-doc.pdf'])
        ->create();

    Chunk::factory()
        ->for($document, 'document')
        ->state(['sequence' => 1, 'token_count' => 250, 'indexed_at' => now()])
        ->create();

    Livewire::test(Index::class)
        ->assertSee('Chunks')
        ->assertSee('chunkable-doc.pdf')
        ->assertSee('Test Source')
        ->assertSee('#1')
        ->assertSee('Indexed');
});

test('it shows empty state when no chunks exist', function () {
    Livewire::test(Index::class)
        ->assertSee('No chunks found');
});

test('it shows pending badge for unindexed chunks', function () {
    $document = Document::factory()
        ->for(KnowledgeSource::factory(), 'knowledgeSource')
        ->create();

    Chunk::factory()
        ->for($document, 'document')
        ->state(['indexed_at' => null])
        ->create();

    Livewire::test(Index::class)
        ->assertSee('Pending');
});

test('it filters chunks by indexing status', function () {
    $document = Document::factory()
        ->for(KnowledgeSource::factory(), 'knowledgeSource')
        ->create();

    Chunk::factory()
        ->for($document, 'document')
        ->state(['indexed_at' => now()])
        ->create();

    Chunk::factory()
        ->for($document, 'document')
        ->state(['indexed_at' => null])
        ->create();

    Livewire::test(Index::class)
        ->set('filterIndexed', 'yes')
        ->assertSee('Indexed')
        ->assertDontSee('Pending');
});

test('it filters chunks by knowledge source', function () {
    $sourceA = KnowledgeSource::factory()->create(['name' => 'Source Alpha']);
    $sourceB = KnowledgeSource::factory()->create(['name' => 'Source Beta']);

    $docA = Document::factory()
        ->for($sourceA, 'knowledgeSource')
        ->create();
    $docB = Document::factory()
        ->for($sourceB, 'knowledgeSource')
        ->create();

    Chunk::factory()->for($docA, 'document')->create();
    Chunk::factory()->for($docB, 'document')->create();

    Livewire::test(Index::class)
        ->set('filterSource', (string) $sourceA->id)
        ->assertSee('Source Alpha')
        ->assertDontSee('Source Beta');
});

test('it searches chunks by document filename', function () {
    $source = KnowledgeSource::factory()->create();

    $doc1 = Document::factory()
        ->for($source, 'knowledgeSource')
        ->state(['filename' => 'quarterly-report.pdf'])
        ->create();

    $doc2 = Document::factory()
        ->for($source, 'knowledgeSource')
        ->state(['filename' => 'annual-plan.pdf'])
        ->create();

    Chunk::factory()->for($doc1, 'document')->create();
    Chunk::factory()->for($doc2, 'document')->create();

    Livewire::test(Index::class)
        ->set('search', 'report')
        ->assertSee('quarterly-report.pdf')
        ->assertDontSee('annual-plan.pdf');
});

test('it resets all filters', function () {
    Document::factory()
        ->for(KnowledgeSource::factory(), 'knowledgeSource')
        ->has(Chunk::factory(), 'chunks')
        ->create();

    Livewire::test(Index::class)
        ->set('search', 'nonexistent')
        ->set('filterIndexed', 'no')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('filterIndexed', '')
        ->assertSet('filterSource', '');
});

test('it toggles filter panel', function () {
    Livewire::test(Index::class)
        ->assertSet('showFilters', false)
        ->set('showFilters', true)
        ->assertSet('showFilters', true);
});
