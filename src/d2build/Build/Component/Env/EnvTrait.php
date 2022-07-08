<?php

namespace d2build\Build\Component\Env;

/**
 * @file
 * Env trait file.
 */

/**
 * Trait EnvTrait.
 *
 * @package d2build\Build\Component\Env
 */
trait EnvTrait {

  /**
   * Change environment.
   *
   * @param string $environment_name
   *   New environment name.
   *
   * @aliases chenv setenv
   */
  public function environmentChange(string $environment_name) {
    $this->setConfigEnv($environment_name);
  }

  /**
   * Get environment.
   *
   * @aliases getenv
   */
  public function environmentGet() {
    $env = $this->getConfigEnv();
    $this->say('Environment: ' . $env);
  }

  /**
   * List environments.
   *
   * @aliases listenv lsenv
   */
  public function environmentList() {
    $file_list = glob('robo-config.*.yml');
    $this->say('Environments:');
    foreach ($file_list as $file) {
      $split = explode('.', $file);
      $env_list[] = $split[1];
      $this->say($split[1]);
    }
  }

}
