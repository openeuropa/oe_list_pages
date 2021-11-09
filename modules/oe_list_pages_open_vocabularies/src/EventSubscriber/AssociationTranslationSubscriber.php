<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_open_vocabularies\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\language\Config\LanguageConfigOverrideCrudEvent;
use Drupal\language\Config\LanguageConfigOverrideEvents;
use Drupal\oe_list_pages_open_vocabularies\SearchApiConfigurator;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Responds to changes to configuration translations.
 */
class AssociationTranslationSubscriber implements EventSubscriberInterface {

  /**
   * The search API configurator.
   *
   * @var \Drupal\oe_list_pages_open_vocabularies\SearchApiConfigurator
   */
  protected $searchApiConfigurator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a AssociationTranslationSubscriber.
   *
   * @param \Drupal\oe_list_pages_open_vocabularies\SearchApiConfigurator $searchApiConfigurator
   *   The search API configurator.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(SearchApiConfigurator $searchApiConfigurator, EntityTypeManagerInterface $entityTypeManager) {
    $this->searchApiConfigurator = $searchApiConfigurator;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[LanguageConfigOverrideEvents::SAVE_OVERRIDE] = 'onOverrideChange';
    $events[LanguageConfigOverrideEvents::DELETE_OVERRIDE] = 'onOverrideDelete';
    return $events;
  }

  /**
   * Updates the open vocabulary association facets with translations.
   *
   * @param \Drupal\language\Config\LanguageConfigOverrideCrudEvent $event
   *   The language configuration event.
   */
  public function onOverrideChange(LanguageConfigOverrideCrudEvent $event) {
    // We don't do anything if we are part of an install.
    if (InstallerKernel::installationAttempted()) {
      return;
    }

    $override = $event->getLanguageConfigOverride();
    $config_id = $override->getName();
    if (!$this->isAssociationTranslation($config_id)) {
      return;
    }

    $association = $this->getAssociationFromConfigId($config_id);
    if (!$association) {
      return;
    }

    foreach ($association->getFields() as $field) {
      $this->searchApiConfigurator->updateConfigTranslation($association, $field, $override);
    }
  }

  /**
   * Deletes the open vocabulary association facets translation.
   *
   * @param \Drupal\language\Config\LanguageConfigOverrideCrudEvent $event
   *   The language configuration event.
   */
  public function onOverrideDelete(LanguageConfigOverrideCrudEvent $event) {
    if (InstallerKernel::installationAttempted()) {
      return;
    }

    $override = $event->getLanguageConfigOverride();
    $config_id = $override->getName();
    if (!$this->isAssociationTranslation($config_id)) {
      return;
    }

    $association = $this->getAssociationFromConfigId($config_id);
    if (!$association) {
      return;
    }

    foreach ($association->getFields() as $field) {
      $this->searchApiConfigurator->deleteConfigTranslation($association, $field, $override->getLangcode());
    }
  }

  /**
   * Checks if a config ID belongs to an association entity.
   *
   * @param string $config_id
   *   The config ID.
   *
   * @return bool
   *   Whether it belongs to an association.
   */
  protected function isAssociationTranslation(string $config_id): bool {
    $entity_type_info = $this->entityTypeManager->getDefinition('open_vocabulary_association');
    return strpos($config_id, $entity_type_info->getConfigPrefix()) !== FALSE;
  }

  /**
   * Loads the association from its config ID.
   *
   * @param string $config_id
   *   The config ID.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyAssociationInterface|null
   *   The open vocabulary association.
   */
  protected function getAssociationFromConfigId(string $config_id): ?OpenVocabularyAssociationInterface {
    $entity_type_info = $this->entityTypeManager->getDefinition('open_vocabulary_association');
    $association_id = str_replace($entity_type_info->getConfigPrefix() . '.', '', $config_id);
    return $this->entityTypeManager->getStorage('open_vocabulary_association')->load($association_id);
  }

}
