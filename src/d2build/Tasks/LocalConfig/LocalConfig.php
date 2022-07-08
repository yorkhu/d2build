<?php

namespace d2build\Tasks\LocalConfig;

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
class LocalConfig extends BaseTask implements BuilderAwareInterface {
  use TaskAccessor;
  use IO;
  use Filesystem\Tasks;
  use File\Tasks;

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
   * SQL server settings.
   *
   * @var array
   */
  protected $sqlServer = [];

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
   * Config environment.
   *
   * @var string
   */
  protected $configEnv = '';

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
   * Insert DB Settings.
   */
  protected function insertDataBaseSetting() {
    if (!empty($this->sqlServer)) {
      $sql = $this->sqlServer;
      $sql += [
        'database' => '',
        'username' => '',
        'password' => '',
        'prefix' => '',
        'host' => '',
        'port' => '',
      ];

      $db_settings = [
        "",
        "/**",
        " * Database config.",
        " */",
        "\$databases['default']['default'] = array (",
        "  'database' => '" . $sql['database'] . "',",
        "  'username' => '" . $sql['username'] . "',",
        "  'password' => '" . $sql['password'] . "',",
        "  'prefix' => '" . $sql['prefix'] . "',",
        "  'host' => '" . $sql['host'] . "',",
        "  'port' => '" . $sql['port'] . "',",
        "  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',  ",
        "  'driver' => 'mysql',",
        ");",
        "",
      ];

      $this->taskWriteToFile($this->webDir . '/sites/' . $this->site . '/settings.local.php')
        ->lines($db_settings)
        ->append(TRUE)
        ->run();
    }

    return $this;
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
   * Set config environment.
   *
   * @param string $env
   *   Config environment.
   *
   * @return $this
   */
  public function setConfigEnv($env) {
    $this->configEnv = $env;

    return $this;
  }

  /**
   * Set SQL server settings.
   *
   * @param string $sqlServer
   *   Drupal SQL server settings.
   *
   * @return $this
   */
  public function setSqlServer($sqlServer) {
    $this->sqlServer = $sqlServer;

    return $this;
  }

  /**
   * Create local settings files.
   */
  public function createLocalConfig() {
    if (file_exists($this->webDir . '/sites/example.settings.local.php')) {
      $this->printTaskInfo('Create Local Settings file:');

      if ('master' === $this->configEnv) {
        $this->taskFilesystemStack()
          ->touch($this->webDir . '/sites/default/settings.local.php')
          ->run();
      }
      else {
        $this->taskFilesystemStack()
          ->remove($this->webDir . '/sites/' . $this->site . '/settings.local.php')
          ->copy($this->webDir . '/sites/example.settings.local.php', $this->webDir . '/sites/default/settings.local.php')
          ->run();

        $this->taskReplaceInFile($this->webDir . '/sites/' . $this->site . '/settings.local.php')
          ->from("# \$settings['cache']['bins']['render'] = 'cache.backend.null'")
          ->to("\$settings['cache']['bins']['render'] = 'cache.backend.null'")
          ->run();

        $this->taskReplaceInFile($this->webDir . '/sites/' . $this->site . '/settings.local.php')
          ->from("# \$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null'")
          ->to("\$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null'")
          ->run();
      }

      $this->insertDataBaseSetting();
    }
    elseif ('7' == $this->drupal) {
      $this->taskFilesystemStack()
        ->touch($this->webDir . '/sites/' . $this->site . '/settings.local.php')
        ->run();

      $this->taskWriteToFile($this->webDir . '/sites/' . $this->site . '/settings.local.php')
        ->append(TRUE)
        ->text("<?php \n")
        ->run();

      $this->insertDataBaseSetting();
    }
    else {
      $this->exitCode = 1;
      $this->errorMessage = 'Not found "' . $this->webDir . '/sites/example.settings.local.php" file.';
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->printTaskInfo('Created local settings');
    $this->createLocalConfig();
    $this->printTaskInfo('Enable local settings');

    return new Result(
      $this,
      $this->exitCode,
      $this->errorMessage,
      []
    );
  }

}
