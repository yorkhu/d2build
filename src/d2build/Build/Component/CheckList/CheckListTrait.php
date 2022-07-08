<?php

namespace d2build\Build\Component\CheckList;

/**
 * @file
 * CheckList trait file.
 */

/**
 * Trait SyncTrait.
 *
 * @package d2build\Build\Component\Sync
 */
trait CheckListTrait {
  use \d2build\Tasks\CheckList\Tasks;

  /**
   * Checklist.
   *
   * @param string $remote_source
   *   Remote source name.
   *
   * @aliases cl
   */
  public function checklist($remote_source = '') {
    $this->say("Run CheckList:");
    if (!empty($remote_source)) {
      $this->checkListRunRemote($remote_source);
    }
    else {
      $this->checklistDiff();
      $this->checklistDrushConfig();
      $this->checklistDrushUpDb();
      $this->checklistDrushStatus();
      $this->checklistRobotsTxt();
      $this->checklistDrushMaintenanceMode();
      $this->checklistDrushCacheCheck();
    }
  }

  /**
   * Checklist extra files diff.
   */
  public function checklistDiff() {
    $this->say("Extra files diff:");

    $webdir = $this->config->get('settings.WebDir');
    $extradir = $this->config->get('settings.DrupalExtra');

    $this->taskCheckListDiff()
      ->setBasePath($this->getBasePath())
      ->setWebsiteDirectory($webdir)
      ->setExtraDirectory($extradir)
      ->run();
  }

  /**
   * Checklist robots.txt.
   */
  public function checklistRobotsTxt() {
    $this->say("Check robots.txt:");

    $webdir = $this->config->get('settings.WebDir');

    $this->taskCheckListRobotsTxt()
      ->setBasePath($this->getBasePath())
      ->setWebsiteDirectory($webdir)
      ->run();
  }

  /**
   * Checklist drush config diff.
   */
  public function checklistDrushConfig() {
    $this->say("Check config:");

    $webdir = $this->config->get('settings.WebDir');
    $drushCmd = $this->config->get('settings.DrushCmd');
    $enableDocker = $this->config->get('settings.EnableDocker');

    $dockerConf = [];
    if ($enableDocker) {
      $dockerContainerID = $this->getDockerContainerID($this->getDockerWebContainerName());
      if (!empty($dockerContainerID)) {
        $dockerConf = $this->config->get('settings.docker');
        $dockerConf['webContainerID'] = $this->getDockerContainerID($this->getDockerWebContainerName());
      }
    }

    $multisite = $this->config->get('settings.MultiSite');

    if ($this->dockerEnable()) {
      if (!$this->dockerIsRun()) {
        $this->dockerUp();
      }
    }

    $this->taskCheckListDrushConfig()
      ->setDockerContainers($dockerConf)
      ->setWebsiteDirectory($webdir)
      ->setDrushCmd($drushCmd)
      ->setMultiSite($multisite)
      ->run();
  }

  /**
   * Checklist drush updb check.
   */
  public function checklistDrushUpDb() {
    $this->say("Check update database:");

    $webdir = $this->config->get('settings.WebDir');
    $drushCmd = $this->config->get('settings.DrushCmd');
    $enableDocker = $this->config->get('settings.EnableDocker');

    $dockerConf = [];
    if ($enableDocker) {
      $dockerContainerID = $this->getDockerContainerID($this->getDockerWebContainerName());
      if (!empty($dockerContainerID)) {
        $dockerConf = $this->config->get('settings.docker');
        $dockerConf['webContainerID'] = $this->getDockerContainerID($this->getDockerWebContainerName());
      }
    }

    $multisite = $this->config->get('settings.MultiSite');

    if ($this->dockerEnable()) {
      if (!$this->dockerIsRun()) {
        $this->dockerUp();
      }
    }

    $this->taskCheckListDrushUpDb()
      ->setDockerContainers($dockerConf)
      ->setWebsiteDirectory($webdir)
      ->setDrushCmd($drushCmd)
      ->setMultiSite($multisite)
      ->run();
  }

