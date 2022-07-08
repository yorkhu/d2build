<?php

namespace d2build\Build\Component\RebuildableCondition;

/**
 * @file
 * Rebuildable condition trait file.
 */

use Symfony\Component\Yaml\Yaml;

/**
 * Trait RebuildableConditionTrait.
 *
 * @package d2build\Build\Component\RebuildableCondition
 */
trait RebuildableConditionTrait {

  /**
   * Old checksum.
   *
   * @var array
   */
  protected $oldChecksum = [];

  /**
   * Check sum file.
   *
   * @var string
   */
  protected $checksumFile = '.make.sum';

  /**
   * File change.
   *
   * @var array
   */
  protected $checksumCheck;

  /**
   * Check whether the code is in a rebuild-able state.
   */
  protected function rebuildableCondition() {
    if (empty($this->checksumCheck)) {
      if (empty($this->oldChecksum)) {
        if (file_exists($this->roboDir . $this->checksumFile)) {
          $old_checksum_data = file_get_contents($this->roboDir . $this->checksumFile);
          $this->oldChecksum = (array) Yaml::parse($old_checksum_data);
        }
      }

      $checksum = [];
      $check_files = [
        'composer' => $this->roboDir . '/composer.json',
        'docker' => $this->roboDir . '/docker-compose.yml',
      ];

      $sources = $this->getThemeSources();

      foreach ($sources as $source) {
        if (is_dir($source)) {
          $themeDirs = $this->getThemeDirs($source, 0, [], TRUE);
          foreach ($themeDirs as $dir => $file) {
            $change_index = str_replace('/', '-', $dir);
            $check_files[$change_index] = $this->roboDir . $dir . '/package.json';
          }
        }
      }

      $update_file = FALSE;
      foreach ($check_files as $category => $filename) {
        if (is_file($filename)) {
          $checksum[$category] = sha1_file($filename);
        }
        else {
          $checksum[$category] = NULL;
        }

        if (isset($this->oldChecksum[$category])) {
          $this->checksumCheck[$category] = ($this->oldChecksum[$category] !== $checksum[$category]);
        }
        else {
          $this->checksumCheck[$category] = TRUE;
        }

        if ($this->checksumCheck[$category]) {
          $update_file = TRUE;
        }
      }

      if (is_file('drupal.make')) {
        $checksum['make'] = sha1_file('drupal.make');
        if (is_file('drupal-dev.make')) {
          $checksum['make'] .= '-' . sha1_file('drupal-dev.make');
        }

        if (isset($this->oldChecksum['make'])) {
          $this->checksumCheck['make'] = ($this->oldChecksum['make'] !== $checksum['make']);
        }
        else {
          $this->checksumCheck['make'] = TRUE;
        }

        if ($this->checksumCheck['make']) {
          $update_file = TRUE;
        }
      }
      else {
        $checksum['make'] = NULL;
      }

      if ($update_file) {
        $checksum_data = Yaml::dump($checksum);
        file_put_contents($this->roboDir . $this->checksumFile, $checksum_data);
      }
    }

    return $this->checksumCheck;
  }

  /**
   * Get theme directory source.
   *
   * @param array $sources
   *   Base source.
   */
  protected function getThemeSources(array $sources = ['themes']) {
    $profile_dir = 'profiles';
    if (is_dir($profile_dir)) {
      $profiles = scandir($profile_dir);
      foreach ($profiles as $profile) {
        if ('.' !== $profile && '..' !== $profile) {
          $basedir = $profile_dir . '/' . $profile . '/themes';
          if (is_dir($basedir)) {
            $sources[] = $basedir;
          }
        }
      }
    }

    return $sources;
  }

  /**
   * Search theme directories.
   *
   * @param string $source
   *   Base directory.
   * @param int $level
   *   Recursive level.
   * @param array $opts
   *   Restrict the output to configuration values for a specific section.
   *
   * @option $no-docker
   *   Command run in the shell.
   *
   * @option $not-interactive
   *   Disable shell interactive mode.
   *
   * @option $force
   *   Run all build components.
   *
   * @param bool $skip_change_index
   *   Skip theme change packages.
   *
   * @return array
   *   Theme directories.
   */
  protected function getThemeDirs(string $source, int $level = 0, array $opts = ['no-docker' => FALSE, 'not-interactive' => FALSE, 'force' => FALSE], bool $skip_change_index = FALSE) {
    $themeDirs = [];

    if (3 > $level) {
      if (is_dir($source)) {
        $files = scandir($source);

        foreach ($files as $file) {
          if ('.' != $file && '..' != $file) {
            if (is_dir($source . '/' . $file)) {
              if (is_file($source . '/' . $file . '/package.json')) {
                if ($skip_change_index) {
                  $themeDirs[$source . '/' . $file] = $file;
                }
                elseif ($this->isChanged($source, $file, $opts)) {
                  $themeDirs[$source . '/' . $file] = $file;
                }
              }
              elseif ('node_modules' !== $file) {
                $themeDirs += $this->getThemeDirs($source . '/' . $file, $level++, $opts, $skip_change_index);
              }
            }
          }
        }
      }
    }

    return $themeDirs;
  }

}
