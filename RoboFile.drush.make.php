<?php

/**
 * @file
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

use Robo\Robo;
use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;

/**
 * Class RoboFile.
 */
class RoboFile extends Tasks {

  use \d2build\Build\Component\Config\ConfigTrait;
  use \d2build\Build\Component\Docker\DockerTrait;
  use \d2build\Build\Component\Sync\SyncTrait;
  use \d2build\Build\Component\Link\LinkTrait;
  use \d2build\Build\Component\Lock\LockTrait;
  use \d2build\Build\Component\Theme\ThemeTrait;
  use \d2build\Build\Component\BackupDB\BackupDBTrait;
  use \d2build\Build\Component\Env\EnvTrait;

  use \d2build\Tasks\LocalConfig\loadTasks;
  use \d2build\Tasks\ExtraConfig\loadTasks;

  /**
   * Project builder base directory.
   *
   * @var string
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

    if (!file_exists('scripts')) {
      $this->taskFilesystemStack()
        ->symlink('drupal_build/composer-scripts', 'scripts')
        ->run();
    }

    if ($this->dockerEnable()) {
      // Make sure the docker config files are present.
      $this->copyDockerFiles();

      // Build and start docker container.
      $this->dockerUp();
    }

    // Build drupal project.
    $this->build();

    $drupal = $this->config->get('settings.Drupal');
    $webdir = $this->config->get('settings.WebDir');

    // Create base settings.php:
    if ('7' == $drupal) {
      if (!is_file($webdir . '/sites/default/settings.php')) {
        $this->taskFilesystemStack()
          ->copy($webdir . '/sites/default/default.settings.php', $webdir . '/sites/default/settings.php')
          ->run();
      }
    }

    // Create local Config files.
    $this->createLocalConfig();

    // Add extra config files.
    $this->addExtraConfig();

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
   * Build drupal site.
   *
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
   * @aliases b
   */
  public function build(array $opts = ['no-docker' => NULL, 'not-interactive' => FALSE, 'force' => FALSE]) {
    $this->say("Build " . $this->getProjectName() . ' project.');

    if ($this->dockerEnable($opts['no-docker'])) {
      if (!$this->dockerIsRun()) {
        $this->dockerUp();
      }
    }

    $change = $this->rebuildableCondition();
    if (empty($change) || $change['make'] || $opts['force']) {
      $make_file = 'drupal.make';
      $devEnvironment = $this->config->get('settings.DevEnvironment');
      if ($devEnvironment) {
        $make_file = 'drupal-dev.make';
      }

      // Remove web directory.
      $webdir = $this->config->get('settings.WebDir');
      $drushCmd = $this->config->get('settings.DrushCmd');

      $cmd = $this->taskDrushStack($drushCmd)
        ->drush('make --concurrency=4 ' . $make_file . ' ' . $webdir . '.tmp');
      $this->cmdExec($cmd, $opts['no-docker'], !$opts['not-interactive']);

      if (!is_dir($webdir . '.tmp/sites/all/modules')) {
        $this->say("Drush Make build failed, correct it before re-executing phing, docroot wasn't manipulated!");
        return;
      }

      if (is_dir($webdir)) {
        // Copy default settings.php.
        if (is_file($webdir . '/sites/default/settings.php') || is_link($webdir . '/sites/default/settings.php')) {
          $this->taskExec('cp -PR ' . $webdir . '/sites/default/settings.php' . ' ' . $webdir . '.tmp/sites/default/settings.php')
            ->run();
        }

        // Copy default all extra settings.php.
        $this->taskExec('cp -PR ' . $webdir . '/sites/default/*.settings.php' . ' ' . $webdir . '.tmp/sites/default/')
          ->run();
        $this->taskExec('cp -PR ' . $webdir . '/sites/default/settings.*.php' . ' ' . $webdir . '.tmp/sites/default/')
          ->run();

        $docroot = realpath($webdir);
        $docroot_length = mb_strlen($docroot);

        $localdirs = (array) $this->config->get('settings.SyncServer.localFilesDir');
        if (!empty($localdirs)) {
          $this->yell('Build System SyncServer configuration is DEPRECATED!!!', 50, 'red');
          $this->yell('cat drupal_build/DEPRECATED.txt', 50, 'red');
        }

        if (empty($localdirs)) {
          $syncOptions = $this->config->get('settings.SyncOptions.' . $this->getConfigEnv());
          $localdirs = (array) $syncOptions['localFilesDir'];
        }

        $this->taskExec('chmod u+w ' . $webdir . '/sites/default')
          ->run();

        foreach ($localdirs as $localdir) {
          $files_dir = realpath($webdir . '/' . $localdir);
          if (is_link($webdir . '/' . $localdir)) {
            $this->taskExec('mv ' . $webdir . '/' . $localdir . ' ' . $webdir . '.tmp/' . $localdir)
              ->run();
          }
          else {
            if ($files_dir && $docroot == mb_substr($files_dir, 0, $docroot_length)) {
              $this->taskExec('mv ' . $webdir . '/' . $localdir . ' ' . $webdir . '.tmp/' . $localdir)
                ->run();
            }
          }
        }

        $this->taskFilesystemStack()
          ->chmod($webdir, 0777, 0000, TRUE)
          ->run();

        $this->taskDeleteDir($webdir)
          ->run();
      }

      $this->taskExec('mv ' . $webdir . '.tmp ' . $webdir)
        ->run();
    }

    $this->linkProfiles();
    $this->linkModules();
    $this->linkThemes();
    $this->linkLibraries();

    $this->copyDrupalExtras();

    $this->installThemeComponent($opts);
    $this->compileCSS($opts);
    $this->compassBuild($opts);
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

    if (is_file('docker-sync.yml')) {
      // Run docker-sync clean.
      $this->taskDockerSyncClean()
        ->run();
    }

    if ($this->dockerEnable()) {
      // Remove docker containers.
      $this->dockerDestroy();
    }

    // Remove vendor directory.
    $this->taskFilesystemStack()
      ->remove('vendor')
      ->run();

    // Remove web directory.
    $webdir = $this->config->get('settings.WebDir');
    $this->taskFilesystemStack()
      ->remove($webdir)
      ->run();

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
   * Run export UID.
   */
  protected function runExport() {
    $this->say('Exporting UID.');
    $this->envExtra['UID'] = posix_getuid();
    putenv("UID=" . $this->envExtra['UID']);
  }

  /**
   * Create local drupal configuration.
   */
  protected function createLocalConfig() {
    if ($this->config->get('settings.CreateLocalSettings')) {
      $sql = $this->config->get('settings.DevSqlServer');
      if (empty($sql)) {
        $sql = [];
      }

      $webdir = $this->config->get('settings.WebDir');
      $drupal = $this->config->get('settings.Drupal');
      $this->taskLocalConfig('default')
        ->setWebsiteDirectory($webdir)
        ->setDrupalVersion($drupal)
        ->setSqlServer($sql)
        ->run();
    }
  }

  /**
   * Add extra config files.
   */
  protected function addExtraConfig() {
    $webdir = $this->config->get('settings.WebDir');
    $drupal = $this->config->get('settings.Drupal');
    $this->taskExtraConfig('default')
      ->setWebsiteDirectory($webdir)
      ->setDrupalVersion($drupal)
      ->run();
  }

  /**
   * Get drush cache clear command.
   */
  protected function getCacheCmd() {
    $drupal = $this->config->get('settings.Drupal');

    if ('7' == $drupal) {
      return 'cc all';
    }

    return 'cr';
  }

}
