<?php

namespace Drupal\oe_list_pages_open_vocabularies;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\facets\FacetInterface;
use Drupal\language\Config\LanguageConfigOverride;
use Drupal\oe_list_pages\ListSourceFactoryInterface;
use Drupal\oe_list_pages_open_vocabularies\Event\AssociationFacetUpdateEvent;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\FieldInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Configure search api when vocabulary associations are created.
 */
class SearchApiConfigurator {

  /**
   * The weight of the created facets.
   *
   * @var int
   */
  const MAX_WEIGHT = 50;

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
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private $languageManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, ListSourceFactoryInterface $listSourceFactory, LanguageManagerInterface $languageManager, EventDispatcherInterface $eventDispatcher) {
    $this->entityTypeManager = $entityTypeManager;
    $this->listSourceFactory = $listSourceFactory;
    $this->entityFieldManager = $entityFieldManager;
    $this->languageManager = $languageManager;
    $this->eventDispatcher = $eventDispatcher;
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
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   */
  public function updateConfig(OpenVocabularyAssociationInterface $association, string $field_id, IndexInterface $index = NULL): void {
    // Based on the list source, determine the correct index to create the
    // field in.
    $field_config = $this->entityTypeManager->getStorage('field_config')->load($field_id);
    $entity_type = $field_config->getTargetEntityTypeId();
    $bundle = $field_config->getTargetBundle();

    // Refresh field definitions so that new field is available for search api.
    $this->entityFieldManager->clearCachedFieldDefinitions();
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $this->listSourceFactory->get($entity_type, $bundle);

    if (empty($index)) {
      /** @var \Drupal\search_api\IndexInterface $index */
      $index = $list_source->getIndex();
    }

    // Generate the field id.
    /* @TODO: Extract this id generation to a common method. */
    $property_path = $association->getName() . '_' . substr(hash('sha256', $field_config->getName()), 0, 10);

    // Create the field.
    $index_field = $this->getField($index, $property_path);
    $index_field->setType('string');

    $index_field->setPropertyPath($property_path);
    $index_field->setDatasourceId('entity:' . $entity_type);
    $index_field->setLabel($association->label());
    $index->save();

    // Create the facet.
    $id = $this->generateFacetId($association, $field_id);
    $facet = $this->getFacet($id);
    $facet->setUrlAlias(str_replace('.', '_', $association->id()));
    $facet->set('name', $association->label());
    $facet->setOnlyVisibleWhenFacetSourceIsVisible(TRUE);
    $facet->setWeight(self::MAX_WEIGHT);
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
    $facet->addProcessor([
      'processor_id' => 'translate_entity',
      'weights' => [],
      'settings' => [],
    ]);
    $facet->setEmptyBehavior(['behavior' => 'none']);
    $facet->setFacetSourceId($list_source->getSearchId());
    $facet->setWidget('oe_list_pages_multiselect', []);
    $facet->setFieldIdentifier($property_path);

    // Use event dispatching to allow alter facet config before saving.
    $event = new AssociationFacetUpdateEvent($facet);
    $this->eventDispatcher->dispatch($event, AssociationFacetUpdateEvent::NAME);
    $event->getFacet()->save();
  }

  /**
   * Updates a facet with translations from the vocabulary association.
   *
   * @param \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association
   *   The open vocabulary association.
   * @param string $field_id
   *   The field_id.
   * @param \Drupal\language\Config\LanguageConfigOverride $override
   *   The translation config override.
   */
  public function updateConfigTranslation(OpenVocabularyAssociationInterface $association, string $field_id, LanguageConfigOverride $override): void {
    $id = $this->generateFacetId($association, $field_id);
    $facet = $this->getFacet($id);
    $entity_type_info = $this->entityTypeManager->getDefinition($facet->getEntityTypeId());
    $facet_config_id = $entity_type_info->getConfigPrefix() . '.' . $facet->id();
    $language = $override->getLangcode();
    /** @var \Drupal\facets\FacetInterface $config_translation */
    $config_translation = $this->languageManager->getLanguageConfigOverride($language, $facet_config_id);
    $config_translation->set('name', $override->get('label'));
    $config_translation->save();
  }

  /**
   * Deletes a translation from a facet when it is deleted from the association.
   *
   * @param \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association
   *   The open vocabulary association.
   * @param string $field_id
   *   The field_id.
   * @param string $language
   *   The language to delete the translation.
   */
  public function deleteConfigTranslation(OpenVocabularyAssociationInterface $association, string $field_id, string $language): void {
    $id = $this->generateFacetId($association, $field_id);
    $facet = $this->getFacet($id);
    $entity_type_info = $this->entityTypeManager->getDefinition($facet->getEntityTypeId());
    $facet_config_id = $entity_type_info->getConfigPrefix() . '.' . $facet->id();
    /** @var \Drupal\facets\FacetInterface $config_translation */
    $config_translation = $this->languageManager->getLanguageConfigOverride($language, $facet_config_id);
    $config_translation->delete();
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
   * Generates a facet ID based on the association ID and field name.
   *
   * @param \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association
   *   The open vocabulary association.
   * @param string $field_id
   *   The field_id.
   *
   * @return string
   *   The facet ID.
   */
  protected function generateFacetId(OpenVocabularyAssociationInterface $association, string $field_id): string {
    return 'open_vocabularies_' . str_replace('.', '_', $association->id() . '_' . $field_id);
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
    $facet_storage = $this->entityTypeManager->getStorage('facets_facet');
    $facet = $facet_storage->load($facet_id);
    return $facet instanceof FacetInterface ? $facet : $facet_storage->create(['id' => $facet_id]);
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
