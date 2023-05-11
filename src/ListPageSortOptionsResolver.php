<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

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
   */
  public const SCOPE_CONFIGURATION = 'configuration';

  /**
   * The user scope.
   */
  public const SCOPE_USER = 'user';

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
   * @param string $scope
   *   The scope of the sort options.
   *
   * @return array
   *   The sort options.
   */
  public function getSortOptions(ListSourceInterface $list_source, string $scope = self::SCOPE_CONFIGURATION): array {
    $options = [];

    // Get the default sort option.
    $sort = $this->getBundleDefaultSort($list_source);
    if ($sort) {
      $options[static::generateSortMachineName($sort)] = $this->t('Default');
    }

    $event = new ListPageSortAlterEvent($list_source->getEntityType(), $list_source->getBundle());
    $event->setOptions($options);
    $event->setScope($scope);
    $this->eventDispatcher->dispatch($event, ListPageEvents::ALTER_SORT_OPTIONS);

    return $event->getOptions();
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
   *
   * @return bool
   *   Whether the sort is allowed.
   */
  public function isExposedSortAllowed(ListSourceInterface $list_source): bool {
    $event = new ListPageDisallowSortEvent($list_source->getEntityType(), $list_source->getBundle());
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
