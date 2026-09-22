<?php

namespace Drupal\rss_aggregator\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the RSS feed config entity.
 *
 * A "feed" is an external RSS or Atom source. Feeds are scheduled on cron and
 * processed through the "rss_aggregator_feeds" queue.
 *
 * @ConfigEntityType(
 *   id = "rss_feed",
 *   label = @Translation("RSS feed"),
 *   handlers = {
 *     "list_builder" = "Drupal\rss_aggregator\RssFeedListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *     "form" = {
 *       "add" = "Drupal\rss_aggregator\Form\RssFeedForm",
 *       "edit" = "Drupal\rss_aggregator\Form\RssFeedForm",
 *       "delete" = "Drupal\rss_aggregator\Form\RssFeedDeleteForm"
 *     }
 *   },
 *   config_prefix = "feed",
 *   admin_permission = "administer rss importer",
 *   label_collection = @Translation("RSS feeds"),
 *   label_singular = @Translation("rss feed"),
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "collection" = "/admin/config/services/rss-feeds",
 *     "add-form" = "/admin/config/services/rss-feeds/add",
 *     "edit-form" = "/admin/config/services/rss-feeds/{rss_feed}/edit",
 *     "delete-form" = "/admin/config/services/rss-feeds/{rss_feed}/delete"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "url",
 *     "refresh_interval",
 *     "description",
 *     "status",
 *     "next_refresh",
 *     "last_refreshed"
 *   }
 * )
 */
class RssFeed extends ConfigEntityBase {

  /**
   * The feed URL.
   *
   * @var string
   */
  protected $url = '';

  /**
   * The refresh interval in minutes.
   *
   * @var int
   */
  protected $refresh_interval = 60;

  /**
   * Human-readable description of the feed.
   *
   * @var string
   */
  protected $description = '';

  /**
   * Unix timestamp of the next scheduled refresh.
   *
   * @var int
   */
  protected $next_refresh = 0;

  /**
   * Unix timestamp of the last successful refresh.
   *
   * @var int
   */
  protected $last_refreshed = 0;

  /**
   * Returns the feed URL.
   */
  public function getUrl(): string {
    return (string) $this->url;
  }

  /**
   * Returns the refresh interval in minutes.
   */
  public function getRefreshInterval(): int {
    return (int) ($this->refresh_interval ?: 60);
  }

  /**
   * Returns the feed description.
   */
  public function getDescription(): string {
    return (string) $this->description;
  }

  /**
   * Returns the next scheduled refresh timestamp (0 = due immediately).
   */
  public function getNextRefresh(): int {
    return (int) ($this->next_refresh ?? 0);
  }

  /**
   * Sets the next scheduled refresh timestamp.
   */
  public function setNextRefresh(int $timestamp): static {
    $this->next_refresh = $timestamp;
    return $this;
  }

  /**
   * Returns the last successful refresh timestamp (0 = never refreshed).
   */
  public function getLastRefreshed(): int {
    return (int) ($this->last_refreshed ?? 0);
  }

  /**
   * Sets the last successful refresh timestamp.
   */
  public function setLastRefreshed(int $timestamp): static {
    $this->last_refreshed = $timestamp;
    return $this;
  }

  /**
   * Determines whether the feed is due for a refresh.
   *
   * @param int|null $now
   *   The current request time; defaults to the request time service.
   */
  public function isDue(?int $now = NULL): bool {
    $now = $now ?? (int) \Drupal::time()->getRequestTime();
    return $now >= $this->getNextRefresh();
  }

}
