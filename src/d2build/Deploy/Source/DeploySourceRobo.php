<?php

namespace d2build\Deploy\Source;

use Robo\Common\OutputAwareTrait;

/**
 * Class DeploySourceRobo.
 *
 * @package d2build\Deploy\Source
 */
class DeploySourceRobo extends DeploySourceBase {
  use OutputAwareTrait;

  /**
   * {@inheritdoc}
   */
  public function prepareDeploy() {
    $errors = [];
    $result = $this->taskExec('ssh ' . $this->getServer() . ' -C "cd ' . $this->getProjectDir() . '; git status -s"')
      ->printOutput(FALSE)
      ->run();

    $git_status = $result->getMessage();
    if (!empty($git_status)) {
      $this->writeln('git status report:');
      $this->writeln($git_status);
      $errors[] = [
        'msg' => 'Problem detected in remote server.',
      ];
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function preDeploy() {
    $errors = [];
    $result = $this->taskExec('ssh ' . $this->getServer() . ' -C "cd ' . $this->getProjectDir() . '; git pull"')
      ->run();

    if ($result->wasCancelled()) {
      $errors[] = [
        'msg' => 'Problem detected in remote server.',
      ];
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function deploy() {
    $errors = [];
    $result = $this->taskExec('ssh ' . $this->getServer() . ' -C "cd ' . $this->getProjectDir() . '; ./robo build --not-interactive"')
      ->run();

    if ($result->wasCancelled()) {
      $errors[] = [
        'msg' => 'Problem detected in remote server.',
      ];
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {}

}
