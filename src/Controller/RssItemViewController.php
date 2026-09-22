<?php

namespace Drupal\rss_aggregator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\rss_aggregator\Entity\RssItem;

/**
 * Renders a single imported RSS item in the admin area.
 */
class RssItemViewController extends ControllerBase {

  /**
   * Renders the item.
   */
  public function view(RssItem $rss_item): array {
    $feed = $rss_item->getFeedId() !== ''
      ? $this->entityTypeManager()->getStorage('rss_feed')->load($rss_item->getFeedId())
      : NULL;

    $rows = [
      ['ID', $rss_item->id()],
      ['Feed', $feed ? $feed->label() : $this->t('Deleted feed')],
      ['GUID', $rss_item->get('guid')->value ?: $this->t('—')],
    ];

    if ($uri = $rss_item->getLinkUrl()) {
      $rows[] = ['Link', Link::fromTextAndUrl($uri, Url::fromUri($uri))];
    }
    else {
      $rows[] = ['Link', $this->t('—')];
    }

    $published = $rss_item->get('published_at')->value;
    $rows[] = ['Published', $published ? \Drupal::service('date.formatter')->format((int) $published, 'medium') : $this->t('—')];
    $rows[] = ['Imported', \Drupal::service('date.formatter')->format((int) ($rss_item->get('created')->value ?? 0), 'medium')];

    $build['metadata'] = [
      '#type' => 'details',
      '#title' => $this->t('Item details'),
      '#open' => FALSE,
      'table' => [
        '#type' => 'table',
        '#rows' => $rows,
        '#empty' => '',
      ],
    ];

    // The fetched feed content. Rendered through a text check_plain-safe
    // pipeline: the source is external HTML, so it must be sanitized.
    $description = (string) ($rss_item->get('description')->value ?? '');
    if (trim($description) !== '') {
      $build['content'] = [
        '#type' => 'details',
        '#title' => $this->t('Fetched content'),
        '#open' => TRUE,
        'body' => [
          '#markup' => $description,
          '#allowed_tags' => [
            'a', 'p', 'br', 'strong', 'em', 'b', 'i', 'u', 'ul', 'ol', 'li',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre',
            'img', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr',
            'th', 'td', 'hr', 'span', 'div', 'small', 'sup', 'sub',
          ],
        ],
      ];
    }
    else {
      $build['content'] = [
        '#markup' => $this->t('The source feed did not include content for this item.'),
      ];
    }

    return $build;
  }

  /**
   * Page title callback.
   */
  public function title(RssItem $rss_item): string {
    return (string) $rss_item->label();
  }

}
