<?php

namespace Drupal\rss_aggregator\Entity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * HTML route provider that routes the canonical URL to the custom controller.
 *
 * The rss_item entity is bundle-less with no configurable view modes, so the
 * default "_entity_view" canonical route renders an empty page. The canonical
 * route is wired to RssItemViewController instead.
 */
class RssItemHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('canonical')) {
      $route = (new Route($entity_type->getLinkTemplate('canonical')))
        ->addDefaults([
          '_controller' => 'Drupal\rss_aggregator\Controller\RssItemViewController::view',
          '_title_callback' => 'Drupal\rss_aggregator\Controller\RssItemViewController::title',
        ])
        ->setRequirement('_entity_access', 'rss_item.view')
        ->setRequirement('rss_item', '\d+')
        ->setOption('_admin_route', TRUE)
        ->setOption('parameters', [
          'rss_item' => ['type' => 'entity:rss_item'],
        ]);
      return $route;
    }
  }

}
