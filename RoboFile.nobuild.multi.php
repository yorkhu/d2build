<?php

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

use Robo\Tasks;

// sites/all/libraries/piwik/config/config.ini.php

/**
 * Class RoboFile.
 */
class RoboFile extends Tasks {
  use \d2build\Build\Component\Config\ConfigTrait;
  use \d2build\Build\Component\Docker\DockerTrait;
  use \d2build\Build\Component\Link\LinkTrait;
  use \d2build\Build\Component\Lock\LockTrait;
  use \d2build\Build\Component\Sync\SyncTrait;
  use \d2build\Build\Component\Env\EnvTrait;

  /**
   * @var string
   *
   *   Project builder base directory.
   */
  protected $roboDir = '';

  /**
   * RoboFile constructor.
   */
  public function __construct() {
    $this->roboDir = __DIR__ . '/../';
    $this->config = $this->loadConfiguration($this->roboDir);

    // Set shell env.
    $this->runExport();
  }

  /**
   * Init: create docker image and run, build drupal project.
   *
   * @param array $opts
   *   Restrict the output to configuration values for a specific section.
   *
   * @option $sync
   *   Sync database and files.
   *
   * @option $sync-files
   *   Sync files.
   *
   * @option $sync-db
   *   Sync databasefiles.
   *
   * @usage init --sync
   *
   * @usage init --sync-files
   *
   * @usage init --sync-db
   *
   * @aliases i
   */
  public function init(array $opts = ['sync|s' => FALSE, 'sync-files|sf' => FALSE, 'sync-db|sdb' => FALSE]) {
    // Validated new config env.
    if ($this->isNewEnv) {
      $this->validateNewConfigEnv();
    }

    if ($this->isLocked()) {
      $this->say('The build system is currently locked. Syncing and reinitializing functions are disabled. If you would like to run them delete the .robo-locked file.');
      return;
    }

    $this->say("Init " . $this->getProjectName() . ' project.');

    if ($this->dockerEnable()) {
      // Make sure the docker config files are present.
      $this->copyDockerFiles($this->roboDir);

      // Build and start docker container.
      $this->dockerUp();
    }

    // Create local Config files.
    $this->createLocalConfig();

    // Copy drupal_extra driectory.
    $this->copyDrupalExtras();

    $this->say("Init " . $this->getProjectName() . ' project.');


    if ($opts['sync']) {
      // Sync database and files.
      $this->sync();
    }
    else {
      if ($opts['sync-files']) {
        // Sync files.
        $this->syncFiles();
      }

      if ($opts['sync-db']) {
        // Sync database.
        $this->syncDB();
      }
    }
  }

  /**
   * Clean project.
   *
   * @aliases c
   */
  public function clean() {
    if ($this->isLocked()) {
      $this->say('The build system is currently locked. Syncing and reinitializing functions are disabled. If you would like to run them delete the .robo-locked file.');
      return;
    }

    $this->say("Clean " . $this->getProjectName() . ' project.');

    if ($this->dockerEnable()) {
      // Remove docker containers.
      $this->dockerDestroy();
    }

    // Remove checksum.
    $this->taskFilesystemStack()
      ->remove(__DIR__ . '/../' . $this->checksumFile)
      ->run();

    // Remove env file.
    $this->taskFilesystemStack()
      ->remove(__DIR__ . '/../' . $this->roboEnv)
      ->run();
  }

  /**
   * Run command docker or shell.
   *
   * @param object $cmd
   *   Robo command.
   * @param bool $noDocker
   *   Run docker or shell options.
   * @param bool $interactive
   *   Run docker interactive mode on/off.
   * @param string $container
   *   Docker container name.
   * @param string $skip_user
   *   Skip user options.
   */
  protected function cmdExec($cmd, $noDocker = NULL, $interactive = TRUE, $container = NULL, $skip_user = FALSE) {
    $dockerConf = $this->config->get('settings.docker');
    if (!empty($dockerConf) && $this->dockerEnable($noDocker)) {
      if (empty($container)) {
        $container = $this->getDockerWebContainerName();
      }

      $exec = $this->taskDockerExec($this->getDockerContainerID($container))
        ->interactive($interactive)
        ->option('-t');

      if (!$skip_user) {
        $exec ->option('user', posix_getuid());
      }
      $exec->exec($cmd)
        ->env($this->getEnv())
        ->run();
    }
    else {
      $cmd->run();
    }
  }

  /**
   * Run command node docker or shell.
   *
   * @param object $cmd
   *   Robo command.
   * @param bool $noDocker
   *   Run docker or shell options.
   * @param bool $interactive
   *   Run docker interactive mode on/off.
   */
  protected function cmdThemeExec($cmd, $noDocker = NULL, $interactive = TRUE, $container = NULL) {
    $skip_user = FALSE;
    $dockerConf = $this->config->get('settings.docker');
    if (!empty($dockerConf) && $this->dockerEnable($noDocker) && empty($container)) {
      $container = $this->getDockerThemeContainerName();
      if ($this->getDockerWebContainerName() !== $container && posix_getuid() < 1000) {
        $skip_user = TRUE;
      }
    }

    $this->cmdExec($cmd, $noDocker, $interactive, $container, $skip_user);
  }

  /**
   * Create local drupal configuration.
   */
  protected function createLocalConfig() {
    if ($this->config->get('settings.CreateLocalSettings')) {
      $this->drupalInit();
    }
  }

  /**
   * Download drupal core extra files and prepair configuration.
   */
  protected function drupalInit() {
    $multisite = $this->config->get('settings.MultiSite');
    $sites = [];
    if (!empty($this->multiSites)) {
      $sites = array_keys($multisite);
    }
    else {
      $sites[] = 'default';
    }

    foreach ($sites as $site) {
      if (!is_file('sites/' . $site . '/settings.php')) {
        $this->taskFilesystemStack()
          ->copy('sites/default/default.settings.php', 'sites/' . $site . '/settings.php')
          ->run();

        if ('default' == $site) {
          $sql = $this->config->get('settings.DevSqlServer');
          if (empty($sql)) {
            $sql = [];
          }
          $sql += [
            'database' => '',
            'username' => '',
            'password' => '',
            'prefix' => '',
            'host' => '',
            'port' => '',
          ];

          $this->taskWriteToFile('sites/default/settings.local.php')
            ->text("<?php
\$databases = array (
  'default' =>
  array (
    'default' =>
    array (
      'database' => '" . $sql['database'] . "',
      'username' => '" . $sql['username'] . "',
      'password' => '" . $sql['password'] . "',
      'host' => '" . $sql['host'] . "',
      'port' => '" . $sql['port'] . "',
      'driver' => 'mysql',
      'prefix' => '',
    ),
  ),
);
")->run();
        }

        $this->taskWriteToFile('sites/' . $site . '/settings.php')
          ->append(TRUE)
          ->text("if (file_exists('sites/' . $site . '/settings.extra.php')) {
  include 'sites/' . $site . '/settings.extra.php';
}

if (file_exists('sites/' . $site . '/settings.local.php')) {
  include 'sites/' . $site . '/settings.local.php';
}")->run();
      }
    }
  }

  /**
   * Get drush cache clear command.
   */
  protected function getCacheCmd() {
    return 'cc all';
  }

  /**
   * Run export UID.
   */
  protected function runExport() {
    $this->say('Exporting UID.');
    $this->envExtra['UID'] = posix_getuid();
    putenv("UID=" . $this->envExtra['UID']);
  }

}
