<?php

namespace d2build\Tasks\ExtraConfig;

use Robo\Contract\BuilderAwareInterface;
use Robo\TaskAccessor;
use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Common\IO;
use Robo\Task\Filesystem;
use Robo\Task\File;

/**
 * Class SyncFiles.
 *
 * @package d2build\Tasks\LocalConfig
 */
class ExtraConfig extends BaseTask implements BuilderAwareInterface {
  use TaskAccessor;
  use IO;
  use File\Tasks;
  use Filesystem\Tasks;

  /**
   * Drupal site directory.
   *
   * @var string
   */
  protected $site = 'default';

  /**
   * Drupal version.
   *
   * @var string
   */
  protected $drupal = 8;

  /**
   * Website directory.
   *
   * @var string
   */
  protected $webDir = 'web';

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
   * @param string $site
   *   Drupal site directory.
   */
  public function __construct($site) {
    $this->site = (string) $site;
  }

  /**
   * Set website directory.
   *
   * @param string $webdir
   *   Website directory.
   *
   * @return $this
   */
  public function setWebsiteDirectory($webdir) {
    $this->webDir = $webdir;

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
   * Create local settings files.
   */
  public function enableExtraConfig() {
    if (!is_file($this->webDir . '/sites/' . $this->site . '/settings.php')) {
      if (is_file($this->webDir . '/sites/' . $this->site . '/default.settings.php')) {
        $this->taskFilesystemStack()
          ->copy($this->webDir . '/sites/' . $this->site . '/default.settings.php', $this->webDir . '/sites/' . $this->site . '/settings.php')
          ->run();
      }
    }

    if ('7' == $this->drupal) {
      $this->taskWriteToFile($this->webDir . '/sites/' . $this->site . '/settings.php')
        ->append(TRUE)
        ->text(
          "\nif (file_exists('sites/" . $this->site . "/settings.extra.php')) {
  include sites/" . $this->site . "/settings.extra.php';
}
if (file_exists('sites/" . $this->site . "/settings.local.php')) {
  include 'sites/" . $this->site . "/settings.local.php';
}")
        ->run();
    }
    else {
      $this->taskReplaceInFile($this->webDir . '/sites/' . $this->site . '/settings.php')
        ->from(
          "# if (file_exists(\$app_root . '/' . \$site_path . '/settings.local.php')) {
#   include \$app_root . '/' . \$site_path . '/settings.local.php';
# }"
        )
        ->to(
          "if (file_exists(\$app_root . '/' . \$site_path . '/settings.extra.php')) {
  include \$app_root . '/' . \$site_path . '/settings.extra.php';
}
if (file_exists(\$app_root . '/' . \$site_path . '/settings.local.php')) {
  include \$app_root . '/' . \$site_path . '/settings.local.php';
}")
        ->run();
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->printTaskInfo('Enable extra settings');
    $this->enableExtraConfig();

    return new Result(
      $this,
      $this->exitCode,
      $this->errorMessage,
      []
    );
  }

}
