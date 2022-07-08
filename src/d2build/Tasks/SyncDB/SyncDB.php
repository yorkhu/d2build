<?php

namespace d2build\Tasks\SyncDB;

use Robo\Contract\BuilderAwareInterface;
use Robo\TaskAccessor;
use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Common\IO;
use Robo\Task\Base;
use Robo\Task\Docker;
use Boedah\Robo\Task\Drush;

/**
 * Class SyncDB.
 *
 * @package d2build\Tasks\SyncDB
 */
class SyncDB extends BaseTask implements BuilderAwareInterface {
  use TaskAccessor;
  use IO;
  use Base\Tasks;
  use Drush\loadTasks;
  use Docker\Tasks;

  /**
   * Set docker container name.
   *
   * @var array
   */
  protected $dockerContainers;

  /**
   * Project name.
   *
   * @var string
   */
  protected $projectName;

  /**
   * Project base path.
   *
   * @var string
   */
  protected $basePath;

  /**
   * Remote server settings.
   *
   * @var string
   */
  protected $remoteServer;

  /**
   * Remote server sync options.
   *
   * @var string
   */
  protected $syncOptions;

  /**
   * Application directory.
   *
   * @var string
   */
  protected $appDir;

  /**
   * Website directory.
   *
   * @var string
   */
  protected $webDir;

  /**
   * Drush cmd.
   *
   * @var string
   */
  protected $drushCmd;

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
   * Drush cache command.
   *
   * @var string
   */
  protected $cacheCommand = 'cache-rebuild';

  /**
   * Multisite list.
   *
   * @var int
   */
  protected $multiSites = 0;

  /**
   * Set project name.
   *
   * @param string $projectName
   *   Project name.
   *
   * @return $this
   */
  public function setProjectName($projectName) {
    $this->projectName = $projectName;

    return $this;
  }

  /**
   * Set docker container name.
   *
   * @param string $dockerContainers
   *   Docker containers.
   *
   * @return $this
   */
  public function setDockerContainers($dockerContainers) {
    $this->dockerContainers = $dockerContainers;

    return $this;
  }

  /**
   * Set project base path.
   *
   * @param string $basePath
   *   Project name.
   *
   * @return $this
   */
  public function setBasePath($basePath) {
    $this->basePath = $basePath;

    return $this;
  }

  /**
   * Set cache command.
   *
   * @param string $cacheCommand
   *   Cache command name.
   *
   * @return $this
   */
  public function setCacheCommand($cacheCommand) {
    $this->cacheCommand = $cacheCommand;

    return $this;
  }

  /**
   * Set remote server.
   *
   * @param array $remoteServer
   *   Remote server configuration.
   *
   * @return $this
   */
  public function setRemoteServer(array $remoteServer) {
    $remoteServer += [
      'port' => 22,
    ];

    $this->remoteServer = $remoteServer;

    return $this;
  }

  /**
   * Set sync options.
   *
   * @param array $syncOptions
   *   Remote server sync options.
   *
   * @return $this
   */
  public function setSyncOptions(array $syncOptions) {
    $this->syncOptions = $syncOptions;

    return $this;
  }

  /**
   * Set application directory.
   *
   * @param string $appdir
   *   Application directory.
   *
   * @return $this
   */
  public function setAppDirectory($appdir) {
    if (empty($appdir)) {
      $appdir = '/var/www';
    }
    $this->appDir = $appdir;

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
    if (!empty($webdir)) {
      $webdir .= '/';
    }
    $this->webDir = $webdir;

    return $this;
  }

  /**
   * Set website directory.
   *
   * @param string $drushCmd
   *   Drush command.
   *
   * @return $this
   */
  public function setDrushCmd($drushCmd) {
    if (empty($drushCmd)) {
      $drushCmd = 'drush';
    }
    $this->drushCmd = $drushCmd;

    return $this;
  }

  /**
   * Set website directory.
   *
   * @param integer $multisite
   *   1 - Is multi site.
   *
   * @return $this
   */
  public function setMultiSite($multisite) {
    $this->multiSites = $multisite;

    return $this;
  }

