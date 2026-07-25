<?php

namespace App\Livewire\Admin\Chunks;

use App\Knowledge\Models\Chunk;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterSource = '';

    public string $filterIndexed = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public bool $showFilters = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSource(): void
    {
        $this->resetPage();
    }

    public function updatingFilterIndexed(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterSource', 'filterIndexed']);
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Chunk::with('document.knowledgeSource')
            ->leftJoin('documents', 'chunks.document_id', '=', 'documents.id')
            ->select('chunks.*');

        if ($this->search !== '') {
            $query->where('documents.filename', 'like', '%'.$this->search.'%');
        }

        if ($this->filterSource !== '') {
            $query->where('documents.knowledge_source_id', (int) $this->filterSource);
        }

        if ($this->filterIndexed !== '') {
            if ($this->filterIndexed === 'yes') {
                $query->whereNotNull('chunks.indexed_at');
            } else {
                $query->whereNull('chunks.indexed_at');
            }
        }

        $sortColumn = match ($this->sortField) {
            'filename' => 'documents.filename',
            default => 'chunks.'.$this->sortField,
        };

        return view('livewire.admin.chunks.index', [
            'chunks' => $query->orderBy($sortColumn, $this->sortDirection)->paginate(15),
            'sources' => KnowledgeSource::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['header' => 'Chunks']);
    }
}
