<?php

namespace App\Livewire\Admin\Documents;

use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterSource = '';

    public string $filterStatus = '';

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

    public function updatingFilterStatus(): void
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
        $this->reset(['search', 'filterSource', 'filterStatus']);
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Document::with('knowledgeSource');

        if ($this->search !== '') {
            $query->where('filename', 'like', '%'.$this->search.'%');
        }

        if ($this->filterSource !== '') {
            $query->where('knowledge_source_id', (int) $this->filterSource);
        }

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        return view('livewire.admin.documents.index', [
            'documents' => $query->orderBy($this->sortField, $this->sortDirection)->paginate(15),
            'sources' => KnowledgeSource::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['header' => 'Documents']);
    }
}