  /**
   * Sync files.
   */
  public function syncDB() {
    $remote = $this->remoteServer;
    $options = $this->syncOptions;

    if (!empty($remote) && isset($remote['server'])) {
      if (!(isset($remote['user']) && isset($remote['HomeDir'])))
      $this->exitCode = 1;
      $this->errorMessage = 'Not found remote server configuration.';

    }
    elseif (empty($remote)) {
      $this->exitCode = 1;
      $this->errorMessage = 'Invalid sync source or configuration.';
    }

    $webdir = $this->webDir;
    $sites = [];
    if (!empty($this->multiSites)) {
      foreach ($this->multiSites as $dir => $dbs) {
        if (is_array($dbs)) {
          foreach ($dbs as $db) {
            $sites[$dir][$db] = $db . '.';
          }
        }
        else {
          $sites[$dir][$dbs] = $dbs . '.';
        }
      }
    }
    else {
      $dbs = [];
      if (isset($options['sync_source']['databases'])) {
        foreach ($options['sync_source']['databases'] as $db_name) {
          $dbs[$db_name] = $db_name . '.';
        }
      }
      else {
        $dbs['default'] = '';
      }
      $sites = [$dbs];
    }

    if (!empty($sites)) {
      foreach ($sites as $dir => $dbs) {
        $site_prefix = '';
        $uri = 'default';
        $localDir = '';

        if (is_string($dir)) {
          $site_prefix = $dir . '.';
          $uri = $dir;
        }

        if (is_dir($webdir)) {
          $localDir = $webdir;
        }
        $this->printTaskInfo("* Sync site: ");

        if (!empty($dbs)) {
          foreach ($dbs as $db_name => $db_prefix) {
            // Download DB:
            if (!empty($remote['server'])) {
              $this->downloadDB($db_name, $db_prefix, $uri, $localDir, $site_prefix);
            }

            // Backup local DB:
            $this->backupLocalDB($db_name, $db_prefix, $uri, $webdir, $localDir, $site_prefix);

            if (is_file($localDir . '.database.' . $site_prefix . $db_prefix . 'sql')) {
              // Remove tables:
              $this->removeTables($db_name, $uri);

              // Import DB:
              $this->importDB($db_name, $db_prefix, $uri, $localDir, $site_prefix);
            }

            if ('default' === $db_name) {
              if ($options['sanitize']) {
                $this->sanitizeDB($db_name, $uri);
              }

              if ($options['resetPassword']) {
                $this->resetPassword($uri);
              }

              $this->cacheClear($uri);
            }
          }
        }
      }
    }

    return $this;
  }

  /**
   * Get account information.
   *
   * @return string
   *   SSH account.
   */
  private function getAccount() {
    $remote = $this->remoteServer;
    return !empty($remote['user']) ? $remote['user'] . '@' . $remote['server'] : $remote['server'];
  }

  /**
   * Download remote db
   *
   * @param $db_name
   *   DB name
   * @param $db_prefix
   *   DB prefix
   * @param $uri
   *   Site uri
   * @param $localDir
   *   Local directory
   * @param $site_prefix
   *   Site name
   *
   * @throws \Robo\Exception\TaskException
   */
  private function downloadDB($db_name, $db_prefix, $uri, $localDir, $site_prefix) {
    $webdir = $this->webDir;
    $remote = $this->remoteServer;
    $account = $this->getAccount();

    $this->printTaskInfo("* Download database: " . $db_name);

    if ($remote['EnableDocker']) {
      $this->taskExecStack()
        ->exec('ssh ' . $account . ' -p' . $remote['port'] . ' -C "cd ' . $remote['HomeDir'] . '; docker exec -i \$(docker-compose ps -q ' . $this->dockerContainers['webContainer'] . ') '. $this->drushCmd .' -l ' . $uri . ' -r ' . $remote['AppDir'] . '/' . $webdir . ' sql-dump --database=' . $db_name . ' --structure-tables-list=cache,cache_*,history,sessions,watchdog" > ' . $localDir . '.database.' . $site_prefix . $db_prefix . 'sql')
        ->run();
    }
    else {
      $this->taskExecStack()
        ->exec('ssh ' . $account . ' -p' . $remote['port'] . ' -C "cd ' . $remote['WebDir'] . '; '. $this->drushCmd .' -l ' . $uri . ' sql-dump --database=' . $db_name . ' --structure-tables-list=cache,cache_*,history,sessions,watchdog" > ' . $localDir . '.database.' . $site_prefix . $db_prefix . 'sql')
        ->run();
    }
  }

  /**
   * Backup local db
   *
   * @param $db_name
   *   DB name
   * @param $db_prefix
   *   DB prefix
   * @param $uri
   *   Site uri
   * @param $webdir
   *   Web directory
   * @param $localDir
   *   Local directory
   * @param $site_prefix
   *   Site name
   *
   * @throws \Robo\Exception\TaskException
   */
  private function backupLocalDB($db_name, $db_prefix, $uri, $webdir, $localDir, $site_prefix) {
    $drupalRoot = $this->appDir . '/' . $webdir;

    $this->printTaskInfo("* Backup local database: " . $db_name);
    $cmd = $this->taskDrushStack()
      ->drush('sql-dump -l ' . $uri . ' -r ' . $drupalRoot . ' --database=' . $db_name . ' --structure-tables-list=cache,cache_*,history,sessions,watchdog> ./' . $localDir . '.database.backup.' . $site_prefix . $db_prefix . 'sql');

    $this->runCmd($cmd);
  }

