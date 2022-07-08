<?php

namespace d2build\Build\Component\Link;

/**
 * @file
 * Link trait file.
 */

/**
 * Trait LinkTrait.
 *
 * @package d2build\Build\Component\Link
 */
trait LinkTrait {
  use \d2build\Tasks\LinkDirs\loadTasks;

  /**
   * Copy extra files to drupal webroot.
   */
  protected function copyDrupalExtras() {
    $drupal_extra = $this->config->get('settings.DrupalExtra');
    if (is_dir($drupal_extra)) {
      $this->say('Copy extra files (' . $drupal_extra . ') to drupal webroot:');
      $webdir = $this->config->get('settings.WebDir');
      $this->taskExec('cp')->rawArg('-rf '. $drupal_extra . '/. ' . $webdir . '/')
        ->run();
    }
    else {
      $this->say('Drupal extra not found: ' . $drupal_extra);

    }
  }

  /**
   * Linked profile directory.
   */
  protected function linkProfiles() {
    $this->say('Linking profiles:');
    if (file_exists('profiles')) {
      $webdir = $this->config->get('settings.WebDir');
      $this->taskLinkDirs('profiles')
        ->setDestinationDirectory($webdir)
        ->run();
    }
  }

  /**
   * Linked module directory.
   */
  protected function linkModules() {
    $this->say('Linking modules:');
    if (file_exists('modules')) {
      $webdir = $this->config->get('settings.WebDir');
      $drupal = $this->config->get('settings.Drupal');
      if ('7' == $drupal) {
        $webdir .= '/sites/all';
      }
      $this->taskLinkDirs('modules')
        ->setDrupalVersion($drupal)
        ->setDestinationDirectory($webdir)
        ->run();
    }
  }

  /**
   * Linked theme directory.
   */
  protected function linkThemes() {
    $this->say('Linking themes:');
    if (file_exists('themes')) {
      $webdir = $this->config->get('settings.WebDir');
      $drupal = $this->config->get('settings.Drupal');
      if ('7' == $drupal) {
        $webdir .= '/sites/all';
      }
      $this->taskLinkDirs('themes')
        ->setDrupalVersion($drupal)
        ->setDestinationDirectory($webdir)
        ->run();
    }
  }

  /**
   * Links libraries directory.
   */
  protected function linkLibraries() {
    $this->say('Linking libraries:');
    if (file_exists('libraries')) {
      $webdir = $this->config->get('settings.WebDir');
      $drupal = $this->config->get('settings.Drupal');
      if ('7' == $drupal) {
        $webdir .= '/sites/all';
      }
      $this->taskLinkDirs('libraries')
        ->setDrupalVersion($drupal)
        ->setDestinationDirectory($webdir)
        ->run();
    }
  }

}
