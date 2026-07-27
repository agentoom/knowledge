<?php

namespace App\Livewire\Admin\KnowledgeSources;

use App\Knowledge\Enums\ProviderType;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public bool $showSourceTypesHelp = false;

    public function delete(int $id): void
    {
        KnowledgeSource::findOrFail($id)->delete();
        session()->flash('status', 'Knowledge source deleted.');
    }

    public function toggleActive(int $id): void
    {
        $source = KnowledgeSource::findOrFail($id);
        $source->update(['is_active' => ! $source->is_active]);
        session()->flash('status', 'Knowledge source status updated.');
    }

    public function providerTypeLabel(string $type): string
    {
        $pt = ProviderType::tryFrom($type);

        return $pt?->label() ?? ucfirst($type);
    }

    public function render(): View
    {
        return view('livewire.admin.knowledge-sources.index', [
            'sources' => KnowledgeSource::orderBy('name')->paginate(15),
        ])->layout('layouts.app', ['header' => 'Knowledge Sources']);
    }
}
