<?php

namespace App\Livewire\Admin\Chunks;

use App\Knowledge\Models\Chunk;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.admin.chunks.index', [
            'chunks' => Chunk::with('document.knowledgeSource')
                ->orderBy('created_at', 'desc')
                ->paginate(15),
        ])->layout('layouts.app', ['header' => 'Chunks']);
    }
}
