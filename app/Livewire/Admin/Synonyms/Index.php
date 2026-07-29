<?php

namespace App\Livewire\Admin\Synonyms;

use App\Models\SynonymGroup;
use App\Retrieval\Services\SynonymService;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $newWords = '';

    public ?int $editingId = null;

    public string $editingWords = '';

    protected function rules(): array
    {
        return [
            'newWords' => ['required', 'string', 'min:3'],
            'editingWords' => ['required', 'string', 'min:3'],
        ];
    }

    public function create(SynonymService $synonymService): void
    {
        $this->validateOnly('newWords');

        $words = $this->parseWords($this->newWords);

        if (count($words) < 2) {
            $this->addError('newWords', 'Enter at least two comma-separated words.');

            return;
        }

        $synonymService->create($words);
        $this->newWords = '';
        $this->dispatch('notify', message: 'Synonym group created.');
    }

    public function startEdit(SynonymGroup $group): void
    {
        $this->editingId = $group->id;
        $this->editingWords = implode(', ', $group->words ?? []);
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editingWords = '';
    }

    public function update(SynonymService $synonymService): void
    {
        $this->validateOnly('editingWords');

        $words = $this->parseWords($this->editingWords);

        if (count($words) < 2) {
            $this->addError('editingWords', 'Enter at least two comma-separated words.');

            return;
        }

        $synonymService->update($this->editingId, $words);
        $this->editingId = null;
        $this->editingWords = '';
        $this->dispatch('notify', message: 'Synonym group updated.');
    }

    public function delete(int $id, SynonymService $synonymService): void
    {
        $synonymService->delete($id);
        $this->dispatch('notify', message: 'Synonym group deleted.');
    }

    /**
     * Parse comma- or space-separated words into a clean array.
     *
     * @return array<int, string>
     */
    private function parseWords(string $input): array
    {
        // Split by comma first, then trim each part
        $parts = explode(',', $input);
        $words = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '' || $part === '0') {
                continue;
            }

            // Handle space-separated entries too
            foreach (preg_split('/\s+/', $part) as $word) {
                $word = trim($word);

                if ($word !== '' && $word !== '0') {
                    $words[] = $word;
                }
            }
        }

        return array_values(array_unique($words));
    }

    public function render(): View
    {
        $groups = SynonymGroup::orderBy('id')->get();

        return view('livewire.admin.synonyms.index', [
            'groups' => $groups,
        ])->layout('layouts.app', ['header' => 'Synonyms']);
    }
}
