<?php

namespace Drupal\oe_list_pages_address\Plugin\facets\processor;

use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transforms country code of an Address field into the full country name.
 *
 * @FacetsProcessor(
 *   id = "oe_list_pages_address_format_country_code",
 *   label = @Translation("Transform country codes into full names"),
 *   description = @Translation("Transform country codes from an Address field in to their related full name."),
 *   stages = {
 *     "build" = 5
 *   }
 * )
 */
class FormatCountryCodeProcessor extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * The country repository.
   *
   * @var \CommerceGuys\Addressing\Country\CountryRepositoryInterface
   */
  protected $countryRepository;

  /**
   * Constructs a new FormatCountryCodeProcessor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \CommerceGuys\Addressing\Country\CountryRepositoryInterface $country_repository
   *   The country repository.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CountryRepositoryInterface $country_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->countryRepository = $country_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('address.country_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    // Loop over all results and try to determine the country.
    foreach ($results as $i => $result) {
      try {
        $country = $this->countryRepository->get($result->getRawValue());
      }
      catch (\Exception $exception) {
        continue;
      }

      $result->setDisplayValue($country->getName());
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    $data_definition = $facet->getDataDefinition();
    if (in_array($data_definition->getDataType(), [
      'field_item:address',
      'field_item:address_country',
      'field_item:string',
      'string',
    ])) {
      return TRUE;
    }

    return FALSE;
  }

}
