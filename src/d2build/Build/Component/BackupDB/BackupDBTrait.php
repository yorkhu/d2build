<?php

namespace d2build\Build\Component\BackupDB;

/**
 * @file
 * Sync trait file.
 */

/**
 * Trait BackupDBTrait.
 *
 * @package d2build\Build\Component\BackupDB
 */
trait BackupDBTrait {

  /**
   * Backup database to file.
   *
   * @aliases bdb
   */
  public function backupDB() {
    $webdir = $this->config->get('settings.WebDir');
    $drushCmd = $this->config->get('settings.DrushCmd');
    $appDir = $this->config->get('settings.AppDir');

    $this->say('Create sql backup:');
    $drupalRoot = $appDir . '/' . $webdir;
    $file_name = 'dump-' . date('Y-m-d.H.i') . '.sql.gz';
    $this->cmdExec('bash -c \''. $drushCmd . ' sql-dump -r ' . $drupalRoot . ' | gzip > ' . $file_name . '\'');
    $this->say($file_name);
  }

  /**
   * Restore database.
   *
   * @aliases rdb
   */
  public function restoreDB($file_name) {
    $webdir = $this->config->get('settings.WebDir');
    $drushCmd = $this->config->get('settings.DrushCmd');
    $appDir = $this->config->get('settings.AppDir');
    $drupalRoot = $appDir . '/' . $webdir;

    if (is_file($file_name)) {
      $this->say('Restore database:');
      $this->cmdExec('bash -c \'gunzip -c ' . $file_name . ' | '. $drushCmd . ' sqlc -r ' . $drupalRoot . '\'');
      $this->say($file_name . '.');
    }
    else {
      $this->say('File not found.');
    }
  }

}
