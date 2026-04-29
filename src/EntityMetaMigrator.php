<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\emr\Entity\EntityMeta;
use Drupal\emr\Entity\EntityMetaInterface;

/**
 * Helper to migrate the oe_list_page entity meta to node fields.
 */
class EntityMetaMigrator {

  /**
   * Migrates an oe_list_page entity meta to node fields.
   *
   * @param \Drupal\emr\Entity\EntityMetaInterface $entity_meta
   *   The entity meta.
   */
  public static function migrateOeListPageEntityMeta(EntityMetaInterface $entity_meta): void {
    static::doTranslationAwareMultiFieldMigrate($entity_meta, [
      [
        'meta_field' => 'oe_list_page_source',
        'node_field' => 'oe_list_page_source',
        'columns' => ['value'],
      ],
      [
        'meta_field' => 'oe_list_page_config',
        'node_field' => 'oe_list_page_config',
        'columns' => ['value'],
      ],
    ]);
  }

  /**
   * Loads a given entity meta by ID.
   *
   * For metas that lost their default revision, fall back to loading via a
   * revision id.
   *
   * @param string|int $id
   *   The ID.
   *
   * @return \Drupal\emr\Entity\EntityMetaInterface|null
   *   The entity meta, or NULL if it cannot be loaded.
   */
  public static function loadEntityMeta(string|int $id): ?EntityMetaInterface {
    $entity_meta = EntityMeta::load($id);
    if ($entity_meta) {
      return $entity_meta;
    }

    /** @var \Drupal\emr\EntityMetaStorageInterface $meta_storage */
    $meta_storage = \Drupal::entityTypeManager()->getStorage('entity_meta');
    $revision_id = \Drupal::database()->query("SELECT revision_id FROM entity_meta_revision WHERE id = :id LIMIT 1", [
      ':id' => $id,
    ])->fetchField();
    if (!$revision_id) {
      return NULL;
    }
    return $meta_storage->loadRevision($revision_id);
  }

  /**
   * Migrates one or more fields, walking every revision and translation.
   *
   * @param \Drupal\emr\Entity\EntityMetaInterface $entity_meta
   *   The entity meta.
   * @param array $field_map
   *   The field map.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected static function doTranslationAwareMultiFieldMigrate(EntityMetaInterface $entity_meta, array $field_map): void {
    $meta_storage = \Drupal::entityTypeManager()->getStorage('entity_meta');
    $meta_storage->resetCache();

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node_storage->resetCache();

    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('entity_meta', $entity_meta->bundle());
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $mapping */
    $mapping = \Drupal::entityTypeManager()->getStorage('entity_meta')->getTableMapping();

    $related = $meta_storage->getRelatedEntities($entity_meta);
    if (!$related) {
      $entity_meta->delete();
      return;
    }
    $node = reset($related);
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $default_revision_id = $node->getRevisionId();
    $revision_ids = \Drupal::database()->query(
      'SELECT [vid] FROM {node_field_revision} WHERE [nid] = :nid ORDER BY [vid]',
      [':nid' => $node->id()]
    )->fetchCol();

    $revision_tables = [];
    $node_field_columns = [];
    foreach ($field_map as $spec) {
      $meta_field = $spec['meta_field'];
      $node_field = $spec['node_field'];
      $revision_tables[$meta_field] = $mapping->getDedicatedRevisionTableName($definitions[$meta_field]->getFieldStorageDefinition());
      $value_cols = [];
      foreach ($spec['columns'] as $c) {
        $value_cols[] = $node_field . '_' . $c;
      }
      $node_field_columns[$node_field] = array_merge(
        ['bundle', 'deleted', 'entity_id', 'revision_id', 'langcode', 'delta'],
        $value_cols,
      );
    }

    $revision_values = [];
    $field_values = [];

