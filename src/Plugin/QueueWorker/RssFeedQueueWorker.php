<?php

namespace Drupal\rss_aggregator\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rss_aggregator\FeedFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fetches a feed document and pushes its entries onto the item queue.
 */
#[QueueWorker(
  id: 'rss_aggregator_feeds',
  title: new TranslatableMarkup('RSS importer: feed fetcher'),
  cron: ['time' => 30]
)]
class RssFeedQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected FeedFetcher $feedFetcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('rss_aggregator.feed_fetcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $feed_id = (string) ($data['feed_id'] ?? '');
    if ($feed_id === '') {
      return;
    }
    $this->feedFetcher->fetchFeed($feed_id);
  }

}
