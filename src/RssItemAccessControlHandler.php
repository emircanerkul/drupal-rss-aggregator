<?php

namespace Drupal\rss_aggregator;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the rss_item entity.
 *
 * Items are imported content: nobody creates or edits them through the UI, so
 * only view and delete are granted, each via its own permission.
 */
class RssItemAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view rss item');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete rss item');

      default:
        return AccessResult::allowedIfHasPermission($account, 'administer rss importer');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer rss importer');
  }

}
