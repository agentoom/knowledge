<?php

namespace App\Livewire\Admin\ActivityLogs;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $filterAction = '';

    public string $filterActor = '';

    public bool $showFilters = false;

    public function updatingFilterAction(): void
    {
        $this->resetPage();
    }

    public function updatingFilterActor(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['filterAction', 'filterActor']);
        $this->resetPage();
    }

    public function render(): View
    {
        $query = ActivityLog::query()->with(['user', 'subject']);

        if ($this->filterAction !== '') {
            $query->where('action', $this->filterAction);
        }

        if ($this->filterActor !== '') {
            $term = mb_strtolower(trim($this->filterActor));

            $query->where(function ($q) use ($term) {
                $q->whereHas('user', fn ($userQuery) => $userQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$term.'%']));

                // "system" matches rows written outside an HTTP actor context.
                if ($term === 'system') {
                    $q->orWhereNull('user_id');
                }
            });
        }

        return view('livewire.admin.activity-logs.index', [
            'logs' => $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')->paginate(20),
            // The audit log is append-only and unbounded; a distinct scan on
            // every render would degrade as it grows. Five-minute staleness
            // on an admin filter list is acceptable.
            'actions' => Cache::remember('activity_log_actions', 300, function () {
                return ActivityLog::query()
                    ->distinct()
                    ->orderBy('action')
                    ->pluck('action');
            }),
        ])->layout('layouts.app', ['header' => 'Activity Log']);
    }
}
