<?php

namespace d2build\Deploy\Source;

use Robo\Common\IO;
use Robo\Contract\BuilderAwareInterface;
use Robo\Task\Base;
use Robo\Task\BaseTask;
use Robo\TaskAccessor;

/**
 * Class DeploySourceBase.
 *
 * @package d2build\Deploy\Source
 */
abstract class DeploySourceBase extends BaseTask implements DeploySourceInterface, BuilderAwareInterface {
  use Base\Tasks;
  use IO;
  use TaskAccessor;

  /**
   * Remote server parameter.
   *
   * @var array
   */
  protected $remote;

  /**
   * Deploy options.
   *
   * @var array
   */
  protected $options;

  /**
   * DeploySourceBase constructor.
   *
   * @param array $remote
   *   Remote server parameter.
   * @param array $options
   *   The deploy options.
   */
  public function __construct(array $remote, array $options) {
    $this->remote = $remote;
    $this->options = $options;
  }

  /**
   * Get server options.
   *
   * @return string
   *   Return server options.
   */
  public function getServer() {
    $server = '';
    switch ($this->remote['ctype']) {
      case 'ssh':
        $port = $this->remote['port'] ?? '22';
        $user = isset($this->remote['user']) ? $this->remote['user'] . '@' : '';
        $server = $user . $this->remote['server'] . ' -p' . $port;

        break;
    }

    return $server;
  }

  /**
   * Get project dir.
   *
   * @return string
   *   Return project dir.
   */
  public function getProjectDir() {
    return $this->remote['HomeDir'];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDeploy() {}

  /**
   * {@inheritdoc}
   */
  public function preDeploy() {}

  /**
   * {@inheritdoc}
   */
  abstract public function deploy();

  /**
   * {@inheritdoc}
   */
  public function postDeploy() {}

}
