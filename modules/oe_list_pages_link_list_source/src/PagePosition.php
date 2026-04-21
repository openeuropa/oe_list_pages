<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_link_list_source;

/**
 * Value object to specify a page number, page size and offset within the page.
 *
 * @internal
 *   This class may be moved or renamed in future versions.
 */
final class PagePosition {

  /**
   * Constructs a new instance.
   *
   * @param non-negative-int $page
   *   The page number, starting at zero.
   * @param int|null $itemsPerPage
   *   The number of items per page, or NULL for an infinite-size page.
   *   If this is NULL, the page number is always zero.
   * @param int $offsetInPage
   *   The offset within the page.
   */
  public function __construct(
    public readonly int $page,
    public readonly ?int $itemsPerPage,
    public readonly int $offsetInPage,
  ) {}

  /**
   * Computes a new page position so that the page fully contains a given range.
   *
   * @param non-negative-int $offset
   *   Start of the range.
   * @param positive-int|null $length
   *   Length of the range.
   *
   * @return static
   *   New instance.
   */
  public static function fromRange(int $offset, ?int $length): static {
    if ($length === NULL) {
      return new self(0, NULL, $offset);
    }

    $max_page_size = $offset + $length;
    for ($page_size = $length; $page_size < $max_page_size; $page_size++) {
      $result_offset = $offset % $page_size;
      if ($result_offset + $length <= $page_size) {
        return new self(
          intdiv($offset, $page_size),
          $page_size,
          $result_offset,
        );
      }
    }
    return new self(0, $max_page_size, $offset);
  }

}
