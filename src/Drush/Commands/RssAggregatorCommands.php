<?php

namespace Drupal\rss_aggregator\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\rss_aggregator\FeedFetcher;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Drush commands for the RSS Aggregator module.
 */
class RssAggregatorCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected QueueFactory $queueFactory,
    protected QueueWorkerManagerInterface $queueWorkerManager,
    #[Autowire(service: 'rss_aggregator.feed_fetcher')]
    protected FeedFetcher $feedFetcher,
  ) {
    parent::__construct();
  }

  #[CLI\Command(name: 'rss:import', aliases: ['rssi'])]
  #[CLI\Argument(name: 'feedId', description: 'Machine name of the feed to import. Omit with --all.')]
  #[CLI\Option(name: 'all', description: 'Enqueue every enabled feed.')]
  #[CLI\Usage(name: 'drush rss:import --all', description: 'Queue all enabled feeds for import.')]
  #[CLI\Usage(name: 'drush rss:import my_feed', description: 'Queue a single feed by machine name.')]
  public function import(?string $feedId = NULL, array $options = ['all' => FALSE]): void {
    if (!empty($options['all'])) {
      $feeds = $this->entityTypeManager->getStorage('rss_feed')->loadByProperties(['status' => TRUE]);
      if (!$feeds) {
        $this->logger()->warning('No enabled feeds found.');
        return;
      }
      foreach ($feeds as $feed) {
        $this->feedFetcher->enqueueFeed($feed->id());
        $this->logger()->success(dt('Queued feed: @id', ['@id' => $feed->id()]));
      }
      return;
    }

    if ($feedId === NULL || $feedId === '') {
      $this->logger()->error('Provide a feed id or use --all.');
      return;
    }

    $feed = $this->entityTypeManager->getStorage('rss_feed')->load($feedId);
    if (!$feed) {
      $this->logger()->error(dt('Feed @id does not exist.', ['@id' => $feedId]));
      return;
    }
    $this->feedFetcher->enqueueFeed($feedId);
    $this->logger()->success(dt('Queued feed: @id', ['@id' => $feedId]));
  }

  #[CLI\Command(name: 'rss:process', aliases: ['rssp'])]
  #[CLI\Option(name: 'feed-queue', description: 'Also process the feed fetch queue first.')]
  public function process(array $options = ['feed-queue' => FALSE]): void {
    if (!empty($options['feed-queue'])) {
      $feeds = $this->processQueue(FeedFetcher::FEED_QUEUE, 50);
      $this->logger()->success(dt('Processed @count feed queue item(s).', ['@count' => $feeds]));
    }
    $items = $this->processQueue(FeedFetcher::ITEM_QUEUE, 100);
    $this->logger()->success(dt('Processed @count item queue item(s).', ['@count' => $items]));
  }

  /**
   * Claims and processes up to $limit items from a queue.
   */
  protected function processQueue(string $name, int $limit): int {
    $queue = $this->queueFactory->get($name);
    $worker = $this->queueWorkerManager->createInstance($name);
    $processed = 0;

    while ($processed < $limit && ($item = $queue->claimItem())) {
      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $processed++;
      }
      catch (\Exception $e) {
        $this->logger()->error(dt('Queue @name item failed: @msg', ['@name' => $name, '@msg' => $e->getMessage()]));
        // Release for a later retry; repeated failures age out automatically.
        break;
      }
    }
    return $processed;
  }

}
