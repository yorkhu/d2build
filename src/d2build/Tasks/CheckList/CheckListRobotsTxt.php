<?php

namespace d2build\Tasks\CheckList;

use Robo\Contract\BuilderAwareInterface;
use Robo\Robo;
use Robo\TaskAccessor;
use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Common\IO;
use Robo\Task\Base;

/**
 * Checklist robot.txt task.
 *
 * @package d2build\Tasks\CheckList
 */
class CheckListRobotsTxt extends BaseTask implements BuilderAwareInterface {
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
   * Project base path.
   *
   * @var string
   */
  protected $basePath;

  /**
   * Website directory.
   *
   * @var string
   */
  protected $webDir;

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
   * Check robot.txt.
   */
  protected function checkRobotsTxt() {
    // Save actual logger and disabled console log:
    $result = $this->taskExec('grep -E "^Disallow: /$" ' . $this->basePath . '/' . $this->webDir .'/robots.txt |cat')
      ->silent(TRUE)
      ->printOutput(FALSE)
      ->run();

    $message = $result->getMessage();
    if (!empty($message)) {
      $this->exitCode = 1;
      $this->errorMessage = $message . ' detected in robots.txt!';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->checkRobotsTxt();

    if (!$this->exitCode) {
      $this->yell('The robots.txt is right!', 0, 'green');
    }

    return new Result(
      $this,
      $this->exitCode,
      $this->errorMessage,
      []
    );
  }

}
