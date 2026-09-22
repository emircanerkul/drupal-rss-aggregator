<?php

namespace Drupal\rss_aggregator\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the RSS item content entity.
 *
 * One entity per imported feed entry. Items are created exclusively by the
 * "rss_aggregator_items" queue worker and deduplicated by their hash.
 *
 * @ContentEntityType(
 *   id = "rss_item",
 *   label = @Translation("RSS item"),
 *   base_table = "rss_item",
 *   handlers = {
 *     "list_builder" = "Drupal\rss_aggregator\RssItemListBuilder",
 *     "access" = "Drupal\rss_aggregator\RssItemAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\rss_aggregator\Entity\Routing\RssItemHtmlRouteProvider"
 *     },
 *     "views_data" = "Drupal\rss_aggregator\RssItemViewsData",
 *     "form" = {
 *       "delete" = "Drupal\rss_aggregator\Form\RssItemDeleteForm"
 *     }
 *   },
 *   admin_permission = "administer rss importer",
 *   label_collection = @Translation("RSS items"),
 *   label_singular = @Translation("rss item"),
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "title",
 *     "created" = "created"
 *   },
 *   unique_keys = {
 *     "hash" = {"hash"}
 *   },
 *   links = {
 *     "canonical" = "/admin/content/rss-items/{rss_item}",
 *     "delete-form" = "/admin/content/rss-items/{rss_item}/delete",
 *     "collection" = "/admin/content/rss-items"
 *   }
 * )
 */
class RssItem extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setReadOnly(TRUE);

    $fields['feed_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Feed ID'))
      ->setDescription(t('The machine name of the rss_feed config entity this item was imported from.'))
      ->setSetting('max_length', 64);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setSetting('max_length', 255);

    $fields['link'] = BaseFieldDefinition::create('link')
      ->setLabel(t('Link'));

    $fields['guid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('GUID'))
      ->setSetting('max_length', 255);

    $fields['hash'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Hash'))
      ->setDescription(t('SHA-1 hash used for deduplication.'))
      ->setSetting('max_length', 64);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'));

    $fields['published_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Published at'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Imported on'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $title = (string) ($this->get('title')->value ?? '');
    return $title !== '' ? $title : (string) $this->t('Untitled RSS item');
  }

  /**
   * Returns the machine name of the feed this item belongs to.
   */
  public function getFeedId(): string {
    return (string) ($this->get('feed_id')->value ?? '');
  }

  /**
   * Returns the external link URI of the item, if any.
   */
  public function getLinkUrl(): ?string {
    if ($link = $this->get('link')) {
      return $link->uri ?: NULL;
    }
    return NULL;
  }

}
