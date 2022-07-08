<?php

namespace d2build\Tasks\LocalConfig;

trait loadTasks {

  protected function taskLocalConfig($site = 'default') {
    return $this->task(LocalConfig::class, $site);
  }

}
