# Phase 6: Production Hardening — Implementation Plan

> **Status:** Plan (no code modifications made)
> **Target branch:** TBD from current `main`
> **Predecessor:** Phase 5 (SEO/localization remediation completed)

---

## Overview

Phase 6 adds the operational infrastructure needed to run Agentoom Knowledge reliably in production: scheduled maintenance, API abuse protection, service health monitoring for orchestration platforms, and an alerting pipeline for operational incidents.

The codebase **currently has none** of these — no Laravel scheduler entries (beyond hourly knowledge-source sync), no rate limiting on any endpoint, no health-check endpoint, and no notification pipeline for failures or latency.

---

## 1. Laravel Scheduler for Periodic Maintenance

### 1.1 Current state

The only scheduled task in `routes/console.php` is an hourly pipeline run over all active knowledge sources:

```php
Schedule::call(function () {
    $orchestrator = app(PipelineOrchestrator::class);
    KnowledgeSource::where('is_active', true)->each(function ($source) use ($orchestrator) {
        $orchestrator->run($source);
    });
})->hourly();
```

The production `compose.prod.yaml` runs `php artisan schedule:run` in a `scheduler` container on a 60-second loop, so the scheduler infrastructure itself is in place — it just lacks useful entries.

### 1.2 Additions needed

#### A. Horizon metrics snapshots

**Problem:** `config/horizon.php` sets `metrics.trim_snapshots` for job/queue metrics retention (24 snapshots each), but no `horizon:snapshot` command is scheduled. The Horizon metrics dashboard is permanently blank.

**Action:** Add a `$schedule->command('horizon:snapshot')->everyFiveMinutes()` entry. This keeps metrics populated and aligns with the 60s scheduler loop.

**Files to modify:**
- `routes/console.php`

**Configuration alignment:**
- Horizon `trim_snapshots` values of 24 mean ~2 hours of 5-minute snapshots. Verify this is sufficient for production; consider increasing `job`/`queue` to 288 (24 hours).

#### B. Federation sync

**Problem:** `FederationManager::syncCapabilities()` is only called reactively when federation servers are configured — there is no periodic refresh. Stale `remote_capabilities` may persist indefinitely.

**Action:** Add a scheduled job that iterates all active `FederatedServer` records and calls `FederationManager::syncCapabilities()`. Run every 15 minutes (configurable).

**New command (optional):** Could also add an `app:federation-sync` Artisan command for manual triggering and observability.

**Files to create/modify:**
- `routes/console.php` — add schedule entry
- (Optional) `app/Console/Commands/FederationSync.php` — dedicated command

#### C. Log pruning / rotation

**Problem:** The `compose.prod.yaml` has no log rotation. Laravel's native `daily` log channel keeps 14 days (`LOG_DAILY_DAYS=14`), but the default `single` channel grows unbounded. There's also no pruning of old `RetrievalLog` or `activity_log` table records.

**Action:**
1. Document that production `LOG_CHANNEL=daily` and `LOG_DAILY_DAYS=14` should be set in `.env.production`.
2. Add a scheduled pruning job for `RetrievalLog` records older than 30 days (configurable).
3. Optionally add a scheduled pruning job for the `activity_log` table if that logger is enabled in production.

**Files to modify:**
- `routes/console.php` — add pruning schedule entries
- `.env.example` — note `LOG_CHANNEL` guidance for production

#### D. Horizon job trimming verification

**Observation:** Horizon's `trim` config already sets reasonable retention for recent/failed/completed jobs (1 hour for recent, 1 week for failed). Horizon handles this internally and does not require a scheduler entry. No action needed here — just document the values are sufficient.

### 1.3 Summary: new schedule entries

| Entry | Frequency | Purpose |
|-------|-----------|---------|
| `horizon:snapshot` | Every 5 minutes | Populate Horizon metrics dashboard |
| Federation sync | Every 15 minutes | Refresh remote capabilities |
| RetrievalLog pruning | Daily | Delete records > 30 days old |

