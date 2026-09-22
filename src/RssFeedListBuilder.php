<?php

namespace Drupal\rss_aggregator;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * List builder for the rss_feed config entity.
 */
class RssFeedListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Feed');
    $header['url'] = $this->t('URL');
    $header['interval'] = $this->t('Interval');
    $header['last_refreshed'] = $this->t('Last refreshed');
    $header['next_refresh'] = $this->t('Next refresh');
    $header['status'] = $this->t('Enabled');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\rss_aggregator\Entity\RssFeed $entity */
    $row['label'] = Link::fromTextAndUrl($entity->label(), $entity->toUrl('edit-form'));
    $row['url'] = $entity->getUrl();
    $row['interval'] = $this->t('@min min', ['@min' => $entity->getRefreshInterval()]);
    $last = $entity->getLastRefreshed();
    $row['last_refreshed'] = $last ? $this->date($last) : $this->t('Never');
    $next = $entity->getNextRefresh();
    $row['next_refresh'] = $entity->isDue() ? $this->t('Due now') : $this->date($next);
    $row['status'] = $entity->status() ? $this->t('Yes') : $this->t('No');
    return $row + parent::buildRow($entity);
  }

  /**
   * Formats a timestamp for display in the table.
   */
  protected function date(int $timestamp): string {
    return \Drupal::service('date.formatter')->format($timestamp, 'short');
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations($entity) {
    $operations = parent::getDefaultOperations($entity);
    $operations['import'] = [
      'title' => $this->t('Import'),
      'weight' => 20,
      'url' => Url::fromRoute('rss_aggregator.feed_import', ['rss_feed' => $entity->id()]),
    ];
    return $operations;
  }

}
