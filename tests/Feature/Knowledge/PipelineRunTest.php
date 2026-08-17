<?php

use App\Jobs\DocumentPipeline\DiscoverDocuments;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

test('pipeline run command with no sources exits gracefully', function () {
    $this->artisan('knowledge:pipeline:run')
        ->expectsOutput('No active knowledge sources found.')
        ->assertExitCode(0);
});

test('pipeline run command dispatches batch for active sources', function () {
    Bus::fake();

    KnowledgeSource::create([
        'name' => 'Test Source',
        'slug' => 'test-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
        'is_active' => true,
    ]);

    $this->artisan('knowledge:pipeline:run')
        ->expectsOutput('Running document pipeline for 1 knowledge source(s)...')
        ->assertExitCode(0);

    Bus::assertBatched(function (PendingBatch $batch) {
        return $batch->hasJobs([
            fn (DiscoverDocuments $job) => true,
        ]);
    });
});

test('pipeline run command skips inactive sources', function () {
    Bus::fake();

    KnowledgeSource::create([
        'name' => 'Inactive Source',
        'slug' => 'inactive-source',
        'provider_type' => 'filesystem',
        'namespace' => 'inactive',
        'is_active' => false,
    ]);

    $this->artisan('knowledge:pipeline:run')
        ->expectsOutput('No active knowledge sources found.')
        ->assertExitCode(0);

    Bus::assertNothingBatched();
});

test('pipeline run with specific source by id', function () {
    Bus::fake();

    $source = KnowledgeSource::create([
        'name' => 'Source 1',
        'slug' => 'source-1',
        'provider_type' => 'filesystem',
        'namespace' => 'one',
        'is_active' => true,
    ]);

    KnowledgeSource::create([
        'name' => 'Source 2',
        'slug' => 'source-2',
        'provider_type' => 'filesystem',
        'namespace' => 'two',
        'is_active' => true,
    ]);

    $this->artisan('knowledge:pipeline:run', ['source' => (string) $source->id])
        ->assertExitCode(0);

    // Source creation dispatches its own pipeline batch through the observer,
    // so assert the command scoped its batch to the requested source instead
    // of asserting an exact global batch count.
    Bus::assertBatched(function (PendingBatch $batch) use ($source) {
        return $batch->name === "Pipeline: {$source->name}";
    });
});

test('pipeline run with invalid source shows error', function () {
    $this->artisan('knowledge:pipeline:run', ['source' => '999'])
        ->expectsOutput("No active knowledge source found matching '999'.")
        ->assertExitCode(1);
});
