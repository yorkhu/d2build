<?php

namespace d2build\Deploy\Source;

use Robo\Task\Remote;

/**
 * Class DeploySourceRoboSync.
 *
 * @package d2build\Deploy\Source
 */
class DeploySourceRoboSync extends DeploySourceRobo {
  use Remote\Tasks;

  /**
   * {@inheritdoc}
   */
  public function prepareDeploy() {
    $errors = [];

    $this->taskExec('./robo build --not-interactive')
      ->run();

    $continue = $this->confirm('Do you want to continue?', TRUE);
    if ($continue) {
      $errors = parent::prepareDeploy();
    }
    else {
      $errors[] = [
        'exit' => TRUE,
      ];
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function deploy() {
    $errors = parent::deploy();

    foreach ($this->options['sync_dirs'] as $sync_dir) {
      $result = $this->taskRsync()
        ->fromPath($sync_dir)
        ->toHost($this->remote['server'])
        ->toUser($this->remote['user'])
        ->toPath($this->remote['HomeDir'] . '/' . $sync_dir)
        ->recursive()
        ->excludeVcs()
        ->checksum()
        ->wholeFile()
        ->verbose()
        ->progress()
        ->humanReadable()
        ->stats()
        ->run();

      if ($result->wasCancelled()) {
        $errors[] = [
          'msg' => 'Sync error: ' . $sync_dir,
        ];
      }
    }

    return $errors;
  }

}
