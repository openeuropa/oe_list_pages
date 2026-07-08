<?php

namespace Drupal\Tests\oe_list_pages_address\Unit;

use CommerceGuys\Addressing\Country\Country;
use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\facets\Entity\Facet;
use Drupal\facets\Result\Result;
use Drupal\oe_list_pages_address\Plugin\facets\processor\FormatCountryCodeProcessor;

/**
 * Unit test for processor.
 *
 * @group facets
 */
class FormatAddressProcessorTest extends UnitTestCase {

  /**
   * Tests filtering of results.
   */
  public function testBuild() {
    // The country name is expected to be resolved in the current language, so
    // mock the repository to return the localized name only when the country
    // code is requested together with the current language code.
    $country = new Country([
      'country_code' => 'GB',
      'name' => 'Royaume-Uni',
      'locale' => 'fr',
    ]);
    $country_repository = $this->createMock(CountryRepositoryInterface::class);
    $country_repository->expects($this->any())
      ->method('get')
      ->with('GB', 'fr')
      ->willReturn($country);

    // Mock the language manager to return French as the current language.
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('fr');
    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager->method('getCurrentLanguage')->willReturn($language);

    $facet = new Facet([], 'facets_facet');
    $processor = new FormatCountryCodeProcessor([], 'oe_list_pages_address_format_country_code', [], $country_repository, $language_manager);

    $original_results = [
      new Result($facet, 'GB', 0, 10),
    ];
    $facet->setResults($original_results);

    $filtered_results = $processor->build($facet, $original_results);
    $this->assertEquals('Royaume-Uni', $filtered_results[0]->getDisplayValue());
  }

}
