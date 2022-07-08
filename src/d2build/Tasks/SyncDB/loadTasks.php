<?php

namespace d2build\Tasks\SyncDB;

trait loadTasks {

  protected function taskSyncDB() {
    return $this->task(SyncDB::class);
  }

}
