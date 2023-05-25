<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages_open_vocabularies\Kernel;

use Drupal\facets\Entity\Facet;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\open_vocabularies\Entity\OpenVocabularyAssociation;

/**
 * Tests that the created facets are also translated after the associations.
 */
class ListPagesSearchApiConfiguratorTranslationTest extends ListPagesSearchApiConfiguratorTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'config_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('fr')->save();
  }

  /**
   * Tests that facets get translations.
   *
   * Whenever translations on the associations get created/updated/deleted,
   * this needs to be reflected in the facet translation.
   */
  public function testVocabularyAssociationTranslationChanges(): void {
    $facet_id_one = 'open_vocabularies_custom_vocabulary_open_vocabulary_node_content_type_one_field_open_vocabularies';
    $facet_id_two = 'open_vocabularies_custom_vocabulary_open_vocabulary_node_content_type_two_field_open_vocabularies';

    // Create an association with one field.
    $fields = [
      'node.content_type_one.field_open_vocabularies',
    ];
    $values = [
      'label' => 'The association label',
      'name' => 'open_vocabulary',
      'widget_type' => 'options_select',
      'required' => TRUE,
      'help_text' => 'Some text',
      'predicate' => 'http://example.com/#name',
      'cardinality' => 5,
      'vocabulary' => 'custom_vocabulary',
      'fields' => $fields,
    ];
    $this->container->get('kernel')->rebuildContainer();
    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association */
    $association = OpenVocabularyAssociation::create($values);
    $association->save();

    // Assert we have the facet.
    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = Facet::load($facet_id_one);
    $this->assertEquals('list_facet_source:node:content_type_one', $facet->getFacetSourceId());

    // Check the translatable values were correctly saved.
    $this->assertEquals('The association label', $facet->label());

    // Alter the association, change label and add to a new field.
    $association = OpenVocabularyAssociation::load($association->id());
    $fields = [
      'node.content_type_one.field_open_vocabularies',
      'node.content_type_two.field_open_vocabularies',
    ];
    $association->set('fields', $fields);
    $association->set('label', 'New association label');
    $association->save();

    // Check that the label was changed in the facet and a new facet got
    // created.
    $this->container->get('kernel')->rebuildContainer();
    /** @var \Drupal\facets\FacetInterface $facet_one */
    $facet = Facet::load($facet_id_one);
    $this->assertEquals('New association label', $facet->label());
    $facet_two = Facet::load($facet_id_two);
    $this->assertEquals('New association label', $facet_two->label());

    // Assert the facets don't have any translations yet.
    /** @var \Drupal\language\Config\LanguageConfigOverride $config_translation */
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'facets.facet.' . $facet_id_one);
    $this->assertTrue($config_translation->isNew());
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'facets.facet.' . $facet_id_two);
    $this->assertTrue($config_translation->isNew());

    // Create a translation of the association.
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'open_vocabularies.open_vocabulary_association.' . $association->id());
    $config_translation->set('label', 'New association label in French');
    $config_translation->save();

    // Assert that the facets have also been translated.
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'facets.facet.' . $facet_id_one);
    $this->assertFalse($config_translation->isNew());
    $this->assertEquals('New association label in French', $config_translation->get('name'));
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'facets.facet.' . $facet_id_two);
    $this->assertFalse($config_translation->isNew());
    $this->assertEquals('New association label in French', $config_translation->get('name'));

    // Update the translation and assert the changes are reflected.
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'open_vocabularies.open_vocabulary_association.' . $association->id());
    $config_translation->set('label', 'New association label in French updated');
    $config_translation->save();

    /** @var \Drupal\language\Config\LanguageConfigOverride $config_translation */
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'facets.facet.' . $facet_id_one);
    $this->assertFalse($config_translation->isNew());
    $this->assertEquals('New association label in French updated', $config_translation->get('name'));
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'facets.facet.' . $facet_id_two);
    $this->assertFalse($config_translation->isNew());
    $this->assertEquals('New association label in French updated', $config_translation->get('name'));

    // Delete the translation and assert the facet translation is also
    // deleted.
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'open_vocabularies.open_vocabulary_association.' . $association->id());
    $config_translation->delete();
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'facets.facet.' . $facet_id_one);
    $this->assertTrue($config_translation->isNew());
    $this->assertEmpty($config_translation->get());
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('fr', 'facets.facet.' . $facet_id_two);
    $this->assertTrue($config_translation->isNew());
    $this->assertEmpty($config_translation->get());
  }

}
