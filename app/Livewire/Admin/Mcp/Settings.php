<?php

namespace App\Livewire\Admin\Mcp;

use App\Mcp\Servers\KnowledgeServer;
use Illuminate\View\View;
use Livewire\Component;

class Settings extends Component
{
    /**
     * @var array<string, mixed>
     */
    public array $serverInfo = [];

    /**
     * @var array<int, array<string, string>>
     */
    public array $tools = [];

    /**
     * @var array<int, array<string, string>>
     */
    public array $prompts = [];

    public function mount(): void
    {
        $reflection = new \ReflectionClass(KnowledgeServer::class);
        $attrs = $reflection->getAttributes();

        $this->serverInfo = [];
        foreach ($attrs as $attr) {
            $instance = $attr->newInstance();
            $name = class_basename($attr->getName());
            $args = $instance->getArguments();
            $this->serverInfo[$name] = $args[0] ?? $name;
        }

        $server = app(KnowledgeServer::class);
        $serverReflection = new \ReflectionClass($server);

        $toolsProp = $serverReflection->getProperty('tools');
        /** @var array<int, object> $toolClasses */
        $toolClasses = $toolsProp->getValue($server);

        $this->tools = collect($toolClasses)->map(fn ($toolClass) => [
            'name' => $toolClass->name(),
            'description' => $toolClass->description(),
        ])->all();

        $promptsProp = $serverReflection->getProperty('prompts');
        /** @var array<int, object> $promptClasses */
        $promptClasses = $promptsProp->getValue($server);

        $this->prompts = collect($promptClasses)->map(fn ($promptClass) => [
            'name' => $promptClass->name(),
            'description' => $promptClass->description(),
        ])->all();
    }

    public function render(): View
    {
        return view('livewire.admin.mcp.settings')
            ->layout('layouts.app', ['header' => 'MCP Settings']);
    }
}