    foreach (array_reverse($revision_ids) as $revision_id) {
      foreach ($field_map as $spec) {
        $meta_field = $spec['meta_field'];
        $node_field = $spec['node_field'];
        $columns = $spec['columns'];

        $rows = static::queryAllTranslationsForRevisionAndField(
          $entity_meta, $revision_id, $revision_tables[$meta_field], $meta_field, $columns,
        );
        foreach ($rows as $row) {
          $entry = [
            'bundle' => $node->bundle(),
            'deleted' => 0,
            'entity_id' => $node->id(),
            'revision_id' => $revision_id,
            'langcode' => $row->langcode,
            'delta' => 0,
          ];
          foreach ($columns as $col) {
            $entry[$node_field . '_' . $col] = $row->{$meta_field . '_' . $col};
          }
          if ($revision_id == $default_revision_id) {
            $field_values[$node_field][] = $entry;
          }
          $revision_values[$node_field][] = $entry;
        }
      }
    }

    $connection = \Drupal::database();
    $transaction = $connection->startTransaction();

    try {
      foreach ($field_map as $spec) {
        $node_field = $spec['node_field'];
        $col_list = $node_field_columns[$node_field];
        if (!empty($field_values[$node_field])) {
          $insert = $connection->insert('node__' . $node_field)->fields($col_list);
          foreach ($field_values[$node_field] as $values) {
            $insert->values($values);
          }
          $insert->execute();
        }
        if (!empty($revision_values[$node_field])) {
          $insert = $connection->insert('node_revision__' . $node_field)->fields($col_list);
          foreach ($revision_values[$node_field] as $values) {
            $insert->values($values);
          }
          $insert->execute();
        }
      }

      $entity_meta->delete();
      \Drupal::cache('entity')->delete('values:node:' . $node->id());
    }
    catch (\Exception $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    $transaction->__destruct();
  }

  /**
   * Queries all translation rows for a meta field at a given node revision.
   *
   * @param \Drupal\emr\Entity\EntityMetaInterface $entity_meta
   *   The meta.
   * @param string $revision_id
   *   The node revision ID.
   * @param string $meta_revision_table
   *   The meta field revision table name.
   * @param string $meta_field_name
   *   The field name on the meta.
   * @param array $column_suffixes
   *   The column suffixes to select.
   *
   * @return array
   *   Array of objects, one per langcode.
   */
  protected static function queryAllTranslationsForRevisionAndField(EntityMetaInterface $entity_meta, string $revision_id, string $meta_revision_table, string $meta_field_name, array $column_suffixes): array {
    $select_cols = [];
    foreach ($column_suffixes as $c) {
      $select_cols[] = $meta_revision_table . '.' . $meta_field_name . '_' . $c;
    }
    $select = $meta_revision_table . '.langcode, ' . implode(', ', $select_cols);

    $query = \Drupal::database()->query("SELECT DISTINCT $select
    FROM
    entity_meta_relation_revision base_table
    INNER JOIN entity_meta_relation_revision__emr_node_revision entity_meta_relation_revision__emr_node_revision ON entity_meta_relation_revision__emr_node_revision.revision_id = base_table.revision_id
    INNER JOIN entity_meta_relation_revision__emr_meta_revision entity_meta_relation_revision__emr_meta_revision ON entity_meta_relation_revision__emr_meta_revision.revision_id = base_table.revision_id
    INNER JOIN entity_meta entity_meta ON entity_meta.id = entity_meta_relation_revision__emr_meta_revision.emr_meta_revision_target_id
    INNER JOIN " . $meta_revision_table . " " . $meta_revision_table . " ON " . $meta_revision_table . ".revision_id = entity_meta_relation_revision__emr_meta_revision.emr_meta_revision_target_revision_id
    WHERE entity_meta_relation_revision__emr_node_revision.emr_node_revision_target_revision_id = :revision_id AND entity_meta.bundle = :meta_bundle AND entity_meta.id = :meta_id", [
      ':revision_id' => $revision_id,
      ':meta_bundle' => $entity_meta->bundle(),
      ':meta_id' => $entity_meta->id(),
    ]);

    return $query->fetchAll();
  }

}
