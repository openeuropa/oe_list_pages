<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\query_type;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\query_type\SearchApiString;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\Plugin\facets\widget\HierarchicalMultiselectWidget;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides support for Hierarchy facets.
 *
 * @FacetsQueryType(
 *   id = "oe_list_pages_hierarchy_query_type",
 *   label = @Translation("List pages hierarchy"),
 * )
 */
class HierarchyQueryType extends SearchApiString implements ContainerFactoryPluginInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the HierarchyQueryType plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entityFieldManager, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * This is mostly copied from SearchApiString however there are a few
   * differences. We check if the operator is with or without hierarchy
   * and change the operator used in the query to use all values returned
   * in the hierarchy of the defined value.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function execute() {
    $query = $this->query;

    // Only alter the query when there's an actual query object to alter.
    if (empty($query)) {
      return;
    }
    $original_operator = $this->facet->getQueryOperator();
    $field_identifier = $this->facet->getFieldIdentifier();
    $exclude = $this->facet->getExclude();

    if ($query->getProcessingLevel() === QueryInterface::PROCESSING_FULL) {
      // Set the options for the actual query.
      $options = &$query->getOptions();
      $options['search_api_facets'][$field_identifier] = $this->getFacetOptions();
    }

    // Add the filter to the query if there are active values.
    $active_items = $this->facet->getActiveItems();

    if (count($active_items)) {
      // Convert the operator to use in case the equivalent operator
      // with hierarchy is used.
      if ($original_operator === HierarchicalMultiselectWidget::AND_WITH_HIERARCHY_OPERATOR) {
        $operator = ListPresetFilter::AND_OPERATOR;
      }
      elseif ($original_operator === HierarchicalMultiselectWidget::OR_WITH_HIERARCHY_OPERATOR) {
        $operator = ListPresetFilter::OR_OPERATOR;
      }
      elseif ($original_operator === HierarchicalMultiselectWidget::NONE_WITH_HIERARCHY_OPERATOR) {
        $operator = ListPresetFilter::NOT_OPERATOR;
      }
      else {
        $operator = $original_operator;
      }

      $filter = $query->createConditionGroup($operator, ['facet:' . $field_identifier]);
      foreach ($active_items as $value) {
        if (str_starts_with($value, '!(')) {
          /** @var \Drupal\facets\UrlProcessor\UrlProcessorInterface $url_processor */
          $url_processor = $this->facet->getProcessors()['url_processor_handler']->getProcessor();
          foreach (explode($url_processor->getDelimiter(), substr($value, 2, -1)) as $missing_value) {
            // Note that $exclude needs to be inverted for "missing".
            // Maintain original logic but alter the operator to use in case
            // we detect the operator is with hierarchy.
            if ($operator == $original_operator) {
              $filter->addCondition($this->facet->getFieldIdentifier(), $missing_value, !$exclude ? '<>' : '=');
            }
            else {
              $hierarchy_values = $this->getHierarchy($missing_value, $this->facet);
              if (empty($hierarchy_values)) {
                throw new SearchApiException("Invalid hierarchy query. The term itself was not found or the facet is incorrectly configured");
              }
              $filter->addCondition($this->facet->getFieldIdentifier(), $hierarchy_values, !$exclude ? 'NOT IN' : 'IN');
            }

          }
        }
        else {
          // Maintain original logic but alter the operator to use in case we
          // detect the operator is with hierarchy.
          if ($operator === $original_operator) {
            $filter->addCondition($this->facet->getFieldIdentifier(), $value, $exclude ? '<>' : '=');
          }
          else {
            $hierarchy_values = $this->getHierarchy($value, $this->facet);
            $filter->addCondition($this->facet->getFieldIdentifier(), $hierarchy_values, $exclude ? 'NOT IN' : 'IN');
          }
        }
      }
      $query->addConditionGroup($filter);
    }
  }

  /**
   * Gets the hierarchy for the related entity reference field.
   *
   * It only works if the referenced entity type has support for hierarchy.
   *
   * @param string $value
   *   The value to get the parents from.
   * @param \Drupal\facets\FacetInterface $facet
   *   The related facet.
   *
   * @return array
   *   The hierarchy
   */
  protected function getHierarchy(string $value, FacetInterface $facet): array {
    $facet_source = $facet->getFacetSource();
    $field = $facet_source->getIndex()->getField($facet->getFieldIdentifier());

    if (!$field instanceof FieldInterface) {
      return [];
    }

    // Finds the related entity type and bundle for this facet.
    $facet_source_id = $facet_source->getPluginId();
    $facet_parts = explode(':', $facet_source_id);
    if ($facet_parts[0] !== 'list_facet_source') {
      return [];
    }

    $field_name = $field->getOriginalFieldIdentifier();
    $property_path = $field->getPropertyPath();
    $parts = explode(':', $property_path);
    if (count($parts) > 1) {
      $field_name = $parts[0];
    }

    $field_definitions = $this->entityFieldManager->getFieldDefinitions($facet_parts[1], $facet_parts[2]);

    if (empty($field_definitions[$field_name])) {
      return [];
    }

    $field = $field_definitions[$field_name];

    // We only support entity reference fields.
    if (!in_array($field->getType(), [
      'skos_concept_entity_reference',
      'entity_reference',
    ])) {
      return [];
    }

    $target_entity_type_id = $field->getSettings()['target_type'];

    // Check if there is a hierarchy entity handler for this target entity type.
    if ($this->entityTypeManager->hasHandler($target_entity_type_id, 'oe_list_pages_hierarchy')) {
      $handler = $this->entityTypeManager->getHandler($target_entity_type_id, 'oe_list_pages_hierarchy');
      return $handler->getHierarchy($value);
    }

    return [];
  }

}
