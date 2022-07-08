<?php

namespace d2build\Build\Component\Sync;

/**
 * @file
 * Sync trait file.
 */

/**
 * Trait SyncTrait.
 *
 * @package d2build\Build\Component\Sync
 */
trait SyncTrait {
  use \d2build\Tasks\SyncFiles\loadTasks;
  use \d2build\Tasks\SyncDB\loadTasks;

  /**
   * Sync database and files.
   *
   * @param string $remote_source
   *   Remote sync server source name.
   *
   * @aliases s
   */
  public function sync(string $remote_source = '') {
    if ($this->isLocked()) {
      $this->say('The build system is currently locked. Syncing and reinitializing functions are disabled. If you would like to run them delete the .robo-locked file.');
      return;
    }

    $this->syncDB($remote_source);
    $this->syncFiles($remote_source);
  }

  /**
   * Sync database.
   *
   * @param string $remote_source
   *   Remote sync server source name.
   *
   * @aliases sdb
   */
  public function syncDB(string $remote_source = '') {
    if ($this->isLocked()) {
      $this->say('The build system is currently locked. Syncing and reinitializing functions are disabled. If you would like to run them delete the .robo-locked file.');
      return;
    }

    $this->say("Sync database:");

    $sync_server = $this->config->get('settings.SyncServer');
    if (!empty($sync_server)) {
      $this->yell('Build System SyncServer configuration is DEPRECATED!!!', 50, 'red');
      $this->yell('cat drupal_build/DEPRECATED.txt', 50, 'red');
    }

    $project = $this->getProjectName();
    if (empty($remote_source)) {
      $remote_source = $this->config->get('settings.SyncDefaultSource');
    }

    $this->say("Connection source: " . $remote_source);
    $remote = $this->config->get('settings.connections.' . $remote_source);

    if (empty($remote)) {
      $remote = [];
      if ('local' != $remote_source) {
        $this->yell('The connections source not found!', 50, 'red');
        return;
      }
    }
    else {
      $remote += [
        'port' => 22,
      ];
    }

    $sync_options = $this->config->get('settings.SyncOptions.' . $remote_source) ?: [];

    $webdir = $this->config->get('settings.WebDir');
    $appDir = $this->config->get('settings.AppDir');
    $drushCmd = $this->config->get('settings.DrushCmd');
    $dockerConf = $this->config->get('settings.docker');
    $dockerConf['webContainerID'] = $this->getDockerContainerID($this->getDockerWebContainerName());

    $multisite = $this->config->get('settings.MultiSite');

    if ($this->dockerEnable()) {
      if (!$this->dockerIsRun()) {
        $this->dockerUp();
      }
    }

    $this->taskSyncDB()
      ->setBasePath($this->getBasePath())
      ->setDockerContainers($dockerConf)
      ->setProjectName($project)
      ->setRemoteServer($remote)
      ->setSyncOptions($sync_options)
      ->setCacheCommand($this->getCacheCmd())
      ->setAppDirectory($appDir)
      ->setWebsiteDirectory($webdir)
      ->setDrushCmd($drushCmd)
      ->setMultiSite($multisite)
      ->run();
  }

  /**
   * Sync files.
   *
   * @param string $remote_source
   *   Remote sync server source name.
   *
   * @aliases sfs
   */
  public function syncFiles(string $remote_source = '') {
    if ($this->isLocked()) {
      $this->say('The build system is currently locked. Syncing and reinitializing functions are disabled. If you would like to run them delete the .robo-locked file.');
      return;
    }

    $this->say("Sync files");

    $sync_server = $this->config->get('settings.SyncServer');
    if (!empty($sync_server)) {
      $this->yell('Build System SyncServer configuration is DEPRECATED!!!', 50, 'red');
      $this->yell('cat drupal_build/DEPRECATED.txt', 50, 'red');
    }

    $project = $this->getProjectName();

    if (empty($remote_source)) {
      $remote_source = $this->config->get('settings.SyncDefaultSource');
    }

    $remote = $this->config->get('settings.connections.' . $remote_source);
    if (!empty($remote)) {
      $sync_options = $this->config->get('settings.SyncOptions.' . $remote_source);
      $webdir = $this->config->get('settings.WebDir');

      $this->taskSyncFiles()
        ->setProjectName($project)
        ->setRemoteServer($remote)
        ->setSyncOptions($sync_options)
        ->setWebsiteDirectory($webdir)
        ->run();
    }
  }

}
