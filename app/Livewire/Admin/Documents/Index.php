<?php

namespace App\Livewire\Admin\Documents;

use App\Knowledge\Models\Document;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.admin.documents.index', [
            'documents' => Document::with('knowledgeSource')
                ->orderBy('created_at', 'desc')
                ->paginate(15),
        ])->layout('layouts.app', ['header' => 'Documents']);
    }
}
