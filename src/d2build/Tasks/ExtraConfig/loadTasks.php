<?php

namespace d2build\Tasks\ExtraConfig;

trait loadTasks {

  protected function taskExtraConfig($site = 'default') {
    return $this->task(ExtraConfig::class, $site);
  }

}
