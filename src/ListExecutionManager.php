<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * ListManager constructor.
   *
   * @param \Drupal\oe_list_pages\ListSourceFactoryInterface $listSourceFactory
   *   The list source factory.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(ListSourceFactoryInterface $listSourceFactory, EntityTypeManager $entityTypeManager, RequestStack $requestStack) {
    $this->listSourceFactory = $listSourceFactory;
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public function executeList($entity): ?ListExecution {
    // The number of items to show on a page.
    // @todo take this value from the list_page meta plugin.
    $limit = 10;
    /** @var \Drupal\emr\Entity\EntityMetaInterface $entity_meta */
    $entity_meta = $entity->get('emr_entity_metas')->getEntityMeta('oe_list_page');
    /** @var \Drupal\oe_list_pages\ListPageWrapper $wrapper */
    $wrapper = $entity_meta->getWrapper();
    $list_source = $this->listSourceFactory->get($wrapper->getSourceEntityType(), $wrapper->getSourceEntityBundle());
    if (!$list_source) {
      return NULL;
    }

    // Determine the query options and execute it.
    $bundle_entity_type = $this->entityTypeManager->getDefinition($wrapper->getSourceEntityType())->getBundleEntityType();
    $storage = $this->entityTypeManager->getStorage($bundle_entity_type);
    $bundle = $storage->load($wrapper->getSourceEntityBundle());
    $sort = $bundle->getThirdPartySetting('ewcms_site_tree', 'site_tree_pool_sort', []);
    $current_page = (int) $this->requestStack->getCurrentRequest()->get('page', 0);
    $sort = $sort ? [$sort['name'] => $sort['direction']] : [];
    $query = $list_source->getQuery($limit, $current_page, $sort);
    $result = $query->execute();
    $listExecution = new ListExecution($query, $result, $list_source, $wrapper);
    return $listExecution;
  }

}
