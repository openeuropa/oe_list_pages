<?php

/**
 * @file
 * OE List Pages Link List Source post updates.
 */

declare(strict_types = 1);

use Drupal\oe_link_lists\Entity\LinkList;

/**
 * Updates link lists contextual preset filters to use the new object.
 */
function oe_list_pages_link_list_source_post_update_0001(&$sandbox) {
  if (!isset($sandbox['total'])) {
    $link_list_ids = \Drupal::entityTypeManager()->getStorage('link_list')->getQuery()
      ->accessCheck(FALSE)
      ->addTag('allow_local_link_lists')
      ->execute();

    $sandbox['link_list_ids'] = array_unique($link_list_ids);
    $sandbox['total'] = count($sandbox['link_list_ids']);
    $sandbox['current'] = 0;
    $sandbox['updated'] = 0;
  }

  /** @var \Drupal\oe_list_pages_link_list_source\ContextualFiltersUpdater $updater */
  $updater = \Drupal::service('oe_list_pages_link_list_source.contextual_filters_updater');

  if ($sandbox['link_list_ids']) {
    $id = array_pop($sandbox['link_list_ids']);
    $link_list = LinkList::load($id);
    $updated = $updater->updateLinkList($link_list);
    if ($updated) {
      $sandbox['updated']++;
    }
  }

  $sandbox['current']++;
  $sandbox['#finished'] = empty($sandbox['total']) ? 1 : ($sandbox['current'] / $sandbox['total']);

  if ($sandbox['#finished'] === 1) {
    return t('A total of @link_lists link lists been updated with the new default filter values.', [
      '@link_lists' => $sandbox['updated'],
    ]);
  }
}
