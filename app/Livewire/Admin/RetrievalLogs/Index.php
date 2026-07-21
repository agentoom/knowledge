<?php

namespace App\Livewire\Admin\RetrievalLogs;

use App\Models\RetrievalLog;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public ?RetrievalLog $selectedLog = null;

    public bool $showDetailModal = false;

    public function viewDetails(int $id): void
    {
        $this->selectedLog = RetrievalLog::find($id);
        $this->showDetailModal = true;
    }

    public function render(): View
    {
        return view('livewire.admin.retrieval-logs.index', [
            'logs' => RetrievalLog::latest()->paginate(20),
        ])->layout('layouts.app', ['header' => 'Retrieval Logs']);
    }
}
