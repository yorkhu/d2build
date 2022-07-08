<?php

namespace d2build\Factory;

use d2build\Deploy\Source\DeploySourceRobo;
use d2build\Deploy\Source\DeploySourceRoboSync;

trait DeployFactory {

  protected function taskDeploy($remote, $options) {
    switch ($options['deploy']) {
      case 'robo':
        return $this->task(DeploySourceRobo::class, $remote, $options);
        break;

      case 'robo+sync':
        return $this->task(DeploySourceRoboSync::class, $remote, $options);
        break;
    }
  }

}