---

## 2. Rate Limiting on the MCP API Endpoint

### 2.1 Current state

The MCP API is served via `routes/ai.php`:

```php
Mcp::web('/mcp', KnowledgeServer::class)->middleware('auth:mcp_api');
```

There is **no rate limiting**. A malicious or misconfigured client can issue unlimited requests to `search_knowledge`, `list_sources`, or `get_source_schema` with no throttling. This exposes both the application and the underlying providers (Typesense, databases, federation servers) to abuse.

### 2.2 Design decisions

**Rate limit scope:** Per-API-key. The `McpApiGuard` authenticates via `ApiKey` records. Each key has a `key_prefix` field used for lookup. Rate limiting should key on the resolved API key ID (or the token prefix as a fallback).

**Laravel 13 built-in:** Laravel's `Illuminate\Cache\RateLimiter` is available (accessed via `RateLimiter` facade or `throttle` middleware). Laravel 13 supports named limiters via `RateLimiter::for()`.

**Approach options evaluated:**

| Option | Pros | Cons |
|--------|------|------|
| Inline `throttle` middleware on the route | Simplest; config can be environment-driven | Hard to separate search vs. lightweight operations |
| Custom middleware with per-operation limits | Fine-grained; distinguishes heavy operations | More code; harder to configure |
| **Recommended: Named rate limiter + route middleware** | Balances simplicity with reasonable granularity | — |

