<?php

use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Livewire\Admin\Documents\Index;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $user = User::factory()->create();
    actingAs($user);
});

test('it renders the documents index page', function () {
    $source = KnowledgeSource::factory()->create(['name' => 'Test Source']);

    Document::factory()
        ->for($source, 'knowledgeSource')
        ->state(['filename' => 'test-doc.pdf', 'status' => 'indexed', 'size_bytes' => 1024])
        ->create();

    Livewire::test(Index::class)
        ->assertSee('Documents')
        ->assertSee('test-doc.pdf')
        ->assertSee('Test Source')
        ->assertSee('indexed');
});

test('it shows empty state when no documents exist', function () {
    Livewire::test(Index::class)
        ->assertSee('No documents found');
});

test('it filters documents by search query', function () {
    $source = KnowledgeSource::factory()->create();

    Document::factory()
        ->for($source, 'knowledgeSource')
        ->state(['filename' => 'report-2024.pdf'])
        ->create();

    Document::factory()
        ->for($source, 'knowledgeSource')
        ->state(['filename' => 'invoice-2024.pdf'])
        ->create();

    Livewire::test(Index::class)
        ->set('search', 'report')
        ->assertSee('report-2024.pdf')
        ->assertDontSee('invoice-2024.pdf');
});

test('it filters documents by knowledge source', function () {
    $sourceA = KnowledgeSource::factory()->create(['name' => 'Source Alpha']);
    $sourceB = KnowledgeSource::factory()->create(['name' => 'Source Beta']);

    Document::factory()
        ->for($sourceA, 'knowledgeSource')
        ->state(['filename' => 'alpha-doc.pdf'])
        ->create();

    Document::factory()
        ->for($sourceB, 'knowledgeSource')
        ->state(['filename' => 'beta-doc.pdf'])
        ->create();

    Livewire::test(Index::class)
        ->set('filterSource', (string) $sourceA->id)
        ->assertSee('alpha-doc.pdf')
        ->assertDontSee('beta-doc.pdf');
});

test('it filters documents by status', function () {
    $source = KnowledgeSource::factory()->create();

    Document::factory()
        ->for($source, 'knowledgeSource')
        ->state(['filename' => 'indexed-doc.pdf', 'status' => 'indexed'])
        ->create();

    Document::factory()
        ->for($source, 'knowledgeSource')
        ->state(['filename' => 'error-doc.pdf', 'status' => 'error'])
        ->create();

    Livewire::test(Index::class)
        ->set('filterStatus', 'error')
        ->assertSee('error-doc.pdf')
        ->assertDontSee('indexed-doc.pdf');
});

test('it resets all filters', function () {
    $source = KnowledgeSource::factory()->create();
    Document::factory()
        ->for($source, 'knowledgeSource')
        ->state(['filename' => 'all-docs.pdf'])
        ->create();

    Livewire::test(Index::class)
        ->set('search', 'nonexistent')
        ->set('filterStatus', 'error')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('filterStatus', '')
        ->assertSet('filterSource', '')
        ->assertSee('all-docs.pdf');
});

test('it toggles filter panel', function () {
    Livewire::test(Index::class)
        ->assertSet('showFilters', false)
        ->set('showFilters', true)
        ->assertSet('showFilters', true);
});
