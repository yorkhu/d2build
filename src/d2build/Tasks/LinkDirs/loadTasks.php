<?php

namespace d2build\Tasks\LinkDirs;

trait loadTasks {

  protected function taskLinkDirs($source) {
    return $this->task(LinkDirs::class, $source);
  }

}
