<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface;
use Drupal\search_api\Query\ResultSetInterface;

/**
 * Processes Search API results to apply promotion (show specific items first).
 *
 * This processor re-orders search results based on "promotion" configuration.
 * Items matching promoted values appear first in the specified order,
 * followed by remaining items in their original sort order.
 *
 * Example: For a Category field with values [A, B, C, D] sorted ASC:
 * - Without promotion: A, B, C, D
 * - With promotion [C, A]: C, A, B, D (promoted first, then others)
 */
class CustomSortProcessor {

  /**
   * Processes results to apply promotion (show promoted items first).
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The Search API result set.
   * @param array $promotion
   *   Promotion settings with:
   *   - enabled: bool
   *   - values: array of {field, value, weight}
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   The result set with promoted items first.
   */
  public function processResults(ResultSetInterface $results, array $promotion): ResultSetInterface {
    if (empty($promotion['enabled']) || empty($promotion['values'])) {
      return $results;
    }

    $items = $results->getResultItems();

    if (empty($items)) {
      return $results;
    }

    // Filter out promotion values without field.
    $valid_promotion_values = array_filter($promotion['values'], function ($pv) {
      return !empty($pv['field']) && isset($pv['value']) && $pv['value'] !== '';
    });

    if (empty($valid_promotion_values)) {
      return $results;
    }

    // Ensure fields are extracted from each item.
    foreach ($items as $item) {
      // Force field extraction by getting all fields.
      $item->getFields(TRUE);
    }

    $items = $this->sortByPromotedValues($items, $valid_promotion_values);

    // Update the result set with reordered items.
    $results->setResultItems($items);

    return $results;
  }

  /**
   * Reorders items to show promoted values first, keeping others in order.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $items
   *   The search result items (already sorted by Search API).
   * @param array $promoted_values
   *   Array of promoted values with 'field', 'value', and 'weight' keys.
   *
   * @return \Drupal\search_api\Item\ItemInterface[]
   *   Items reordered: promoted first (by weight), then others (original order).
   */
  protected function sortByPromotedValues(array $items, array $promoted_values): array {
    // Build a map of field+value combinations to their weights.
    // Lower weight = higher priority (appears first).
    $promoted_map = [];
    foreach ($promoted_values as $pv) {
      $field = $pv['field'] ?? '';
      $value = (string) ($pv['value'] ?? '');
      if (!empty($field) && $value !== '') {
        $key = $field . '::' . $value;
        $promoted_map[$key] = $pv['weight'] ?? 0;
      }
    }

    // Separate items into promoted and non-promoted, preserving order.
    $promoted_items = [];
    $other_items = [];

    foreach ($items as $item_id => $item) {
      $matched_weight = NULL;

      // Check each promoted field+value combination.
      foreach ($promoted_values as $pv) {
        $field = $pv['field'] ?? '';
        $expected_value = trim((string) ($pv['value'] ?? ''));
        if (empty($field) || $expected_value === '') {
          continue;
        }

        $field_value = $this->getFieldValue($item, $field);
        $field_value_str = $field_value !== NULL ? trim((string) $field_value) : '';

        if ($field_value_str === $expected_value) {
          $key = $field . '::' . $expected_value;
          $weight = $promoted_map[$key] ?? 0;
          // Use the lowest weight if multiple promotions match.
          if ($matched_weight === NULL || $weight < $matched_weight) {
            $matched_weight = $weight;
          }
        }
      }

      if ($matched_weight !== NULL) {
        $promoted_items[] = [
          'item_id' => $item_id,
          'item' => $item,
          'weight' => $matched_weight,
        ];
      }
      else {
        $other_items[$item_id] = $item;
      }
    }

    // Sort promoted items by their weight.
    usort($promoted_items, fn($a, $b) => $a['weight'] <=> $b['weight']);

    // Build final result: promoted items first, then others.
    $sorted_items = [];
    foreach ($promoted_items as $entry) {
      $sorted_items[$entry['item_id']] = $entry['item'];
    }
    foreach ($other_items as $item_id => $item) {
      $sorted_items[$item_id] = $item;
    }

    return $sorted_items;
  }

