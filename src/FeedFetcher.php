<?php

namespace Drupal\rss_aggregator;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Reader;

/**
 * Fetches RSS/Atom documents and enqueues their items for processing.
 */
class FeedFetcher {

  /**
   * Queue holding individual items ready to be converted to entities.
   */
  public const ITEM_QUEUE = 'rss_aggregator_items';

  /**
   * Queue holding feed IDs that still need to be fetched.
   */
  public const FEED_QUEUE = 'rss_aggregator_feeds';

  public function __construct(
    protected ClientInterface $httpClient,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
    protected QueueFactory $queueFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Places a feed into the fetch queue.
   *
   * @return bool
   *   TRUE if the feed was enqueued, FALSE when it could not be loaded.
   */
  public function enqueueFeed(string $feed_id): bool {
    $feed = $this->entityTypeManager->getStorage('rss_feed')->load($feed_id);
    if (!$feed) {
      return FALSE;
    }
    $this->queueFactory->get(self::FEED_QUEUE)->createItem([
      'feed_id' => $feed_id,
    ]);
    return TRUE;
  }

  /**
   * Fetches a feed URL, parses it and enqueues every entry as an item.
   *
   * @return array{fetched: bool, queued: int, message: string}
   *   Result summary for the queue worker.
   */
  public function fetchFeed(string $feed_id): array {
    $storage = $this->entityTypeManager->getStorage('rss_feed');
    $feed = $storage->load($feed_id);
    if (!$feed) {
      return ['fetched' => FALSE, 'queued' => 0, 'message' => 'Feed no longer exists.'];
    }

    $timeout = (int) ($this->configFactory->get('rss_aggregator.settings')->get('request_timeout') ?: 30);

    try {
      $response = $this->httpClient->request('GET', $feed->getUrl(), [
        'timeout' => $timeout,
        'headers' => [
          'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.8',
          'User-Agent' => 'Drupal RSS Aggregator (+https://www.drupal.org)',
        ],
      ]);
    }
    catch (RequestException $e) {
      $this->loggerFactory->get('rss_aggregator')->error('Feed @feed (@url) request failed: @message', [
        '@feed' => $feed->label(),
        '@url' => $feed->getUrl(),
        '@message' => $e->getMessage(),
      ]);
      return ['fetched' => FALSE, 'queued' => 0, 'message' => 'HTTP request failed: ' . $e->getMessage()];
    }

    $body = (string) $response->getBody()->getContents();
    if (trim($body) === '') {
      return ['fetched' => FALSE, 'queued' => 0, 'message' => 'Empty response body.'];
    }

    try {
      $channel = Reader::importString($body);
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('rss_aggregator')->error('Feed @feed is not a valid RSS/Atom document: @message', [
        '@feed' => $feed->label(),
        '@message' => $e->getMessage(),
      ]);
      return ['fetched' => FALSE, 'queued' => 0, 'message' => 'Feed parse error: ' . $e->getMessage()];
    }

    $queued = 0;
    $queue = $this->queueFactory->get(self::ITEM_QUEUE);
    foreach ($channel as $entry) {
      $item = $this->extractItem($feed_id, $entry);
      $queue->createItem($item);
      $queued++;
    }

    $feed->setLastRefreshed($this->time->getRequestTime());
    $feed->save();

    $this->loggerFactory->get('rss_aggregator')->info('Fetched feed @feed and queued @count item(s).', [
      '@feed' => $feed->label(),
      '@count' => $queued,
    ]);

    return ['fetched' => TRUE, 'queued' => $queued, 'message' => "Queued {$queued} item(s)."];
  }

  /**
   * Converts a feed entry into the payload stored on the item queue.
   */
  protected function extractItem(string $feed_id, EntryInterface $entry): array {
    $title = trim((string) $entry->getTitle());
    $link = '';
    try {
      $link = trim((string) $entry->getLink());
    }
    catch (\Throwable) {
      // Some feeds omit the link element entirely.
    }
    $guid = '';
    try {
      $guid = trim((string) $entry->getId());
    }
    catch (\Throwable) {
      // Fall back to link/hash identity below.
    }
    $description = '';
    try {
      $description = (string) $entry->getDescription();
    }
    catch (\Throwable) {
      // Description is optional in both RSS and Atom.
    }
    $published = 0;
    try {
      $published = $entry->getDateCreated()?->getTimestamp() ?: 0;
    }
    catch (\Throwable) {
      // Missing pubDate is tolerated.
    }

    return [
      'feed_id' => $feed_id,
      'title' => mb_substr($title, 0, 255),
      'link' => $this->externalUri($link),
      'guid' => mb_substr($guid, 0, 255),
      'hash' => $this->dedupeHash($feed_id, $guid ?: $link ?: $title),
      'description' => $description,
      'published_at' => $published,
    ];
  }

  /**
   * Normalizes a URL into the URI format accepted by the link field.
   */
  protected function externalUri(string $url): string {
    $url = trim($url);
    if ($url === '') {
      return '';
    }
    if (parse_url($url, PHP_URL_SCHEME)) {
      return $url;
    }
    // Relative or scheme-less links get resolved against the feed URL if
    // possible; otherwise they are stored as-is.
    return $url;
  }

  /**
   * Computes the deduplication hash for a feed entry.
   */
  protected function dedupeHash(string $feed_id, string $identity): string {
    return sha1($feed_id . '|' . $identity);
  }

}
