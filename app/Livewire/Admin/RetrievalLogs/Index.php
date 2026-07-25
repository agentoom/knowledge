<?php

namespace App\Livewire\Admin\RetrievalLogs;

use App\Models\RetrievalLog;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterNamespace = '';

    public ?int $filterLatencyMin = null;

    public ?int $filterLatencyMax = null;

    public ?string $filterDateFrom = null;

    public ?string $filterDateTo = null;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public ?RetrievalLog $selectedLog = null;

    public bool $showDetailModal = false;

    public bool $showFilters = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterNamespace(): void
    {
        $this->resetPage();
    }

    public function updatingFilterLatencyMin(): void
    {
        $this->resetPage();
    }

    public function updatingFilterLatencyMax(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDateTo(): void
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
        $this->reset(['search', 'filterNamespace', 'filterLatencyMin', 'filterLatencyMax', 'filterDateFrom', 'filterDateTo']);
        $this->resetPage();
    }

    public function viewDetails(int $id): void
    {
        $this->selectedLog = RetrievalLog::find($id);
        $this->showDetailModal = true;
    }

    public function render(): View
    {
        $query = RetrievalLog::query();

        if ($this->search !== '') {
            $query->where('query', 'ilike', '%'.$this->search.'%');
        }

        if ($this->filterNamespace !== '') {
            $query->where('metadata->namespace', $this->filterNamespace);
        }

        if ($this->filterLatencyMin !== null) {
            $query->where('latency_ms', '>=', $this->filterLatencyMin);
        }

        if ($this->filterLatencyMax !== null) {
            $query->where('latency_ms', '<=', $this->filterLatencyMax);
        }

        if ($this->filterDateFrom !== null && $this->filterDateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo !== null && $this->filterDateTo !== '') {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }

        return view('livewire.admin.retrieval-logs.index', [
            'logs' => $query->orderBy($this->sortField, $this->sortDirection)->paginate(20),
            'namespaces' => RetrievalLog::query()
                ->whereNotNull('metadata->namespace')
                ->selectRaw("DISTINCT metadata->>'namespace' as namespace")
                ->orderBy('namespace')
                ->pluck('namespace'),
        ])->layout('layouts.app', ['header' => 'Retrieval Logs']);
    }
}
