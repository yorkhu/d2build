<?php

namespace d2build\Deploy\Source;

/**
 * Interface deploy source interface.
 *
 * @package d2build\Deploy\Source
 */
interface DeploySourceInterface {

  /**
   * Prepare deploy process.
   */
  public function prepareDeploy();

  /**
   * Pre deploy process.
   */
  public function preDeploy();

  /**
   * Deploy process.
   */
  public function deploy();

  /**
   * Post deploy process.
   */
  public function postDeploy();

}
