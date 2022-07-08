<?php

/**
 * @file
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

use Robo\Tasks;

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
  use \d2build\Build\Component\CheckList\CheckListTrait;
  use \d2build\Build\Component\Env\EnvTrait;

  use \d2build\Tasks\LocalConfig\loadTasks;
  use \d2build\Tasks\ExtraConfig\loadTasks;
  use \d2build\Factory\DeployFactory;

  /**
   * Project builder base directory.
   *
   * @var string
   */
  protected $roboDir = '';

  /**
   * Created env file.
   *
   * @var boolean
   */
  protected $isNewEnv = FALSE;

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
    $this->showEnv();

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

    // Create local Config files.
    $this->createLocalConfig();

    // Load extra config files in settings.php:
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
    $this->say('Build ' . $this->getProjectName() . ' project.');
    $this->showEnv();

    if ($this->dockerEnable($opts['no-docker'])) {
      if (!$this->dockerIsRun()) {
        $this->dockerUp();
      }
    }

    $webdir = $this->config->get('settings.WebDir');
    if (is_dir($webdir . '/sites/default')) {
      $this->taskExec('chmod u+w ' . $webdir . '/sites/default')
        ->run();
    }

    $change = $this->rebuildableCondition();
    if (empty($change) || $change['composer'] || $opts['force']) {
      // Run composer install.
      $composerCmd = $this->config->get('settings.ComposerCmd');
      $cmd = $this->taskComposerInstall($composerCmd);
      if ($opts['not-interactive']) {
        $cmd->noInteraction();
      }

      $devEnvironment = $this->config->get('settings.DevEnvironment');
      if (!$devEnvironment) {
        $cmd->noDev();
      }

      $this->cmdExec($cmd, $opts['no-docker'], !$opts['not-interactive']);
      $this->drupalInit($opts['no-docker']);
    }

    $this->linkProfiles();
    $this->linkModules();
    $this->linkThemes();
    $this->linkLibraries();

    $this->copyDrupalExtras();

    $theme_build = $this->config->get('settings.ThemeBuild');
    if ($theme_build) {
      $this->installThemeComponent($opts);
      $this->compileCSS($opts);
    }
  }

  /**
   * Show environment.
   */
  private function showEnv() {
    if ('master' == $this->getConfigEnv()) {
      $this->yell('Environment: ' . $this->getConfigEnv(), 30, 'green');
    }
    else {
      $this->yell('Environment: ' . $this->getConfigEnv(), 30, 'yellow');
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

    // Remove docker compose file.
    if (is_file(__DIR__ . '/../docker-compose.yml')) {
      $this->taskFilesystemStack()
        ->remove(__DIR__ . '/../docker-compose.yml')
        ->run();
    }

    // Remove docker sync file.
    if (is_file(__DIR__ . '/../docker-sync.yml')) {
      $this->taskFilesystemStack()
        ->remove(__DIR__ . '/../docker-sync.yml')
        ->run();
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
   * Deploy project.
   *
   * @param string $deploy
   *   Deploy name.
   */
  public function deploy(string $deploy = '') {
    if (empty($deploy)) {
      $deploy_options = $this->config->get('settings.deploy');
      if (!empty($deploy_options)) {
        $deploy = array_key_first($deploy_options);
      }
      else {
        $this->yell('Deploy config not found!', 50, 'red');
        return;
      }
    }

    $remote = $this->config->get('settings.connections.' . $deploy);
    $deploy_option = $this->config->get('settings.deploy.' . $deploy);

    // Prepair deploy:
    $errors = $this->taskDeploy($remote, $deploy_option)
      ->prepareDeploy();

    $continue = TRUE;
    if ($errors) {
      foreach ($errors as $error) {
        if (isset($error['exit']) && $error['exit']) {
          return;
        }
        $this->yell($error['msg'], 50, 'yellow');
      }
      $continue = $this->confirm('Do you want to continue?', TRUE);
    }

    // Pre deploy:
    if ($continue) {
      $errors = $this->taskDeploy($remote, $deploy_option)
        ->preDeploy();

      if ($errors) {
        foreach ($errors as $error) {
          if (isset($error['exit']) && $error['exit']) {
            return;
          }
          $this->yell($error['msg'], 50, 'yellow');
        }
        $continue = $this->confirm('Do you want to continue?', TRUE);
      }
    }

    // Deploy:
    if ($continue) {
      $error = $this->taskDeploy($remote, $deploy_option)
        ->deploy();

      if ($errors) {
        foreach ($errors as $error) {
          if (isset($error['exit']) && $error['exit']) {
            return;
          }
          $this->yell($error['msg'], 50, 'yellow');
        }
        $continue = $this->confirm('Do you want to continue?', TRUE);
      }
      if ($error) {
        $this->yell('Problem detected in remote server.', 50, 'yellow');
        $continue = $this->confirm('Continue', TRUE);
      }
    }

    // Post deploy:
    if ($continue) {
      $this->taskDeploy($remote, $deploy_option)
        ->postDeploy();
    }
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
      $this->taskLocalConfig('default')
        ->setWebsiteDirectory($webdir)
        ->setSqlServer($sql)
        ->setConfigEnv($this->getConfigEnv())
        ->run();
    }
  }

  /**
   * Add extra config files.
   */
  protected function addExtraConfig() {
    $webdir = $this->config->get('settings.WebDir');
    $this->taskExtraConfig('default')
      ->setWebsiteDirectory($webdir)
      ->run();
  }

  /**
   * Download drupal core extra files and prepair configuration.
   */
  protected function drupalInit($no_docker = FALSE) {
    $webdir = $this->config->get('settings.WebDir');

    if (!is_file($webdir . '/index.php')) {
      $composerCmd = $this->config->get('settings.ComposerCmd');
      $cmd_scaffold = $this->taskExec($composerCmd . ' run-script drupal-scaffold');
      $cmd_post_inst = $this->taskExec($composerCmd . ' run-script post-install-cmd');

      $this->cmdExec($cmd_scaffold, $no_docker);
      $this->cmdExec($cmd_post_inst, $no_docker);
    }

    if (is_file($webdir . '/sites/default/settings.php')) {
      // Load extra config files in settings.php:
      $this->addExtraConfig();
    }
  }

  /**
   * Get drush cache clear command.
   */
  protected function getCacheCmd() {
    return 'cr';
  }

}
