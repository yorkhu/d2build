<?php

namespace d2build\Tasks\CheckList;

use Robo\Contract\BuilderAwareInterface;
use Robo\TaskAccessor;
use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Common\IO;
use Robo\Task\Base;

/**
 * Checklist diff task.
 *
 * @package d2build\Tasks\CheckList
 */
class CheckListRunRemote extends BaseTask implements BuilderAwareInterface {
  use TaskAccessor;
  use IO;
  use Base\Tasks;

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
   * Remote connection.
   *
   * @var array
   */
  protected $remote = [];

  /**
   * Set connections.
   *
   * @param array $remote
   *   Remote connections.
   *
   * @return $this
   */
  public function setRemote($remote) {
    $this->remote = $remote;

    return $this;
  }

  /**
   * Get connection.
   *
   * @return $this
   */
  protected function getRemote() {
    $port = $this->remote['port'] ?? '22';
    $user = isset($this->remote['user']) ? $this->remote['user'] . '@' : '';
    return $user . $this->remote['server'] . ' -p' . $port;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->taskExecStack()
      ->exec('ssh ' . $this->getRemote() . ' -C "cd ' . $this->remote['HomeDir'] . '; ./robo checklist"')
      ->run();

    return new Result(
      $this,
      $this->exitCode,
      $this->errorMessage,
      []
    );
  }

}
