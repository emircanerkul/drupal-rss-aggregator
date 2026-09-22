# RSS Aggregator

A Drupal 11 custom module that imports external RSS/Atom feeds into dedicated
content entities, using the Queue API for resumable, cron-friendly processing.

Built as a learning experiment — see the [experiment log](#experiment-log) for
the interesting bugs found along the way, and
[building with runanywhere.ai](#built-with-runanywhereai) for how the whole
module was written by an AI agent.

## Features

- **Two custom entities**
  - `rss_feed` (config entity) — feed sources: URL, refresh interval, enabled
    state, and next/last refresh scheduling.
  - `rss_item` (content entity) — imported articles with title, external link,
    GUID, description (full feed HTML), publish date and a SHA-1 hash used for
    deduplication (enforced by a unique database key *and* a worker check).
- **Two-stage queue pipeline**
  - `rss_aggregator_feeds` — cron enqueues due feeds; this worker fetches the
    document, parses RSS/Atom via `laminas/laminas-feed`, and pushes every
    entry onto the item queue.
  - `rss_aggregator_items` — converts queued entries into `rss_item` entities,
    skipping anything already imported.
- **Admin UI**
  - Feed CRUD: `/admin/config/services/rss-feeds` (list / add / edit / delete)
    with a per-feed "Import now" tab.
  - Items browser: `/admin/content/rss-items`, also available as a tab on the
    regular Content page.
  - Item detail page renders the fetched feed content (sanitized through a
    tag whitelist) plus metadata.
  - Settings: HTTP timeout and item lifetime (auto-prune on cron).
- **Drush commands**
  - `drush rss:import <feed_id>` (alias `rssi`) or `drush rss:import --all` —
    enqueue feeds for import.
  - `drush rss:process [--feed-queue]` (alias `rssp`) — run the workers
    manually.
- **Cron integration** — enabled feeds whose refresh interval has elapsed are
  enqueued automatically; their `next_refresh` is advanced immediately so a
  single cron run never double-enqueues.

## Requirements

- Drupal ^10 || ^11
- `laminas/laminas-feed` (the feed parser):

  ```bash
  composer require laminas/laminas-feed
  ```

  Drupal core does not ship this library; `aggregator` in core uses it, but if
  you don't want to install that module, require the library directly.

## Installation

```bash
composer require laminas/laminas-feed
drush en rss_aggregator -y
```

Then add your first feed at **Configuration → Web services → RSS feeds**
(`/admin/config/services/rss-feeds`).

## Usage

### Automatic (cron)

Every cron run: due feeds are enqueued → `rss_aggregator_feeds` fetches and
parses them → `rss_aggregator_items` creates entities. With Drupal's stock
cron this happens in the same run; on production you typically run
`drush queue:run` from a real queue worker (`core_queue_*`) instead.

### Manual (Drush)

```bash
# Enqueue one feed or all enabled feeds
drush rss:import my_feed
drush rss:import --all

# Process the queues (fetch + create entities)
drush rss:process --feed-queue
```

### Deduplication

An item is only imported once per feed. Identity is the first non-empty value
of `guid` → `link` → `title`, hashed together with the feed id
(`sha1(feed_id|identity)`). Re-importing the same feed creates nothing new.

## Architecture

```
cron ──▶ enqueue due feeds ──▶ [rss_aggregator_feeds queue]
                                  │  worker: HTTP GET + laminas-feed parse
                                  ▼
                              [rss_aggregator_items queue]
                                  │  worker: hash dedupe + entity create
                                  ▼
                               rss_item entities
```

Key classes:

| Class | Role |
|---|---|
| `Entity\RssFeed` | Config entity: source + schedule |
| `Entity\RssItem` | Content entity: imported article |
| `FeedFetcher` | HTTP fetch, parse, enqueue; also the enqueue service |
| `Plugin\QueueWorker\RssFeedQueueWorker` | Fetch stage |
| `Plugin\QueueWorker\RssItemQueueWorker` | Entity-creation stage |
| `RssItemViewsData` | Views integration (wizard + custom views) |
| `Drush\Commands\RssAggregatorCommands` | `rss:import` / `rss:process` |

## Experiment log

This module was developed iteratively against a live ddev site
(Drupal 11.4, PHP 8.4). Several modern-Drupal pitfalls surfaced, documented
here for future reference:

### 1. Queue workers need PHP attributes, not annotations
`@QueueWorker` docblock annotations are no longer discovered on Drupal 11.4.
Workers must use the attribute:

```php
use Drupal\Core\Queue\Attribute\QueueWorker;

#[QueueWorker(id: 'rss_aggregator_feeds', cron: ['time' => 30])]
class RssFeedQueueWorker extends QueueWorkerBase { ... }
```

Symptom before the fix: `drush queue:list` showed the queues (DatabaseQueue
always exists) but `plugin.manager.queue_worker->getDefinitions()` returned an
empty array.

### 2. Plugin discovery only scans `src/Plugin/<Subdir>`
Workers originally lived in `src/Queue/` and were silently never found.
Plugin discovery walks `src/Plugin/QueueWorker/` (matching the `subdir` passed
to the plugin manager). Directory layout is not cosmetic — it *is* the
discovery mechanism.

### 3. Drush 13 command classes need `AutowireTrait`
Drush finds module commands in `src/Drush/Commands/*Commands.php` by pattern
(`*Commands.php`) and tries `new $class()` / `create()` when it has no service
definition. A constructor with required arguments fails with a debug-only
message (`drush -vvv` is essential):

```
Could not instantiate ...: Too few arguments ...
```

Fix: use `Drush\Commands\AutowireTrait`. It resolves type-hints from the
Drupal container, and non-obvious services (e.g. our own fetcher) are pointed
at explicitly:

```php
use Drush\Commands\AutowireTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RssAggregatorCommands extends DrushCommands {
  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'rss_aggregator.feed_fetcher')]
    protected FeedFetcher $feedFetcher,
  ) { parent::__construct(); }
}
```

Note: `drush.command` service tags in `*.services.yml` are a legacy path
(`drush.services.yml` era); plain command classes in `src/Drush/Commands` are
discovered without any service registration.

### 4. Entities need a `route_provider` or the UI 500s
Defining `links` (edit-form, delete-form, collection) in the entity annotation
does nothing unless the entity type also declares:

```php
"route_provider" = { "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider" }
```

Without it, `$entity->toUrl('edit-form')` throws
`RouteNotFoundException: Route "entity.rss_feed.edit_form" does not exist`.
The manual routes in `routing.yml` under different names don't help — the
entity system looks up canonical `entity.*` route names.

### 5. Bundle-less content entities render empty canonical pages
The auto-generated canonical route uses `_entity_view: rss_item.full`, but for
an entity with no bundles there are no `entity_view_display` configs, so
`EntityViewBuilder` builds nothing and the page renders an empty main region.
Fix: override `getCanonicalRoute()` in a custom HTML route provider and point
the route at a controller that renders fields explicitly.

### 6. Config entity IDs are strings — don't int-cast them
Feed IDs are machine names (`'test_feed'`). Casting to `int` in the item
worker produced `0` for every row. The `feed_id` base field is `string`.

### 7. `laminas/laminas-feed` is not in core's vendor tree
Parser failure appeared as `Class "Laminas\Feed\Reader\Reader" not found`
inside a watchdog message. `composer require laminas/laminas-feed` solves it.

### 8. Drush caches command discovery separately from Drupal
After changing a command class, `drush cr` is not enough in some flows; the
command list is recomputed per request, but instantiation failures only show
up with `-vvv`. Always debug Drush command registration with `drush -vvv help
<command>`.

## Built with runanywhere.ai

This entire module was created by an AI coding agent running on
[runanywhere.ai](https://runanywhere.ai) (DeepSeek Harness, GLM model family) —
no code was written in a local IDE. The complete workflow, from empty folder to
a tested, GitHub-published module, happened inside one agentic session.

**How the experiment worked:**

- The agent had direct access to the local filesystem, shell and (escalated)
  tools — it scaffolded the module, configured a disposable
  [ddev](https://ddev.com) Drupal 11 environment (Drupal 11.4 / PHP 8.4 /
  MariaDB) itself, including fixing ddev's Docker provider (installing a
  buildx plugin, switching router ports).
- The human drove with short natural-language requests ("use ddev setup some
  simple drupal and install there to test it", "check again"). Debugging was
  interactive: the agent ran diagnostics inside the containers, the human
  pasted browser screenshots, and the agent fixed whatever the screenshots
  revealed (empty entity page, missing menu tab, Views integration…).
- Every iteration step — create, install, break, diagnose, fix — was executed
  live against a real running site, not simulated.

**Token usage for the whole build (per runanywhere.ai dashboard):**

| Metric | Value |
|---|---|
| Total tokens | **78,804,843** |
| Cache hit rate | **99.5%** |
| Uncached (fresh) input | 368,350 |
| Cached input | 78,192,640 |
| Output | 243,853 |
| Requests | 664 |
| Spent | **$1.7054** (of a $5 budget, ~$3.29 remaining) |

The striking part: **99.5% of input came from cache.** The conversational
agent re-reads large context (workspace files, logs, tool outputs) constantly;
provider-side prompt caching makes that nearly free. The entire build —
module code, environment setup, debugging cycles, README, Git push — cost
about **1.7 US dollars** of inference.

## Page references

| Page | Path |
|---|---|
| Feed sources | `/admin/config/services/rss-feeds` |
| Import a feed now | `/admin/config/services/rss-feeds/{feed}/import` |
| Imported items | `/admin/content/rss-items` |
| Item detail | `/admin/content/rss-items/{item}` |
| Module settings | `/admin/config/services/rss-feeds/settings` |

## License

MIT
