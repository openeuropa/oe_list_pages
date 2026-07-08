<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages_address\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\facets\Entity\Facet;
use Drupal\facets\Result\Result;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\oe_list_pages_address\Plugin\facets\processor\FormatCountryCodeProcessor;

/**
 * Tests that the country facet is displayed in the current language.
 *
 * @group oe_list_pages
 */
class FormatCountryCodeProcessorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'language',
    'address',
  ];

  /**
   * Tests that country names are resolved in the current language.
   */
  public function testCountryNameLocalisation(): void {
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // Instantiate the country repository service while English is still the
    // current language. This freezes its default locale to English, which is
    // the condition that caused the untranslated country names.
    $country_repository = $this->container->get('address.country_repository');
    $this->assertEquals('Belgium', $country_repository->get('BE')->getName());

    // Switch the current language to French.
    $this->container->get('language.default')->set(ConfigurableLanguage::load('fr'));
    $language_manager = $this->container->get('language_manager');
    $language_manager->reset();
    $this->assertEquals('fr', $language_manager->getCurrentLanguage()->getId());

    // Build the facet results through the processor. It must resolve the
    // country name in the current (French) language and not reuse the frozen
    // English default of the shared country repository service.
    $facet = new Facet([], 'facets_facet');
    $processor = FormatCountryCodeProcessor::create($this->container, [], 'oe_list_pages_address_format_country_code', []);
    $results = [new Result($facet, 'BE', 0, 10)];
    $facet->setResults($results);

    $built = $processor->build($facet, $results);
    $this->assertEquals('Belgique', $built[0]->getDisplayValue());
  }

}
