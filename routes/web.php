<?php

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
use App\Livewire\Admin\QueryPlanner\Settings as QueryPlannerSettings;
use App\Livewire\Admin\RetrievalLogs\Index as RetrievalLogsIndex;
use App\Livewire\Admin\Search\Playground as SearchPlayground;
use App\Livewire\Admin\Settings\General as SettingsGeneral;
use App\Livewire\Admin\Settings\Notifications as SettingsNotifications;
use App\Livewire\Admin\Settings\Storage as SettingsStorage;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Admin\Users\Roles as UsersRoles;
use App\Livewire\Admin\VectorStore\Settings as VectorStoreSettings;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::get('dashboard', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('admin')->name('admin.')->middleware('role:admin,operator')->group(function () {
        Route::livewire('dashboard', DashboardIndex::class)->name('dashboard');

        Route::livewire('knowledge-sources', KnowledgeSourcesIndex::class)->name('knowledge-sources.index');
        Route::livewire('knowledge-sources/{source}', KnowledgeSourcesShow::class)->name('knowledge-sources.show');

        Route::livewire('providers', ProvidersIndex::class)->name('providers.index');
        Route::livewire('providers/{provider}/configure', ProvidersConfigure::class)->name('providers.configure');

        Route::livewire('documents', DocumentsIndex::class)->name('documents.index');
        Route::livewire('documents/{document}', DocumentsShow::class)->name('documents.show');

        Route::livewire('chunks', ChunksIndex::class)->name('chunks.index');

        Route::livewire('retrieval-logs', RetrievalLogsIndex::class)->name('retrieval-logs.index');

        Route::livewire('playground', SearchPlayground::class)->name('playground');

        Route::livewire('vector-store', VectorStoreSettings::class)->name('vector-store.settings');

        Route::livewire('query-planner', QueryPlannerSettings::class)->name('query-planner.settings');

        Route::livewire('jobs', JobsIndex::class)->name('jobs.index');

        Route::livewire('mcp/api-keys', McpApiKeys::class)->name('mcp.api-keys');
        Route::livewire('mcp/settings', McpSettings::class)->name('mcp.settings');

        Route::livewire('users', UsersIndex::class)->name('users.index');
        Route::livewire('users/roles', UsersRoles::class)->name('users.roles');

        Route::livewire('settings/general', SettingsGeneral::class)->name('settings.general');
        Route::livewire('settings/storage', SettingsStorage::class)->name('settings.storage');
        Route::livewire('settings/notifications', SettingsNotifications::class)->name('settings.notifications');

        Route::livewire('federation/servers', FederationServers::class)->name('federation.servers');

        Route::livewire('health', HealthDashboard::class)->name('health');
    });
});

require __DIR__.'/settings.php';
