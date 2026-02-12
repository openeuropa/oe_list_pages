<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Resolver for the sort options of a given list page configuration.
 */
class ListPageSortOptionsResolver {

  use StringTranslationTrait;

  /**
   * The configuration scope.
   *
   * Used for determining which sort options should be available when
   * configuring a list page.
   */
  public const SCOPE_CONFIGURATION = 'configuration';

  /**
   * The user scope.
   *
   * Used for determining which sort options should be available for users
   * in the frontend.
   */
  public const SCOPE_USER = 'user';

  /**
   * The system scope.
   *
   * Used for determining which sort options should be available always
   * because they may be used by the system.
   */
  public const SCOPE_SYSTEM = 'system';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new ListPageSortOptionsResolver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EventDispatcherInterface $eventDispatcher) {
    $this->entityTypeManager = $entityTypeManager;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Gets the available sort options.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The selected list source.
   * @param array $scopes
   *   The scopes of the sort options.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $context_entity
   *   The entity onto which we are building the list.
   *
   * @return array
   *   The sort options.
   */
  public function getSortOptions(ListSourceInterface $list_source, array $scopes = [self::SCOPE_CONFIGURATION], ?ContentEntityInterface $context_entity = NULL): array {
    $options = [];

    // Get the default sort option.
    $sort = $this->getBundleDefaultSort($list_source);
    if ($sort) {
      $options[static::generateSortMachineName($sort)] = $this->t('Default');
    }

    if (in_array(self::SCOPE_SYSTEM, $scopes)) {
      // Add also the Changed field because it may be used in various use cases
      // that are not necessarily exposed to editors or visitors.
      $changed = [
        'name' => 'changed',
        'direction' => 'DESC',
      ];
      $options[static::generateSortMachineName($changed)] = $this->t('Changed');
    }

    // Cycle through each scope and gather options specific to what was
    // requested.
    foreach ($scopes as $scope) {
      $event = new ListPageSortAlterEvent($list_source->getEntityType(), $list_source->getBundle());
      $event->setOptions($options);
      $event->setScope($scope);
      if ($context_entity) {
        $event->setContextEntity($context_entity);
      }
      $this->eventDispatcher->dispatch($event, ListPageEvents::ALTER_SORT_OPTIONS);

      $options = $event->getOptions();
    }

    return $options;
  }

  /**
   * Get the default sort configuration from the bundle.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The selected list source.
   *
   * @return array
   *   The sort information.
   */
  public function getBundleDefaultSort(ListSourceInterface $list_source): array {
    $default_sort = &drupal_static(__FUNCTION__ . $list_source->getEntityType() . $list_source->getBundle());
    if ($default_sort) {
      return $default_sort;
    }
    $bundle_entity_type = $this->entityTypeManager->getDefinition($list_source->getEntityType())->getBundleEntityType();
    if (!$bundle_entity_type) {
      // This can happen for bundle-less entity types like aggregator items.
      return [];
    }
    $storage = $this->entityTypeManager->getStorage($bundle_entity_type);
    $bundle = $storage->load($list_source->getBundle());
    $default_sort = $bundle->getThirdPartySetting('oe_list_pages', 'default_sort', []);
    return $default_sort;
  }

  /**
   * Determines if exposing the sort is allowed for a list source.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The selected list source.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $context_entity
   *   The entity onto which we are building the list.
   *
   * @return bool
   *   Whether the sort is allowed.
   */
  public function isExposedSortAllowed(ListSourceInterface $list_source, ?ContentEntityInterface $context_entity = NULL): bool {
    $event = new ListPageDisallowSortEvent($list_source->getEntityType(), $list_source->getBundle());
    if ($context_entity) {
      $event->setContextEntity($context_entity);
    }
    $this->eventDispatcher->dispatch($event, ListPageEvents::DISALLOW_EXPOSED_SORT);
    return !$event->isDisallowed();
  }

  /**
   * Given a sort array with "name" and "direction", generate a machine name.
   *
   * @param array $sort
   *   The sort information.
   *
   * @return string
   *   The machine name.
   */
  public static function generateSortMachineName(array $sort): string {
    return $sort['name'] . '__' . strtoupper($sort['direction']);
  }

}
