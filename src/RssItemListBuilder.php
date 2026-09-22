<?php

namespace Drupal\rss_aggregator;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * List builder for the rss_item content entity.
 */
class RssItemListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['title'] = $this->t('Title');
    $header['feed'] = $this->t('Feed');
    $header['published'] = $this->t('Published');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\rss_aggregator\Entity\RssItem $entity */
    $row['id'] = $entity->id();
    $row['title'] = Link::fromTextAndUrl($entity->label(), $entity->toUrl());
    $row['feed'] = $this->feedLabel($entity->getFeedId());
    $published = $entity->get('published_at')->value;
    $row['published'] = $published
      ? \Drupal::service('date.formatter')->format((int) $published, 'short')
      : $this->t('Unknown');
    return $row + parent::buildRow($entity);
  }

  /**
   * Resolves the human label of the feed the item came from.
   */
  protected function feedLabel(string $feed_id): string {
    if ($feed_id === '') {
      return '';
    }
    $feed = \Drupal::entityTypeManager()->getStorage('rss_feed')->load($feed_id);
    return $feed ? (string) $feed->label() : $this->t('Deleted feed');
  }

}
