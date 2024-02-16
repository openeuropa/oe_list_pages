<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_filters_test\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;

/**
 * Test custom Search API field.
 *
 * @SearchApiProcessor(
 *   id = "oe_list_pages_filters_test_test_field_no_contextual",
 *   label = @Translation("Foo fake field - no contextual"),
 *   description = @Translation("A fake field to base a facet on."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class FooFakeFieldNoContextual extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Foo - no contextual'),
        'description' => $this->t('Foo field - no contextual.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => FALSE,
      ];
      $properties['oe_list_pages_filters_test_foo_field_no_contextual'] = new ProcessorProperty($definition);
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

    $fields = $this->getFieldsHelper()->filterForPropertyPath($fields, NULL, 'oe_list_pages_filters_test_foo_field_no_contextual');

    foreach ($fields as $field) {
      // We set the entity ID as the field value.
      $field->addValue($entity->id());
    }
  }

}
