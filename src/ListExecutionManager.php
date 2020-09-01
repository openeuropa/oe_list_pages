<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

/**
 * Handles the list pages execution.
 */
class ListExecutionManager implements ListExecutionManagerInterface {

  /**
   * The list source factory.
   *
   * @var \Drupal\oe_list_pages\ListSourceFactoryInterface
   */
  protected $listSourceFactory;

  /**
   * ListManager constructor.
   *
   * @param \Drupal\oe_list_pages\ListSourceFactoryInterface $listSourceFactory
   *   The list source factory.
   */
  public function __construct(ListSourceFactoryInterface $listSourceFactory) {
    $this->listSourceFactory = $listSourceFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function executeList($entity): ListExecution {
    // The number of items to show on 1 page.
    $page = 10;
    /** @var \Drupal\emr\Entity\EntityMetaInterface $entity_meta */
    $entity_meta = $entity->get('emr_entity_metas')->getEntityMeta('oe_list_page');
    /** @var \Drupal\oe_list_pages\ListPageWrapper $wrapper */
    $wrapper = $entity_meta->getWrapper();
    $list_source = $this->listSourceFactory->get($wrapper->getSourceEntityType(), $wrapper->getSourceEntityBundle());
    $query = $list_source->getQuery($page);
    $result = $query->execute();
    $listExecution = new ListExecution($query, $result, $list_source, $wrapper);

    return $listExecution;
  }

}