**Recommendation:** Use a named rate limiter `mcp-api` applied as a route-level middleware. The limiter allows 60 requests per minute per API key by default, configurable via `.env` (`MCP_RATE_LIMIT_PER_MINUTE`). The `search_knowledge` tool (most expensive) gets this default; lighter operations (`list_sources`, `get_source_schema`) could have a higher limit if needed (but the simple approach starts with one global limit, refining later if analytics show it's necessary).

### 2.3 Implementation steps

1. **Create a named rate limiter** in `AppServiceProvider::boot()` (or a dedicated `RateLimitServiceProvider`):

   ```php
   use Illuminate\Cache\RateLimiting\Limit;
   use Illuminate\Support\Facades\RateLimiter;

   RateLimiter::for('mcp-api', function (Request $request) {
       $keyId = optional($request->user())->getAuthIdentifier() ?? $request->ip();
       return Limit::perMinute(
           (int) env('MCP_RATE_LIMIT_PER_MINUTE', 60)
       )->by($keyId);
   });
   ```

2. **Apply to the MCP route** in `routes/ai.php`:

   ```php
   Mcp::web('/mcp', KnowledgeServer::class)
       ->middleware(['auth:mcp_api', 'throttle:mcp-api']);
   ```

3. **Add `.env` support:** Add `MCP_RATE_LIMIT_PER_MINUTE=60` to `.env.example` with a comment.

4. **Handle `429 Too Many Requests` gracefully:** The MCP protocol expects JSON-RPC error responses. The default Laravel `ThrottleRequests` middleware returns HTML for 429. Need to ensure the MCP server handles this gracefully — verify Laravel MCP returns proper JSON-RPC error codes when middleware rejects with 429. If not, register an exception handler in `bootstrap/app.php` that returns JSON for requests to `/mcp`.

**Files to modify:**
- `routes/ai.php` — add `throttle:mcp-api` middleware
- `app/Providers/AppServiceProvider.php` — define rate limiter (or new provider)
- `.env.example` — add `MCP_RATE_LIMIT_PER_MINUTE`
- `bootstrap/app.php` — optional 429 JSON handler for MCP route

### 2.4 Test coverage

Create `tests/Feature/Mcp/McpRateLimitTest.php`:
- Test that 60 rapid requests succeed
- Test that the 61st request within a minute returns 429
- Test that a different API key is not rate-limited by another key's activity
- Test that `X-RateLimit-Remaining` headers are present

---

## 3. Health-Check Endpoint

### 3.1 Current state

There is **no dedicated health-check endpoint**. The admin `/admin/health` Livewire dashboard (`HealthDashboard`) provides an interactive health status UI but requires session authentication and only checks DB, cache, vector store, and Typesense. It is not callable by Docker health checks, Kubernetes probes, or load balancers.

### 3.2 Requirements

The endpoint must:
- Return HTTP 200 when all critical services are healthy
- Return HTTP 503 when any critical service is unhealthy
- Respond in <1 second (cumulative checks should be fast)
- Not require authentication
- Not expose sensitive internal details in the response body (only service names and statuses)
- Be configurable as to which services are considered "critical" vs "optional"

### 3.3 Design

**Endpoint:** `GET /health` (or `GET /api/health`)

**Route location:** `routes/api.php` would conflict with the `auth:sanctum` middleware group. Add to `routes/web.php` instead as an unauthenticated route, or create a dedicated `routes/health.php` loaded unconditionally.

**Recommendation:** Add to `routes/web.php` (simplest, no new route file needed):

```php
Route::get('/health', [HealthController::class, 'check'])->name('health');
```

**Controller:** `app/Http/Controllers/HealthController.php`

Checks to perform:
1. **Database connectivity** — `DB::connection()->getPdo()`
2. **Redis connectivity** — `Cache::store('redis')->set('health', true, 1)` (since Redis is used for Horizon, queues, cache, and sessions in production)
3. **Typesense connectivity** — reuse `VectorStoreManager::driver()->healthCheck()` logic
4. **Disk writeability** — `Storage::disk('local')->put('health.txt', 'ok')` then delete (ensures the app can write to storage for logs, caches)

**Response format:**

```json
{
  "status": "ok",
  "checks": {
    "database": "ok",
    "redis": "ok",
    "typesense": "ok",
    "storage": "ok"
  },
  "timestamp": "2026-07-27T10:00:00Z"
}
```

On failure:
```json
{
  "status": "error",
  "checks": {
    "database": "ok",
    "redis": "ok",
    "typesense": "error",
    "storage": "ok"
  },
  "timestamp": "2026-07-27T10:00:00Z"
}
```

HTTP status: 503 on error.

**Fencing:** A `HEALTH_CHECK_TOKEN` env variable can optionally gate access if the response body should not be public (debugging aid). If set, require `?token=...` query parameter match. The check results returned are already minimal, so this is a nice-to-have.

### 3.4 Docker integration

The production `compose.prod.yaml` should add a healthcheck to the `app` service:

```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/health"]
  interval: 30s
  timeout: 5s
  retries: 3
```

The `nginx` service can proxy to the app's health endpoint.

### 3.5 Files to create/modify

| File | Action |
|------|--------|
| `app/Http/Controllers/HealthController.php` | Create |
| `routes/web.php` | Add route |
| `compose.prod.yaml` | Add healthcheck to `app` service |
| `tests/Feature/HealthEndpointTest.php` | Create |

### 3.6 Test coverage

- Test healthy response (200, JSON with "ok" status)
- Test with database down (must return 503 — may require a mock or a separate test DB configuration)
- Test response time is under 1000ms
- Test that non-authenticated requests succeed
- Test optional token gating if implemented

---

## 4. Notification Pipeline for Sync Failures and High-Latency Alerts

### 4.1 Current state

- **Notification settings UI exists:** `App\Livewire\Admin\Settings\Notifications` provides email/webhook toggles persisted via the `Settings` facade.
- **Settings keys in use:** `notifications.email_enabled`, `notifications.email_address`, `notifications.webhook_enabled`, `notifications.webhook_url`, `notifications.indexing_completed`, `notifications.indexing_failed`.
- **No notification sending:** Despite the UI, there are no listeners or Mailables that read these settings and send actual notifications.
- **Horizon notifications unconfigured:** `HorizonServiceProvider::boot()` has commented-out `routeMailNotificationsTo` / `routeSlackNotificationsTo` calls.
- **Events exist but no notifying listeners:**
  - `RetrievalExecuted` — fires on every search with `query`, `resultCount`, `durationMs`, `providersQueried`. No listener.
  - `ProviderSynced` — fires after provider sync. `RefreshRegistryOnProviderChange` listener dispatches a `RefreshMetadataRegistry` job, but no notification is sent on failure.
  - `SystemOnFallbackConfig` — defined but never dispatched anywhere in the codebase.
  - `ProviderRegistered` — defined, has a listener for registry refresh but no notification.

### 4.2 Two-tier notification strategy

#### Tier 1: Horizon built-in (LongWaitDetected)

Horizon fires `LongWaitDetected` when any queue exceeds its `waits` threshold (currently 60s for `redis:default`). Configure Horizon's built-in notification routing:

**In `HorizonServiceProvider::boot()`:**
```php
Horizon::routeMailNotificationsTo(
    config('knowledge.notifications.alert_email', env('MAIL_FROM_ADDRESS'))
);
Horizon::routeSlackNotificationsTo(
    env('LOG_SLACK_WEBHOOK_URL'), '#alerts'
);
```

Both are gated behind env variables — if not configured, notifications simply don't send.

**Dashboard fix:** Ensure the Horizon dashboard authorization already works (it does — `gate()` returns true for `Admin` role).

#### Tier 2: Custom application-level notifications (sync failures, high latency, provider errors)

**Design:** Create a `NotificationService` class that reads notification preferences from `Settings` and dispatches via email (Laravel Notifications/Mailables) and webhook (HTTP POST).

**Trigger points:**

1. **Federation sync failure** — `FederationManager::syncCapabilities()` already logs warnings. Add a check: if a server fails to sync N consecutive times, send an alert.

2. **Knowledge source pipeline failure** — `PipelineOrchestrator::run()` — if a job chain fails (document parsing, chunking, indexing), notify. This requires:
   - Tracking consecutive failures per knowledge source
   - A threshold setting (e.g., `KNOWLEDGE_SYNC_FAILURE_THRESHOLD=3`)

3. **High search latency** — `RetrievalExecuted` event already captures `durationMs`. Create a listener that checks if duration exceeds a threshold (configurable, e.g., `MCP_SEARCH_LATENCY_THRESHOLD_MS=5000`) and sends an alert.

**Implementation approach:**

| Component | Description |
|-----------|-------------|
| `App\Services\NotificationService` | Reads settings, dispatches mail/webhook |
| `App\Notifications\SyncFailureNotification` | Mailable for sync/pipeline failures |
| `App\Notifications\HighLatencyNotification` | Mailable for search latency |
| `App\Listeners\CheckSearchLatency` | Listens to `RetrievalExecuted`, thresholds check |
| `App\Listeners\NotifySyncFailure` | Listens to sync-failure scenarios |

**Cooldown / deduplication:** Use the cache to prevent notification floods. A key like `alert:search_latency:last_sent` with a TTL of 5 minutes ensures high-latency alerts don't fire on every slow query.

**Webhook format (for Slack/Discord/Teams):**
```json
{
  "text": "Agentoom Alert: High search latency detected",
  "attachments": [
    {
      "title": "Search Performance",
      "fields": [
        {"title": "Query", "value": "how to refund an order"},
        {"title": "Latency", "value": "8,234 ms"},
        {"title": "Providers", "value": "3"}
      ]
    }
  ]
}
```

### 4.3 Files to create/modify

| File | Action |
|------|--------|
| `app/Services/NotificationService.php` | Create |
| `app/Notifications/SyncFailureNotification.php` | Create |
| `app/Notifications/HighLatencyNotification.php` | Create |
| `app/Listeners/CheckSearchLatency.php` | Create |
| `app/Listeners/NotifySyncFailure.php` | Create |
| `app/Providers/AppServiceProvider.php` | Register new listeners |
| `app/Providers/HorizonServiceProvider.php` | Configure Horizon notification routing |
| `.env.example` | Add env vars for notification config |

### 4.4 Test coverage

- `tests/Feature/Notifications/HighLatencyAlertTest.php`
- `tests/Feature/Notifications/SyncFailureAlertTest.php`
- Test that cooldown prevents duplicate alerts within the window
- Test that email is sent when email notifications are enabled
- Test that webhook is POSTed when webhook notifications are enabled
- Test that disabled notifications (both email and webhook off) send nothing

---

## 5. Implementation Order & Dependencies

The four sections are largely independent, but there are logical layering considerations:

```
┌──────────────────────┐
│ 1. Scheduler          │  ← No dependencies, quick win
├──────────────────────┤
│ 3. Health Endpoint    │  ← No dependencies, enables Docker healthchecks
├──────────────────────┤
│ 2. Rate Limiting      │  ← Depends on knowing the MCP auth flow (already done)
├──────────────────────┤
│ 4. Notification Pipe  │  ← Depends on §1 (scheduler creates events to alert on)
└──────────────────────┘
```

**Recommended order:** Scheduler → Health Endpoint → Rate Limiting → Notifications

- Scheduler and Health Endpoint can be done in parallel by different developers.
- Rate Limiting is isolated to the MCP route.
- Notifications benefit from the scheduler being in place first (Horizon snapshots generate events; federation sync creates observable failures).

---

## 6. Files Summary

### New files to create

| File | Section |
|------|---------|
| `app/Console/Commands/FederationSync.php` | 1 (optional) |
| `app/Http/Controllers/HealthController.php` | 3 |
| `app/Services/NotificationService.php` | 4 |
| `app/Notifications/SyncFailureNotification.php` | 4 |
| `app/Notifications/HighLatencyNotification.php` | 4 |
| `app/Listeners/CheckSearchLatency.php` | 4 |
| `app/Listeners/NotifySyncFailure.php` | 4 |
| `app/Livewire/Admin/Settings/RateLimiting.php` | 9 |
| `app/Livewire/Admin/Settings/Maintenance.php` | 9 |
| `resources/views/livewire/admin/settings/rate-limiting.blade.php` | 9 |
| `resources/views/livewire/admin/settings/maintenance.blade.php` | 9 |
| `tests/Feature/Mcp/McpRateLimitTest.php` | 2 |
| `tests/Feature/HealthEndpointTest.php` | 3 |
| `tests/Feature/Notifications/HighLatencyAlertTest.php` | 4 |
| `tests/Feature/Notifications/SyncFailureAlertTest.php` | 4 |
| `tests/Feature/Settings/RateLimitingSettingsTest.php` | 9 |
| `tests/Feature/Settings/MaintenanceSettingsTest.php` | 9 |

### Existing files to modify

| File | Section(s) | Change |
|------|-----------|--------|
| `routes/console.php` | 1 | Add `horizon:snapshot`, federation sync, log pruning schedules |
| `routes/ai.php` | 2 | Add `throttle:mcp-api` middleware |
| `routes/web.php` | 3 | Add `GET /health` route |
| `app/Providers/AppServiceProvider.php` | 2, 4 | Define `mcp-api` rate limiter; register notification listeners |
| `app/Providers/HorizonServiceProvider.php` | 4 | Configure `routeMailNotificationsTo` / `routeSlackNotificationsTo` |
| `compose.prod.yaml` | 3 | Add healthcheck to `app` and `horizon` services |
| `.env.example` | 2, 4, 10 | Add `MCP_RATE_LIMIT_PER_MINUTE`, `MCP_SEARCH_LATENCY_THRESHOLD_MS`, notification/maintenance config vars |
| `config/horizon.php` | 1 | Consider increasing `metrics.trim_snapshots` (24 → 288 for daily retention) |
| `bootstrap/app.php` | 2 | Optional 429 JSON exception handler for MCP routes |
| `app/Livewire/Admin/Settings/Notifications.php` | 9 | Add 6 new properties + validation + save logic |
| `resources/views/livewire/admin/settings/notifications.blade.php` | 9 | Add threshold + alert type form fields |
| `resources/views/livewire/admin/settings/index.blade.php` | 9 | Add `rate-limiting` and `maintenance` tab buttons + content divs |

---

## 7. Environment Variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `MCP_RATE_LIMIT_PER_MINUTE` | `60` | Max requests per API key per minute |
| `MCP_SEARCH_LATENCY_THRESHOLD_MS` | `5000` | Latency threshold for high-latency alerts |
| `KNOWLEDGE_SYNC_FAILURE_THRESHOLD` | `3` | Consecutive failures before alerting |
| `NOTIFICATION_COOLDOWN_SECONDS` | `300` | Minimum interval between duplicate alerts |
| `HEALTH_CHECK_TOKEN` | `null` | Optional gating token for health endpoint |
| `HORIZON_SLACK_WEBHOOK_URL` | `null` | Slack webhook for Horizon alerts |

---

## 8. Risks & Considerations

1. **Rate limiting on MCP could break legitimate AI agent workflows** — Start with a generous default (60/min) and monitor. Make the limit configurable per key via the `ApiKey` model's `scopes` or a new `rate_limit` column in the future.

2. **Health endpoint must not DoS downstream services** — Each call runs DB/Cache/Typesense checks; at 30s Docker intervals this is fine, but if a load balancer polls every 5s across 10 instances, that's 120 checks/minute. Consider short caching (1s TTL) if this becomes an issue.

3. **Federation sync schedule may clash with Horizon worker availability** — The 15-minute sync iterates all federated servers. If there are 100+ servers, this could be heavy. Use `withoutOverlapping()` and a lock timeout.

4. **Notification pipeline MUST NOT cause cascading failures** — If the mail server is down, notification sending should fail silently (catch exceptions, log warnings, do not throw). The `NotificationService` should be wrapped in try/catch and never block the primary operation.

5. **Horizon metrics snapshots every 5 min vs. 24-snapshot retention** — With the current config, only 2 hours of data are retained. Recommend increasing to 288 (24 hours) for production.

6. **No nginx default.conf in `docker/nginx/`** — The `compose.prod.yaml` references `./docker/nginx/default.conf` but the file does not exist. This is a pre-existing issue unrelated to Phase 6 but blocks the health-check endpoint from working correctly in production. Should be created as part of this phase or documented as a dependency.

---

## 9. Admin UI Configuration for Phase 6 Settings

### 9.1 Current Settings Architecture

The admin settings page (`/admin/settings`) uses a tabbed interface defined in `app/Livewire/Admin/Settings/Index.php`. Tabs are rendered via Blade partials hidden/shown with Alpine.js `$tab` state. Each tab is its own Livewire component persisted via the `Settings` facade (DB-backed `Setting` model).

**Existing tabs and components:**

| Tab key | Livewire Component | Settings managed |
|---------|-------------------|------------------|
| `general` | `Settings\General` | `knowledge.app_name`, `knowledge.app_description` |
| `search-config` | `SearchConfig\Index` | Search configuration |
| `vector-store` | `Settings\VectorStore` | Typesense host/port/protocol/api_key |
| `storage` | `Settings\Storage` | `storage.knowledge_path`, `storage.processing_path`, `storage.is_mounted_volume` |
| `notifications` | `Settings\Notifications` | `notifications.email_enabled`, `notifications.email_address`, `notifications.webhook_enabled`, `notifications.webhook_url`, `notifications.indexing_completed`, `notifications.indexing_failed` |
| `danger-zone` | `Settings\DangerZone` | Destructive actions |

**Existing separate MCP page** (`/admin/mcp/settings`, not in Settings tabs):
- `Mcp\Settings` — read-only reflection of `KnowledgeServer` tools/prompts
- `Mcp\ApiKeys` — API key CRUD

### 9.2 New Settings That Need UI

Mapping all Phase 6 settings to where they belong in the admin UI:

#### A. Extend the Notifications tab (`Settings\Notifications`)

These settings are logically part of notification configuration:

| Setting key | Type | Default | UI control |
|------------|------|---------|------------|
| `notifications.latency_threshold_ms` | integer | `5000` | Number input (ms) |
| `notifications.sync_failure_threshold` | integer | `3` | Number input (consecutive failures) |
| `notifications.cooldown_seconds` | integer | `300` | Number input (seconds) |
| `notifications.search_latency_enabled` | boolean | `true` | Toggle |
| `notifications.sync_failure_enabled` | boolean | `true` | Toggle |
| `notifications.federation_failure_enabled` | boolean | `true` | Toggle |

**Component changes:** Add properties, validation, and `save()` logic to the existing `Notifications` Livewire component. Add form fields to the Blade view.

#### B. New "Rate Limiting" tab

| Setting key | Type | Default | UI control |
|------------|------|---------|------------|
| `mcp.rate_limit_per_minute` | integer | `60` | Number input |
| `mcp.rate_limiting_enabled` | boolean | `true` | Toggle (allows disabling entirely) |

**New component:** `app/Livewire/Admin/Settings/RateLimiting.php`
**New view:** `resources/views/livewire/admin/settings/rate-limiting.blade.php`

#### C. New "Maintenance" tab

| Setting key | Type | Default | UI control |
|------------|------|---------|------------|
| `maintenance.federation_sync_interval` | integer | `15` | Number input (minutes) |
| `maintenance.log_pruning_enabled` | boolean | `true` | Toggle |
| `maintenance.log_pruning_age_days` | integer | `30` | Number input (days) |
| `maintenance.retrieval_log_pruning_enabled` | boolean | `true` | Toggle |
| `maintenance.retrieval_log_pruning_age_days` | integer | `30` | Number input (days) |

**New component:** `app/Livewire/Admin/Settings/Maintenance.php`
**New view:** `resources/views/livewire/admin/settings/maintenance.blade.php`

### 9.3 Settings Deliberately Kept as Env-Only (No UI)

These settings contain secrets or are deployment-level concerns rarely changed after initial setup:

| Variable | Reason |
|----------|--------|
| `HEALTH_CHECK_TOKEN` | Secret — should not be stored in the DB or visible in a UI |
| `HORIZON_SLACK_WEBHOOK_URL` | Contains a webhook secret URL |
| `LOG_CHANNEL` / `LOG_DAILY_DAYS` | Deployment-level config set during provisioning |
| `MAIL_*` variables | Already handled by Laravel's mail config system |

### 9.4 Implementation Steps

1. **Extend `Settings\Notifications` component:** Add the 6 new properties, `mount()` hydration from Settings, validation rules, and `save()` persistence.
2. **Extend notifications Blade view:** Add form fields below existing ones, grouped under a "Thresholds" sub-heading and an "Alert Types" sub-heading.
3. **Create `Settings\RateLimiting` component:** Follow the same pattern as `Notifications` — `mount()`, `save()`, validation, dispatch events.
4. **Create `rate-limiting.blade.php` view:** Flux-styled form with number input for rate limit and toggle for enabled/disabled, plus a short explanation of the rate-limiting behavior.
5. **Create `Settings\Maintenance` component:** Same pattern.
6. **Create `maintenance.blade.php` view:** Grouped fields for federation sync, log pruning, and retrieval log pruning.
7. **Update `Settings\Index` component:** Add `rate-limiting` and `maintenance` to the tabs array and render the new Livewire components in the tab content area.
8. **Update settings index Blade view:** Add tab buttons and content divs for `rate-limiting` and `maintenance`.

### 9.5 UI Tab Order (Updated)

```
General → Search Config → Vector Store → Storage → Notifications → Rate Limiting → Maintenance → Danger Zone
```

### 9.6 Files to Create/Modify for UI

| File | Action |
|------|--------|
| `app/Livewire/Admin/Settings/Notifications.php` | **Modify** — add 6 new properties + save logic |
| `resources/views/livewire/admin/settings/notifications.blade.php` | **Modify** — add threshold + alert type fields |
| `app/Livewire/Admin/Settings/RateLimiting.php` | **Create** |
| `resources/views/livewire/admin/settings/rate-limiting.blade.php` | **Create** |
| `app/Livewire/Admin/Settings/Maintenance.php` | **Create** |
| `resources/views/livewire/admin/settings/maintenance.blade.php` | **Create** |
| `resources/views/livewire/admin/settings/index.blade.php` | **Modify** — add 2 new tabs |

### 9.7 Test Coverage

- `tests/Feature/Settings/NotificationSettingsTest.php` — extend existing or create: test new threshold fields save/load correctly
- `tests/Feature/Settings/RateLimitingSettingsTest.php` — create: test rate limit config persistence
- `tests/Feature/Settings/MaintenanceSettingsTest.php` — create: test maintenance config persistence
- Test tab switching preserves dirty state detection for the new tabs

---

## 10. Environment Variables (Consolidated)

Settings persisted in the DB via the Settings UI (§9) are **runtime** values read by the application at execution time. Env variables act as **defaults** or **secrets**. The pattern is:

```php
$value = Settings::get('mcp.rate_limit_per_minute', (int) env('MCP_RATE_LIMIT_PER_MINUTE', 60));
```

| Variable | Default | In UI? | Purpose |
|----------|---------|--------|---------|
| `MCP_RATE_LIMIT_PER_MINUTE` | `60` | Yes (Rate Limiting tab) | Default max requests per API key per minute |
| `MCP_SEARCH_LATENCY_THRESHOLD_MS` | `5000` | Yes (Notifications tab) | Latency threshold for alerts |
| `KNOWLEDGE_SYNC_FAILURE_THRESHOLD` | `3` | Yes (Notifications tab) | Consecutive failures before alerting |
| `NOTIFICATION_COOLDOWN_SECONDS` | `300` | Yes (Notifications tab) | Min interval between duplicate alerts |
| `HEALTH_CHECK_TOKEN` | `null` | No (secret) | Optional gating token for health endpoint |
| `HORIZON_SLACK_WEBHOOK_URL` | `null` | No (secret) | Slack webhook for Horizon alerts |
| `FEDERATION_SYNC_INTERVAL_MINUTES` | `15` | Yes (Maintenance tab) | Default federation sync frequency |
| `LOG_PRUNING_AGE_DAYS` | `30` | Yes (Maintenance tab) | Default log pruning age |

---

## 11. Estimated Effort (Updated)

| Section | Complexity | Est. Duration | Test Effort |
|---------|-----------|---------------|-------------|
| 1. Scheduler | Low | 1-2 hours | 30 min |
| 2. Rate Limiting (backend) | Medium | 2-3 hours | 1 hour |
| 3. Health Endpoint | Low | 1-2 hours | 45 min |
| 4. Notification Pipeline | Medium-High | 4-6 hours | 1.5 hours |
| 5. UI Configuration (§9) | Medium | 3-4 hours | 1 hour |
| **Total** | | **11-17 hours** | **~5 hours** |

---

*Plan created: 2026-07-27. Last modified: 2026-07-27 (added §9 Admin UI Configuration). No code modifications have been made. This plan is for stakeholder review and approval before implementation begins.*
