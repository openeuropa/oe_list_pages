<?php

namespace Drupal\oe_list_pages_open_vocabularies;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;
use Drupal\oe_list_pages\ListSourceFactoryInterface;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\FieldInterface;

/**
 * Configure search api when vocabulary associations are created.
 */
class SearchApiConfigurator {

  /**
   * The entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The list source factory.
   *
   * @var Drupal\oe_list_pages\ListSourceFactoryInterface
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
   *   The openvocabulary association.
   * @param string $field_id
   *   The field_id.
   */
  public function updateConfig(OpenVocabularyAssociationInterface $association, string $field_id): void {
    $field_config_storage = $this->entityTypeManager->getStorage('field_config');
    // Get the correct index looking to the list source.
    $field_config = $field_config_storage->load($field_id);
    $entity_type = $field_config->getTargetEntityTypeId();
    $bundle = $field_config->getTargetBundle();
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $this->listSourceFactory->get($entity_type, $bundle);
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $list_source->getIndex();

    // Generates the field and facet id.
    $id = 'open_vocabularies_' . str_replace('.', '_', $association->id() . '_' . $field_id);

    // Creates the field.
    /** @var \Drupal\search_api\Field $index_field */
    $index_field = $this->getField($index, $id);
    $index_field->setType('string');
    $index_field->setPropertyPath($field_config->getFieldStorageDefinition()->getName() . ':target_id');
    $index_field->setDatasourceId('entity:' . $entity_type);
    $index_field->setLabel($association->label());
    $index->save();

    // Creates the facet.
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
    $facet->setEmptyBehavior([]);
    $facet->setFacetSourceId($list_source->getSearchId());
    $facet->setWidget('oe_list_pages_multiselect', []);
    $facet->setFieldIdentifier($id);
    $facet->save();
  }

  /**
   * Remove configuration association with open vocabulary association.
   *
   * @param \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association
   *   The association configuration.
   * @param string $field_id
   *   The field id.
   */
  public function removeConfig(OpenVocabularyAssociationInterface $association, string $field_id): void {
    $field_config_storage = $this->entityTypeManager->getStorage('field_config');
    // Get the correct index looking to the list source.
    $field_config = $field_config_storage->load($field_id);
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
   * Gets existing facet by facet id or create a new one.
   *
   * @param string $facet_id
   *   The facet id.
   *
   * @return \Drupal\facets\FacetInterface
   *   The facet.
   */
  protected function getFacet(string $facet_id): FacetInterface {
    $facet_storage = $this->entityTypeManager->getStorage('facets_facet');
    return $facet_storage->load($facet_id) ?? new Facet(['id' => $facet_id], 'facets_facet');
  }

  /**
   * Gets existing search api field by id or create a new one.
   *
   * @param \Drupal\search_api\Entity\Index $index
   *   The index.
   * @param string $field_id
   *   The field id.
   *
   * @return \Drupal\search_api\Item\FieldInterface
   *   The Search API field.
   */
  protected function getField(Index $index, string $field_id): FieldInterface {
    // In case the field exists, just return it.
    if (!empty($index->getField($field_id))) {
      return $index->getField($field_id);
    }

    // Otherwise create a field and add to index.
    $field = new Field($index, $field_id);
    $index->addField($field);
    return $field;
  }

}
