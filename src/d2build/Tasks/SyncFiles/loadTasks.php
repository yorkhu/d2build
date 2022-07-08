<?php

namespace d2build\Tasks\SyncFiles;

trait loadTasks {

  protected function taskSyncFiles() {
    return $this->task(SyncFiles::class);
  }

}
