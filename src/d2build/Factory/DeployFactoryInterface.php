<?php

namespace d2build\Factory;

use d2build\Deploy\Source\DeploySourceInterface;

/**
 * Interface extract deploy source interface .
 *
 * @package d2build\Factory
 */
interface DeployFactoryInterface {

  /**
   * Factory method to decorate queries with deploy source.
   *
   * @param array $config
   *   Deploy config.
   *
   * @return \d2build\Deploy\Source\DeploySourceInterface
   *   A new deploy decorator class.
   */
  public static function createInstance(
    array $config
  ): DeploySourceInterface;

}
