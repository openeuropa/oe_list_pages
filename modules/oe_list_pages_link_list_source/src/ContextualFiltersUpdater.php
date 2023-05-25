<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_link_list_source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_link_lists\Entity\LinkListInterface;

/**
 * Updates a link list configuration to the new contextual filter preset object.
 */
class ContextualFiltersUpdater {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ContextualFiltersUpdater.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Updates a link list config.
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface $link_list
   *   The link list.
   *
   * @return bool
   *   Whether it needed an update or not.
   */
  public function updateLinkList(LinkListInterface $link_list): bool {
    $ids = $this->entityTypeManager->getStorage('link_list')->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->addTag('allow_local_link_lists')
      ->condition('id', $link_list->id())
      ->sort('vid', 'ASC')
      ->execute();

    $revisions = $this->entityTypeManager
      ->getStorage('link_list')
      ->loadMultipleRevisions(array_keys($ids));

    $updated = FALSE;
    foreach ($revisions as $revision) {
      $was_updated = $this->updateLinkListRevision($revision);
      if ($was_updated) {
        $updated = TRUE;
      }
    }

    return $updated;
  }

  /**
   * Performs the update on a link list revision.
   *
   * @param \Drupal\oe_link_lists\Entity\LinkListInterface $link_list
   *   The link list.
   *
   * @return bool
   *   Whether it needed an update or not.
   */
  protected function updateLinkListRevision(LinkListInterface $link_list): bool {
    $update = FALSE;
    $configuration = $link_list->getConfiguration();
    if ($configuration['source']['plugin'] !== 'list_pages') {
      return FALSE;
    }

    /** @var \Drupal\oe_list_pages\ListPresetFilter[] $contextual_filters */
    $contextual_filters = &$configuration['source']['plugin_configuration']['contextual_filters'];
    if (!$contextual_filters) {
      // No update necessary as we don't have filters.
      return FALSE;
    }

    foreach ($contextual_filters as $filter_id => $filter) {
      unset($contextual_filters[$filter_id]);
      $new_filter = new ContextualPresetFilter($filter->getFacetId(), $filter->getValues(), $filter->getOperator());
      $contextual_filters[$filter_id] = $new_filter;
      $update = TRUE;
    }

    if ($update) {
      $link_list->setConfiguration($configuration);
      $link_list->setNewRevision(FALSE);
      $link_list->save();
    }

    return $update;
  }

}
