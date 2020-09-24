<?php

namespace Drupal\oe_list_page_address\Plugin\facets\processor;

use CommerceGuys\Addressing\Country\CountryRepositoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transforms the results to show the translated entity label.
 *
 * @FacetsProcessor(
 *   id = "format_address",
 *   label = @Translation("Transform Address Codes into Full Names"),
 *   description = @Translation("Transform Address Codes into Full Names."),
 *   stages = {
 *     "build" = 5
 *   }
 * )
 */
class FormatAddressProcessor extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * The subdivision repository.
   *
   * @var \CommerceGuys\Addressing\Country\CountryRepositoryInterface
   */
  protected $countryRepository;

  /**
   * Constructs a new object.
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
    // Loop over all results.
    foreach ($results as $i => $result) {
      $country = $this->countryRepository->get($result->getRawValue());
      $result->setDisplayValue($country->getName());
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    $data_definition = $facet->getDataDefinition();
    if ($data_definition->getDataType() === 'string') {
      return TRUE;
    }
    return FALSE;
  }

}
