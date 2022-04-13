<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_sort\EventSubscriber;

use Drupal\oe_list_pages\ListPageEvents;
use Drupal\oe_list_pages\ListPageSortAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an event subscriber.
 */
class SortSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ListPageEvents::ALTER_SORT_OPTIONS => 'sortAlter',
    ];
  }

  /**
   * Sort Alter.
   */
  public function sortAlter(ListPageSortAlterEvent $event) {
    /** @var \Drupal\node\NodeInterface $list_page */
    $list_page = $form_state->getFormObject()->getEntity();
    $configuration = ListPageConfiguration::fromEntity($list_page);
    $configuration->setSort([]);

    /** @var \Drupal\emr\Entity\EntityMetaInterface $list_page_entity_meta */
    $list_page_entity_meta = $list_page->get('emr_entity_metas')->getEntityMeta('oe_list_page');
    /** @var \Drupal\oe_list_pages\ListPageWrapper $list_page_entity_meta_wrapper */
    $list_page_entity_meta_wrapper = $list_page_entity_meta->getWrapper();
    $list_page_entity_meta_wrapper->setConfiguration(['sort' => $configuration->getSort()]);
    $list_page->get('emr_entity_metas')->attach($list_page_entity_meta);
    $list_page->save();
  }

}
