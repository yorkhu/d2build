<?php

namespace d2build\Tasks\LinkDirs;

use Robo\Contract\BuilderAwareInterface;
use Robo\TaskAccessor;
use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Task\Filesystem;

/**
 * Class LinkDirs.
 *
 * @package d2build\Tasks\LinkDirs
 */
class LinkDirs extends BaseTask implements BuilderAwareInterface {
  use TaskAccessor;
  use Filesystem\Tasks;

  /**
   * Destination directory.
   *
   * @var string
   */
  protected $destinationDir;

  /**
   * Drupal version.
   *
   * @var string
   */
  protected $drupal = 8;

  /**
   * Source directory.
   *
   * @var string
   */
  protected $source;

  /**
   * Exit code.
   *
   * @var int
   */
  protected $exitCode = 0;

  /**
   * Error message.
   *
   * @var string
   */
  protected $errorMessage = '';

  /**
   * Object construct.
   *
   * @param string $source
   *   Source directory.
   */
  public function __construct($source) {
    $this->source = (string) $source;
  }

  /**
   * Set destination directory.
   *
   * @param string $desc_dir
   *   Destination directory.
   *
   * @return $this
   */
  public function setDestinationDirectory($desc_dir) {
    $this->destinationDir = $desc_dir;

    return $this;
  }

  /**
   * Set drupal version.
   *
   * @param string $version
   *   Drupal version.
   *
   * @return $this
   */
  public function setDrupalVersion($version) {
    $this->drupal = $version;

    return $this;
  }

  /**
   * Sync files.
   */
  public function linkDirs() {
    $source = $this->source;

    if (is_dir($source)) {
      $files = scandir($source);
      $dirs = [];
      $basedir = $this->destinationDir;
      $src_dir = '../../' . $source . '/';
      for ($i = 0; $i < substr_count($basedir, '/'); $i++ ) {
        $src_dir = '../' . $src_dir;
      }
      $desc_dir = $basedir . '/' . $source . '/';
      if (!file_exists($desc_dir)) {
        $this->taskFilesystemStack()
          ->mkdir($desc_dir)
          ->run();
      }

      foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
          if (is_dir($source . '/' . $file)) {
            $dirs[] = $file;

            $this->taskFilesystemStack()
              ->symlink($src_dir . $file, $desc_dir . $file)
              ->run();
          }
        }
      }
    }
    else {
      $this->exitCode = 1;
      $this->errorMessage = 'Not found ' . $source . ' directory.';
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->linkDirs();

    return new Result(
      $this,
      $this->exitCode,
      $this->errorMessage,
      []
    );
  }

}