  /**
   * Checklist status page.
   */
  public function checklistDrushStatus() {
    $this->say("Check drupal status:");

    $webdir = $this->config->get('settings.WebDir');
    $drushCmd = $this->config->get('settings.DrushCmd');
    $enableDocker = $this->config->get('settings.EnableDocker');

    $dockerConf = [];
    if ($enableDocker) {
      $dockerContainerID = $this->getDockerContainerID($this->getDockerWebContainerName());
      if (!empty($dockerContainerID)) {
        $dockerConf = $this->config->get('settings.docker');
        $dockerConf['webContainerID'] = $this->getDockerContainerID($this->getDockerWebContainerName());
      }
    }

    $multisite = $this->config->get('settings.MultiSite');

    if ($this->dockerEnable()) {
      if (!$this->dockerIsRun()) {
        $this->dockerUp();
      }
    }

    $this->taskCheckListDrushStatus()
      ->setDockerContainers($dockerConf)
      ->setWebsiteDirectory($webdir)
      ->setDrushCmd($drushCmd)
      ->setMultiSite($multisite)
      ->run();
  }

  /**
   * Checklist Drush Maintenance Mode.
   */
  public function checklistDrushMaintenanceMode() {
    $this->say("Check drupal maintenance mode:");

    $webdir = $this->config->get('settings.WebDir');
    $drushCmd = $this->config->get('settings.DrushCmd');
    $enableDocker = $this->config->get('settings.EnableDocker');

    $dockerConf = [];
    if ($enableDocker) {
      $dockerContainerID = $this->getDockerContainerID($this->getDockerWebContainerName());
      if (!empty($dockerContainerID)) {
        $dockerConf = $this->config->get('settings.docker');
        $dockerConf['webContainerID'] = $this->getDockerContainerID($this->getDockerWebContainerName());
      }
    }

    $multisite = $this->config->get('settings.MultiSite');

    if ($this->dockerEnable()) {
      if (!$this->dockerIsRun()) {
        $this->dockerUp();
      }
    }

    $this->taskCheckListDrushMaintenanceMode()
      ->setDockerContainers($dockerConf)
      ->setWebsiteDirectory($webdir)
      ->setDrushCmd($drushCmd)
      ->setMultiSite($multisite)
      ->run();
  }

  /**
   * Checklist Drush Cache Check.
   */
  public function checklistDrushCacheCheck() {
    $this->say("Check drupal Cache settings:");

    $webdir = $this->config->get('settings.WebDir');
    $drushCmd = $this->config->get('settings.DrushCmd');
    $enableDocker = $this->config->get('settings.EnableDocker');

    $dockerConf = [];
    if ($enableDocker) {
      $dockerContainerID = $this->getDockerContainerID($this->getDockerWebContainerName());
      if (!empty($dockerContainerID)) {
        $dockerConf = $this->config->get('settings.docker');
        $dockerConf['webContainerID'] = $this->getDockerContainerID($this->getDockerWebContainerName());
      }
    }

    $multisite = $this->config->get('settings.MultiSite');

    if ($this->dockerEnable()) {
      if (!$this->dockerIsRun()) {
        $this->dockerUp();
      }
    }

    $this->taskCheckListDrushCacheCheck()
      ->setDockerContainers($dockerConf)
      ->setWebsiteDirectory($webdir)
      ->setDrushCmd($drushCmd)
      ->setMultiSite($multisite)
      ->run();
  }

  /**
   * Run remote checklist.
   *
   * @param string $remote_source
   *   Remote source name.
   */
  public function checkListRunRemote($remote_source) {
    $remote = $this->config->get('settings.connections.' . $remote_source);

    if (!empty($remote)) {
      $remote += [
        'port' => 22,
      ];

      $this->taskCheckListRunRemote()
        ->setRemote($remote)
        ->run();
    }
  }

}