  /**
   * Remove all table in db.
   *
   * @param $db_name
   *   DB name
   * @param $uri
   *   Site uri
   */
  private function removeTables($db_name, $uri) {
    $webdir = $this->webDir;
    $drupalRoot = $this->appDir . '/' . $webdir;

    $show_db_base_cmd = '/bin/bash -c \'cd ' . $drupalRoot . ';'. $this->drushCmd .' -l ' . $uri . ' -r ' . $drupalRoot . ' sqlq --database=' . $db_name . ' "SHOW TABLES"';
    $cmd = $this->taskExec($show_db_base_cmd);

    $get_cmd = $this->taskDockerExec($this->dockerContainers['webContainerID'])
      ->interactive()
      ->option('user', posix_getuid())
      ->exec($cmd);

    $show_db_cmd = $get_cmd->getCommand() . "'";

    // Drop all existing tables:
    $tables = trim(shell_exec($show_db_cmd));
    if (!empty($tables)) {
      $this->printTaskInfo("* Remove all table: " . $db_name);

      $remove_cmd = '/bin/bash -c \'cd ' . $drupalRoot . ';TABLES=""; for i in `'. $this->drushCmd .' -l ' . $uri . ' -r ' . $drupalRoot . ' sqlq --database=' . $db_name . ' "show tables"`; do TABLES="$i, $TABLES"; done; TABLES="$(echo -e "${TABLES::-2}")"; '. $this->drushCmd .' -l ' . $uri . ' -r ' . $drupalRoot . ' sqlq --database=' . $db_name . ' "SET FOREIGN_KEY_CHECKS = 0; DROP TABLE $TABLES; SET FOREIGN_KEY_CHECKS = 1";\'';
      $cmd = $this->taskExec($remove_cmd);
      $this->runCmd($cmd);
    }
  }

  /**
   * Import DB
   *
   * @param $db_name
   *   DB name
   * @param $db_prefix
   *   DB prefix
   * @param $uri
   *   Site uri
   * @param $localDir
   *   Local directory
   * @param $site_prefix
   *   Site name
   */
  private function importDB($db_name, $db_prefix, $uri, $localDir, $site_prefix) {
    $webdir = $this->webDir;
    $drupalRoot = $this->appDir . '/' . $webdir;

    $this->printTaskInfo("* Import database: " . $db_name);
    $cmd = $this->taskDrushStack()
      ->drush('sql-cli -l ' . $uri . ' -r ' . $drupalRoot . ' --database=' . $db_name . '< ./' . $localDir . '.database.' . $site_prefix . $db_prefix . 'sql');

    $this->runCmd($cmd);
  }

  /**
   * Sanitize DB
   *
   * @param $db_name
   *   DB name
   * @param $uri
   *   Site uri
   */
  private function sanitizeDB($db_name, $uri) {
    $webdir = $this->webDir;
    $drupalRoot = $this->appDir . '/' . $webdir;

    $this->printTaskInfo("* Sanitize database: " . $db_name);
    $cmd = $this->taskDrushStack()
      ->drupalRootDirectory($drupalRoot)
      ->uri($uri)
      ->drush('sql-sanitize');

    $this->runCmd($cmd);
  }

  /**
   * Set admin password
   *
   * @param $uri
   *   Site uri
   */
  private function resetPassword($uri) {
    $webdir = $this->webDir;
    $drupalRoot = $this->appDir . '/' . $webdir;

    $this->printTaskInfo("* Reset admin password");
    $resetCmd = 'upwd ' . $this->syncOptions['adminUser'] . ' --password=admin';
    if (9 == $this->getDrushVersion()) {
      $resetCmd = 'upwd ' . $this->syncOptions['adminUser'] . ' admin';
    }
    $cmd = $this->taskDrushStack()
      ->drupalRootDirectory($drupalRoot)
      ->uri($uri)
      ->drush($resetCmd);

    $this->runCmd($cmd);
  }

  /**
   * Set admin password
   *
   * @param $uri
   *   Site uri
   */
  private function cacheClear($uri) {
    $webdir = $this->webDir;
    $drupalRoot = $this->appDir . '/' . $webdir;

    $this->printTaskInfo("* Cache clear");

    $cmd = $this->taskDrushStack()
      ->drupalRootDirectory($drupalRoot)
      ->uri($uri)
      ->drush($this->cacheCommand);

    $this->runCmd($cmd);
  }

  /**
   * Detect drush main version.
   *
   * @return int
   *   The drush main version.
   */
  private function getDrushVersion() {
    $version = 8;

    $drush_info_file = __DIR__ . '/../../../../../vendor/drush/drush/drush.info';
    if (is_file($drush_info_file)) {
      $drush_version = parse_ini_file($drush_info_file);
      if (version_compare($drush_version['drush_version'], '9.0.0', '>=')) {
        $version = 9;
      }
      else {
        $version = 8;
      }
    }

    return $version;
  }

  /**
   * Run command docker or shell.
   *
   * @param $cmd
   *   Robo command.
   */
  protected function runCmd($cmd) {
    if (!empty($this->dockerContainers)) {
      $this->taskDockerExec($this->dockerContainers['webContainerID'])
        ->interactive()
        ->option('user', posix_getuid())
        ->exec($cmd)
        ->run();
    }
    else {
      $cmd->run();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->syncDB();
    $this->printTaskInfo('Synced DB');

    return new Result(
      $this,
      $this->exitCode,
      $this->errorMessage,
      []
    );
  }

}
