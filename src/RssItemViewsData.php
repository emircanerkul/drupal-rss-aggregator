<?php

namespace Drupal\rss_aggregator;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\views\EntityViewsData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Views integration for the rss_item entity.
 */
class RssItemViewsData extends EntityViewsData {

  public function __construct(
    EntityTypeInterface $entity_type,
    $storage_controller,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    TranslationInterface $translation_manager,
    EntityFieldManagerInterface $entity_field_manager,
    protected ?ModuleExtensionList $moduleExtensionList = NULL,
  ) {
    parent::__construct($entity_type, $storage_controller, $entity_type_manager, $module_handler, $translation_manager, $entity_field_manager);
    if ($this->moduleExtensionList === NULL) {
      $this->moduleExtensionList = \Drupal::service('extension.list.module');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('string_translation'),
      $container->get('entity_field.manager'),
      $container->get('extension.list.module')
    );
  }

}
