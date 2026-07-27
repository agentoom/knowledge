<?php

use App\Enums\Role;
use App\Http\Controllers\HealthController;
use App\Livewire\Admin\Chunks\Index as ChunksIndex;
use App\Livewire\Admin\Dashboard\Index as DashboardIndex;
use App\Livewire\Admin\Documents\Index as DocumentsIndex;
use App\Livewire\Admin\Documents\Show as DocumentsShow;
use App\Livewire\Admin\Federation\Servers as FederationServers;
use App\Livewire\Admin\Health\Dashboard as HealthDashboard;
use App\Livewire\Admin\Jobs\Index as JobsIndex;
use App\Livewire\Admin\KnowledgeSources\Index as KnowledgeSourcesIndex;
use App\Livewire\Admin\KnowledgeSources\Show as KnowledgeSourcesShow;
use App\Livewire\Admin\Mcp\ApiKeys as McpApiKeys;
use App\Livewire\Admin\Mcp\Settings as McpSettings;
use App\Livewire\Admin\Providers\Configure as ProvidersConfigure;
use App\Livewire\Admin\Providers\Index as ProvidersIndex;
use App\Livewire\Admin\RetrievalLogs\Index as RetrievalLogsIndex;
use App\Livewire\Admin\Search\Playground as SearchPlayground;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Admin\Users\Roles as UsersRoles;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::get('install', function () {
    if (User::where('role', Role::Admin)->exists()) {
        return response('Application already installed. A superadmin user already exists.', 403);
    }

    User::create([
        'name' => 'Super Admin',
        'email' => 'knowledge@agentoom.com',
        'password' => 'changeme',
        'role' => Role::Admin,
    ]);

    return response('Installation complete. Superadmin user created with email knowledge@agentoom.com. Please change the password immediately.', 200);
})->name('install');

Route::get('dashboard', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('admin')->name('admin.')->middleware('role:admin,operator')->group(function () {
        Route::livewire('dashboard', DashboardIndex::class)->name('dashboard');

        Route::livewire('knowledge-sources', KnowledgeSourcesIndex::class)->name('knowledge-sources.index');
        Route::livewire('knowledge-sources/create', Create::class)->name('knowledge-sources.create');
        Route::livewire('knowledge-sources/{source}', KnowledgeSourcesShow::class)->name('knowledge-sources.show');

        Route::livewire('providers', ProvidersIndex::class)->name('providers.index');
        Route::livewire('providers/{provider}/configure', ProvidersConfigure::class)->name('providers.configure');

        Route::livewire('documents', DocumentsIndex::class)->name('documents.index');
        Route::livewire('documents/{document}', DocumentsShow::class)->name('documents.show');

        Route::livewire('chunks', ChunksIndex::class)->name('chunks.index');

        Route::livewire('retrieval-logs', RetrievalLogsIndex::class)->name('retrieval-logs.index');

        Route::livewire('playground', SearchPlayground::class)->name('playground');

        Route::livewire('jobs', JobsIndex::class)->name('jobs.index');

        Route::livewire('mcp/api-keys', McpApiKeys::class)->name('mcp.api-keys');
        Route::livewire('mcp/settings', McpSettings::class)->name('mcp.settings');

        Route::livewire('users', UsersIndex::class)->name('users.index');
        Route::livewire('users/roles', UsersRoles::class)->name('users.roles');

        Route::livewire('settings', SettingsIndex::class)->name('settings');

        Route::livewire('federation/servers', FederationServers::class)->name('federation.servers');

        Route::livewire('health', HealthDashboard::class)->name('health');
    });
});

require __DIR__.'/settings.php';

Route::get('/health', [HealthController::class, 'check'])->name('health');
