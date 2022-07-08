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
class CheckListDiff extends BaseTask implements BuilderAwareInterface {
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
   * Drupal extra directory.
   *
   * @var string
   */
  protected $extraDir;

  /**
   * Extra files list.
   *
   * @var array
   */
  protected $extraFiles;

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
   * Set extra directory.
   *
   * @param string $extradir
   *   Extra directory.
   *
   * @return $this
   */
  public function setExtraDirectory($extradir) {
    if (!empty($extradir)) {
      $extradir .= '/';
    }
    $this->extraDir = $extradir;

    return $this;
  }

  /**
   * Get extra files.
   *
   * @return $this
   */
  protected function getExtraFiles($dir = '', $source = '') {
    if (empty($dir)) {
      $dir = $this->extraDir;
    }

    if (is_dir($dir)) {
      $files = scandir($dir);
      foreach ($files as $file) {
        if ('.' !== $file && '..' !== $file) {
          $basefile = $dir . $file;
          if (is_dir($basefile)) {
            $this->getExtraFiles($basefile . '/', $source . $file  . '/');
          }
          else {
            $this->extraFiles[] = $source . $file;
          }
        }
      }
    }

    return $this;
  }

  /**
   * File diff.
   *
   * @param string $file1
   *   Full path and file name.
   * @param string $file2
   *   Full path and file name.
   */
  protected function diff($file1, $file2) {
    $result = $this->taskExec('diff ' . $file1 . ' ' . $file2 . '| cat')
      ->silent(TRUE)
      ->printOutput(FALSE)
      ->run();

    $message = $result->getMessage();
    if (!empty($message)) {
      $this->exitCode = 1;
      $color = 'cyan';
      $format = "<fg=white;bg=$color;options=bold>%s</fg=white;bg=$color;options=bold>";
      $this->writeln(sprintf($format, " $file2 "));
      $this->writeln($message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->getExtraFiles();

    foreach ($this->extraFiles as $file) {
      $this->diff($this->extraDir . $file, $this->webDir . $file);
    }

    if ($this->exitCode) {
      $this->errorMessage = 'Extra files diff found!';
    }
    else {
      $this->yell('Extra files diff not found!', 0, 'green');
    }

    return new Result(
      $this,
      $this->exitCode,
      $this->errorMessage,
      []
    );
  }

}
