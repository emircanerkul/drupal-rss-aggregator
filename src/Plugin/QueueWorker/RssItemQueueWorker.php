<?php

namespace Drupal\rss_aggregator\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Converts queued feed entries into rss_item content entities.
 */
#[QueueWorker(
  id: 'rss_aggregator_items',
  title: new TranslatableMarkup('RSS importer: item processor'),
  cron: ['time' => 60]
)]
class RssItemQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
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
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!is_array($data) || empty($data['hash'])) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('rss_item');

    // Primary dedup: same hash from the same feed is never imported twice.
    $existing = $storage->loadByProperties(['hash' => $data['hash']]);
    if ($existing) {
      return;
    }

    $values = [
      'feed_id' => (string) ($data['feed_id'] ?? ''),
      'title' => (string) ($data['title'] ?? ''),
      'description' => [
        'value' => (string) ($data['description'] ?? ''),
        'format' => NULL,
      ],
      'published_at' => (int) ($data['published_at'] ?? 0),
    ];

    $link = (string) ($data['link'] ?? '');
    if ($link !== '') {
      $values['link'] = ['uri' => $link];
    }
    $guid = (string) ($data['guid'] ?? '');
    if ($guid !== '') {
      $values['guid'] = mb_substr($guid, 0, 255);
    }

    $item = $storage->create($values + [
      'hash' => (string) $data['hash'],
    ]);

    try {
      $item->save();
    }
    catch (\Exception $e) {
      // A concurrent worker may have inserted the same hash first; treat the
      // unique-key violation as "already imported" rather than an error.
      if (str_contains($e->getMessage(), 'rss_item_hash') || str_contains($e->getMessage(), 'Duplicate entry')) {
        return;
      }
      throw $e;
    }

    $this->loggerFactory->get('rss_aggregator')->debug('Imported RSS item @title from feed @feed.', [
      '@title' => $item->label(),
      '@feed' => $item->getFeedId(),
    ]);
  }

}
