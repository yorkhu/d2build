<?php

namespace d2build\Build\Component\Project;

use Robo\Robo;

/**
 * @file
 * Project trait file.
 */

/**
 * Trait ProjectTrait.
 *
 * @package d2build\Build\Component\Project
 */
trait ProjectTrait {

  protected $roboEnv = '.robo.env';

  /**
   * Get git branch.
   */
  protected function getBranch() {
    $git_branch = exec('git rev-parse --abbrev-ref HEAD');
    return $git_branch;
  }

  /**
   * Get project environment.
   */
  protected function getConfigEnv($base_dir = '') {
    if (empty($base_dir)) {
      $base_dir = $this->roboDir;
    }

    if (!is_file($base_dir . $this->roboEnv)) {
      $branch = $this->getBranch();
      switch ($branch) {
        case 'staging':
        case 'master':
          $conf_env = $branch;
          break;

        default:
          $conf_env = 'staging';
      }

      $this->isNewEnv = TRUE;
      $this->setConfigEnv($conf_env, $base_dir);
    }

    $env = Robo::createConfiguration([$base_dir . $this->roboEnv]);
    Robo::loadConfiguration([$base_dir . $this->roboEnv], $env);
    $env->get('env');

    return $env->get('env');
  }

  /**
   * Set project environment
   */
  protected function setConfigEnv($conf_env, $base_dir = '') {
    if (empty($base_dir)) {
      $base_dir = $this->roboDir;
    }

    file_put_contents($base_dir . $this->roboEnv, 'env: ' . $conf_env);
  }

  /**
   * Set project environment
   */
  protected function validateNewConfigEnv() {
    $branch = $this->getBranch();
    $env = $this->getConfigEnv();

    // Set default environment
    if ($branch != $env) {
      $default_env = 'staging';
    }

    if (($env == $branch) && ($env == 'master')) {
      $default_env = 'master';
    }

    if (!isset($default_env)) {
      return;
    }

    // Get new environment to user:
    $env = $this->askDefault('Would you like to change your environment (Which robo-config.[environment].yml would you like to use):', $default_env);

    if ($env !== $default_env) {
      // Set new env:
      $this->setConfigEnv($env);
      $this->say('Set ' . $env . ' environment.');
      // Reload configuration:
      $this->config = $this->loadConfiguration($this->roboDir);
    }
  }

  /**
   * Get git branch.
   */
  protected function getProjectName() {
    $project = exec('basename `git rev-parse --show-toplevel`');
    return $project;
  }

  /**
   * Get base path.
   */
  protected function getBasePath() {
    return exec('pwd');
  }

}
