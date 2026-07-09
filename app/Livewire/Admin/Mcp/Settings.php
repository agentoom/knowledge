<?php

namespace App\Livewire\Admin\Mcp;

use App\Mcp\Servers\KnowledgeServer;
use Livewire\Component;

class Settings extends Component
{
    public array $serverInfo = [];

    public array $tools = [];

    public array $prompts = [];

    public function mount(): void
    {
        $reflection = new \ReflectionClass(KnowledgeServer::class);
        $attrs = $reflection->getAttributes();

        $this->serverInfo = [];
        foreach ($attrs as $attr) {
            $instance = $attr->newInstance();
            $name = class_basename($attr->getName());
            $this->serverInfo[$name] = $instance->getArguments()[0] ?? $name;
        }

        $server = app(KnowledgeServer::class);

        $this->tools = collect($server->tools())->map(fn ($tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
        ])->all();

        $this->prompts = collect($server->prompts())->map(fn ($prompt) => [
            'name' => $prompt->name(),
            'description' => $prompt->description(),
        ])->all();
    }

    public function render()
    {
        return view('livewire.admin.mcp.settings')
            ->layout('layouts.app', ['header' => 'MCP Settings']);
    }
}
