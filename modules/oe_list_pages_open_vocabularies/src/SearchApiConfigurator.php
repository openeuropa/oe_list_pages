<?php

namespace Drupal\oe_list_pages_open_vocabularies;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;
use Drupal\oe_list_pages\ListSourceFactoryInterface;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\FieldInterface;

/**
 * Configure search api when vocabulary associations are created.
 */
class SearchApiConfigurator {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The list source factory.
   *
   * @var \Drupal\oe_list_pages\ListSourceFactoryInterface
   */
  protected $listSourceFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ListSourceFactoryInterface $listSourceFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->listSourceFactory = $listSourceFactory;
  }

  /**
   * Updates the search api configuration.
   *
   * It adds a search index field and facet for an open vocabulary association.
   *
   * @param \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association
   *   The open vocabulary association.
   * @param string $field_id
   *   The field_id.
   */
  public function updateConfig(OpenVocabularyAssociationInterface $association, string $field_id): void {
    // Based on the list source, determine the correct index to create the
    // field in.
    $field_config = $this->entityTypeManager->getStorage('field_config')->load($field_id);
    $entity_type = $field_config->getTargetEntityTypeId();
    $bundle = $field_config->getTargetBundle();
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $this->listSourceFactory->get($entity_type, $bundle);
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $list_source->getIndex();

    // Generate the field and facet id.
    $id = 'open_vocabularies_' . str_replace('.', '_', $association->id() . '_' . $field_id);

    // Create the field.
    $index_field = $this->getField($index, $id);
    $index_field->setType('string');
    $index_field->setPropertyPath($field_config->getFieldStorageDefinition()->getName() . ':target_id');
    $index_field->setDatasourceId('entity:' . $entity_type);
    $index_field->setLabel($association->label());
    $index->save();

    // Create the facet for the new field.
    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = $this->getFacet($id);
    $facet->setUrlAlias(str_replace('.', '_', $association->id()));
    $facet->set('name', $association->label());
    $facet->setOnlyVisibleWhenFacetSourceIsVisible(TRUE);

    $facet->setWeight(0);
    $facet->addProcessor([
      'processor_id' => 'display_value_widget_order',
      'weights' => [],
      'settings' => [
        'sort' => 'ASC',
      ],
    ]);
    $facet->addProcessor([
      'processor_id' => 'url_processor_handler',
      'weights' => [],
      'settings' => [],
    ]);
    $facet->setEmptyBehavior(['behavior' => 'none']);
    $facet->setFacetSourceId($list_source->getSearchId());
    $facet->setWidget('oe_list_pages_multiselect', []);
    $facet->setFieldIdentifier($id);
    $facet->save();
  }

  /**
   * Remove search index field and facet created for an association.
   *
   * @param \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association
   *   The association configuration.
   * @param string $field_id
   *   The field id.
   */
  public function removeConfig(OpenVocabularyAssociationInterface $association, string $field_id): void {
    // Based on the list source, determine the correct index to create the
    // field in.
    $field_config = $this->entityTypeManager->getStorage('field_config')->load($field_id);
    $entity_type = $field_config->getTargetEntityTypeId();
    $bundle = $field_config->getTargetBundle();
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $this->listSourceFactory->get($entity_type, $bundle);
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $list_source->getIndex();

    // Remove field.
    $id = 'open_vocabularies_' . str_replace('.', '_', $association->id() . '_' . $field_id);
    $index->removeField($id);
    $index->save();

    // Remove facet.
    $facet = $this->getFacet($id);
    $facet->delete();
  }

  /**
   * Gets an existing facet by id or creat a new one if doesn't exist.
   *
   * @param string $facet_id
   *   The facet id.
   *
   * @return \Drupal\facets\FacetInterface
   *   The facet.
   */
  protected function getFacet(string $facet_id): FacetInterface {
    $facet = $this->entityTypeManager->getStorage('facets_facet')->load($facet_id);
    return $facet instanceof FacetInterface ? $facet : Facet::create(['id' => $facet_id]);
  }

  /**
   * Gets an existing index field by id or create a new one if doesn't exist.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   * @param string $field_id
   *   The field id.
   *
   * @return \Drupal\search_api\Item\FieldInterface
   *   The Search API field.
   */
  protected function getField(IndexInterface $index, string $field_id): FieldInterface {
    // In case the field exists, just return it.
    if (!empty($index->getField($field_id))) {
      return $index->getField($field_id);
    }

    // Otherwise instantiate a new one and add it to the index.
    $field = new Field($index, $field_id);
    $index->addField($field);
    return $field;
  }

}
