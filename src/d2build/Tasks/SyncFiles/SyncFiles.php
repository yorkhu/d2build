<?php

namespace d2build\Tasks\SyncFiles;

use Robo\Contract\BuilderAwareInterface;
use Robo\TaskAccessor;
use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Common\IO;
use Robo\Task\Remote;

/**
 * Class SyncFiles.
 *
 * @package d2build\Tasks\SyncFiles
 */
class SyncFiles extends BaseTask implements BuilderAwareInterface {
  use TaskAccessor;
  use IO;
  use Remote\Tasks;

  /**
   * Project name.
   *
   * @var string
   */
  protected $projectName;

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
   * Website directory.
   *
   * @var string
   */
  protected $webDir;

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
   * Sync files.
   */
  public function syncFiles() {
    $remote = $this->remoteServer;
    $options = $this->syncOptions;

    if (!empty($remote) && isset($remote['user']) && isset($remote['server']) && isset($remote['WebDir'])) {
      $webdir = $this->webDir;
      $localdir = '';
      if (is_dir($webdir)) {
        $localdir = $webdir . '/';
      }

      $remoteFilesDirs = (array) $options['remoteFilesDir'];
      $localFilesDirs = (array) $options['localFilesDir'];
      foreach ($remoteFilesDirs as $key => $remoteFilesDir) {
        $this->taskRsync()
          ->fromUser($remote['user'])
          ->fromHost($remote['server'])
          ->fromPath($remote['WebDir'] . '/' . $remoteFilesDir . '/')
          ->option('port', $remote['port'])
          ->toPath('./' . $localdir . $localFilesDirs[$key])
          ->recursive()
          ->archive()
          ->compress()
          ->itemizeChanges()
          ->excludeVcs()
          ->checksum()
          ->wholeFile()
          ->verbose()
          ->progress()
          ->humanReadable()
          ->stats()
          ->run();

      }
    }
    else {
      $this->exitCode = 1;
      $this->errorMessage = 'Not found remote server configuration.';
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->syncFiles();
    $this->printTaskInfo('Synced files');

    return new Result(
      $this,
      $this->exitCode,
      $this->errorMessage,
      []
    );
  }

}