  /**
   * Gets the field value from a Search API item.
   *
   * First tries to get the value from the Search API item's indexed fields.
   * If not available, falls back to loading the original entity.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The search result item.
   * @param string $field_name
   *   The field name (Search API field identifier).
   *
   * @return mixed
   *   The field value as a scalar, or NULL if not found.
   */
  protected function getFieldValue(ItemInterface $item, string $field_name) {
    // Always load from the entity for reliability.
    // Search API field extraction can be inconsistent across backends.
    $value = $this->getFieldValueFromEntity($item, $field_name);
    if ($value !== NULL) {
      return $value;
    }

    // Fallback to Search API item's fields.
    $field = $item->getField($field_name);
    if ($field) {
      $values = $field->getValues();
      if (!empty($values)) {
        $value = reset($values);

        // Handle TextValue objects (used for fulltext fields like title).
        if ($value instanceof TextValueInterface) {
          return $value->getText();
        }

        // Handle other object types that might have a string representation.
        if (is_object($value) && method_exists($value, '__toString')) {
          return (string) $value;
        }

        return $value;
      }
    }

    return NULL;
  }

  /**
   * Gets a field value by loading the original entity.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The search result item.
   * @param string $field_name
   *   The Search API field identifier.
   *
   * @return mixed
   *   The field value, or NULL if not found.
   */
  protected function getFieldValueFromEntity(ItemInterface $item, string $field_name) {
    try {
      $original_object = $item->getOriginalObject();
      if (!$original_object) {
        return NULL;
      }

      $entity = $original_object->getValue();
      if (!$entity instanceof \Drupal\Core\Entity\FieldableEntityInterface) {
        return NULL;
      }

      // Get the property path from the index field to find the entity field.
      $index = $item->getIndex();
      $index_field = $index->getField($field_name);
      if (!$index_field) {
        return NULL;
      }

      $property_path = $index_field->getPropertyPath();
      // Get the base field name (before any ':' for nested properties).
      $entity_field_name = explode(':', $property_path)[0];

      if (!$entity->hasField($entity_field_name)) {
        return NULL;
      }

      $entity_field = $entity->get($entity_field_name);
      if ($entity_field->isEmpty()) {
        return NULL;
      }

      // For entity label (title), use the label() method directly.
      if ($entity_field_name === 'title' && $entity instanceof \Drupal\Core\Entity\EntityInterface) {
        return $entity->label();
      }

      // Try to get the value - handle different field types.
      $first_item = $entity_field->first();
      if (!$first_item) {
        return NULL;
      }

      // Get the main property name for this field type.
      $main_property = $entity_field->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getMainPropertyName();

      if ($main_property) {
        try {
          $property = $first_item->get($main_property);
          if ($property) {
            $value = $property->getValue();
            // Handle cases where getValue returns an array.
            if (is_array($value) && isset($value['value'])) {
              return $value['value'];
            }
            return $value;
          }
        }
        catch (\InvalidArgumentException $e) {
          // Property doesn't exist, try common alternatives.
        }
      }

      // Try common property names as fallback.
      foreach (['value', 'target_id', 'uri'] as $property_name) {
        try {
          $property = $first_item->get($property_name);
          if ($property) {
            $value = $property->getValue();
            if (is_array($value) && isset($value['value'])) {
              return $value['value'];
            }
            return $value;
          }
        }
        catch (\InvalidArgumentException $e) {
          // Property doesn't exist, continue.
        }
      }
    }
    catch (\Exception $e) {
      // Log the exception for debugging.
      \Drupal::logger('oe_list_pages')->warning('Error getting field value for promotion: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

}
