<?php

namespace d2build\Build\Component\Lock;

/**
 * @file
 * Lock trait file.
 */

/**
 * Trait LockTrait.
 *
 * @package d2build\Build\Component\Lock
 */
trait LockTrait {

  /**
   * Create lock file.
   */
  public function lock() {
    if (!is_file('.robo-locked')) {
      $lock_file = fopen(".robo-locked", "w") or die("Unable to open file!");
      fwrite($lock_file, date('Y-m-d H:i'));
      fclose($lock_file);
    }
  }

  /**
   * Remove lock file.
   */
  public function unlock() {
    if (is_file('.robo-locked')) {
      unlink('.robo-locked');
    }
  }

  /**
   * Opens a socket on localhost to check if the given port is in use or not.
   */
  protected function isLocked() {
    if (is_file('.robo-locked')) {
      return TRUE;
    }

    return FALSE;
  }

}
