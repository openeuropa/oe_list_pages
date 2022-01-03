<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_link_list_source\EventSubscriber;

use Drupal\oe_list_pages\ListQueryOptionsInterface;
use Drupal\oe_list_pages\ListSourceFactoryInterface;
use Drupal\oe_list_pages\ListSourceInterface;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an event subscriber that allows to alter list source queries.
 */
class QuerySubscriber implements EventSubscriberInterface {

  /**
   * The list source factory.
   *
   * @var \Drupal\oe_list_pages\ListSourceFactoryInterface
   */
  protected $listSourceFactory;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\oe_list_pages\ListSourceFactoryInterface $list_source_factory
   *   The list source factory.
   */
  public function __construct(ListSourceFactoryInterface $list_source_factory) {
    $this->listSourceFactory = $list_source_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => ['queryAlter', 10],
    ];
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function queryAlter(QueryPreExecuteEvent $event) {
    $query = $event->getQuery();

    if (!$query->getIndex()->getServerInstance()->supportsFeature('search_api_facets')) {
      return;
    }

    $source_id = $query->getSearchId();
    if (strpos($source_id, 'list_facet_source') === FALSE) {
      return;
    }

    $query_options = $query->getOption('oe_list_page_query_options');
    if (!$query_options instanceof ListQueryOptionsInterface) {
      return;
    }

    $extra = $query_options->getExtra();
    if (!$extra || !isset($extra['exclude_self_data'])) {
      return;
    }

    $exclude_self = $extra['exclude_self_data'];
    $id = $exclude_self['id'];
    $entity_type = $exclude_self['entity_type'];
    $entity_bundle = $exclude_self['entity_bundle'];

    $index = $query->getIndex();
    // We have to assume a common field name for the ID.
    if (!$index->getField('list_page_link_source_id')) {
      return;
    }

    $exclude_list_source = $this->listSourceFactory->get($entity_type, $entity_bundle);
    if (!$exclude_list_source instanceof ListSourceInterface || $exclude_list_source->getSearchId() !== $source_id) {
      // We don't want to apply a filter if the entity we need to filter out
      // doesn't belong to the list that is being queried. This is to prevent,
      // for example, Node ID 5 to filter to be excluded in a list of Media
      // which may contain ID 5 and should not be filtered out.
      return;
    }

    $query->addCondition('list_page_link_source_id', $id, '<>');
  }

}
