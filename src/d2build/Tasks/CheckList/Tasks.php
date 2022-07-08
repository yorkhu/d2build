<?php

namespace d2build\Tasks\CheckList;

trait Tasks {

  protected function taskCheckListDiff() {
    return $this->task(CheckListDiff::class);
  }

  protected function taskCheckListDrushConfig() {
    return $this->task(CheckListDrushConfig::class);
  }

  protected function taskCheckListDrushUpDb() {
    return $this->task(CheckListDrushUpDb::class);
  }

  protected function taskCheckListRobotsTxt() {
    return $this->task(CheckListRobotsTxt::class);
  }

  protected function taskCheckListDrushStatus() {
    return $this->task(CheckListDrushStatus::class);
  }

  protected function taskCheckListDrushMaintenanceMode() {
    return $this->task(CheckListDrushMaintenanceMode::class);
  }

  protected function taskCheckListDrushCacheCheck() {
    return $this->task(CheckListDrushCacheCheck::class);
  }

  protected function taskCheckListRunRemote() {
    return $this->task(CheckListRunRemote::class);
  }

}
