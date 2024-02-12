<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_filters_test\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_list_pages_link_list_source\ContextualAwareProcessorInterface;
use Drupal\oe_list_pages_link_list_source\ContextualPresetFilter;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;

/**
 * Test custom Search API field.
 *
 * @SearchApiProcessor(
 *   id = "oe_list_pages_filters_test_test_field",
 *   label = @Translation("Foo fake field"),
 *   description = @Translation("A fake field to base a facet on."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class FooFakeField extends ProcessorPluginBase implements ContextualAwareProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Foo'),
        'description' => $this->t('Foo field.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => FALSE,
      ];
      $properties['oe_list_pages_filters_test_foo_field'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    try {
      $entity = $item->getOriginalObject()->getValue();
    }
    catch (SearchApiException $e) {
      return;
    }
    if (!($entity instanceof ContentEntityInterface)) {
      return;
    }

    $fields = $item->getFields();

    $fields = $this->getFieldsHelper()->filterForPropertyPath($fields, NULL, 'oe_list_pages_filters_test_foo_field');

    foreach ($fields as $field) {
      // We set the entity ID as the field value.
      $field->addValue($entity->id());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getContextualValues(ContentEntityInterface $entity, string $source = ContextualPresetFilter::FILTER_SOURCE_FIELD_VALUES): array {
    // Check if the entity has a test contextual field that we can return
    // a value from. Otherwise default to the entity ID.
    if ($entity->hasField('field_test_contextual_filter')) {
      return array_column($entity->get('field_test_contextual_filter')->getValue(), 'value');
    }

    if ($source === ContextualPresetFilter::FILTER_SOURCE_FIELD_VALUES) {
      return [];
    }

    return [$entity->id()];
  }

}
