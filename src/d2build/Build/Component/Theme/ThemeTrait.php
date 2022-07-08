<?php

namespace d2build\Build\Component\Theme;

/**
 * @file
 * Theme trait file.
 */

/**
 * Trait loadComponents.
 *
 * @package d2build\Build\Component\Theme
 */
trait ThemeTrait {
  use \d2build\Build\Component\RebuildableCondition\RebuildableConditionTrait;

  /**
   * Install frontend component in all theme.
   *
   * @param array $opts
   *   Restrict the output to configuration values for a specific section.
   *
   * @option $no-docker
   *   Command run in the shell.
   *
   * @option $not-interactive
   *   Disable shell interactive mode.
   *
   * @option $force
   *   Run all build components.
   */
  public function installThemeComponent(array $opts = ['no-docker' => FALSE, 'not-interactive' => FALSE, 'force' => FALSE]) {
    $theme_build = $this->config->get('settings.ThemeBuild');
    if (!$theme_build) {
      $this->say('Disabled theme building!');
      return;
    }

    $npmCmd = $this->config->get('settings.NpmCmd');
    $sources = $this->getThemeSources();

    foreach ($sources as $source) {
      if (is_dir($source)) {
        $themeDirs = $this->getThemeDirs($source, 0, $opts);
        foreach ($themeDirs as $dir => $file) {
          $this->execInstallThemeComponent($dir, $file, $npmCmd, $opts);
        }
      }
    }

    # Build colonel contrib theme.
    $webDir = $this->config->get('settings.WebDir');
    $source = $webDir . '/themes/contrib/colonel';

    if (is_dir($source) && (!is_dir($source . '/node_modules') || $opts['force'])) {
      if (is_file($source . '/package.json')) {
        $this->execInstallThemeComponent($source, 'colonel', $npmCmd, $opts);
      }
    }
  }

  /**
   * The theme packages is changed.
   *
   * @param string $source
   *   Base directory.
   * @param string $file
   *   Recursive level.
   * @param array $opts
   *   Restrict the output to configuration values for a specific section.
   *
   * @option $no-docker
   *   Command run in the shell.
   *
   * @option $not-interactive
   *   Disable shell interactive mode.
   *
   * @option $force
   *   Run all build components.
   *
   * @return boolean
   *   Changed theme packages.
   */
  protected function isChanged(string $source, string $file, array $opts = ['no-docker' => FALSE, 'not-interactive' => FALSE, 'force' => FALSE]) {
    $change = $this->rebuildableCondition();
    $change_index = str_replace('/', '-', $source);

    if (empty($change) || !isset($change[$change_index . '-' . $file]) || $change[$change_index . '-' . $file] || $opts['force']) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Exec theme packages installer.
   *
   * @param string $path
   *   Theme path.
   * @param string $name
   *   Theme name.
   * @param string $npmCmd
   *   Npm command.
   * @param array $opts
   *   Exec command options.
   */
  protected function execInstallThemeComponent(string $path, string $name, string $npmCmd, array $opts) {
    $this->say('Install ' . $name . ' themes component.');
    $cmd = $this->taskExec('sh -c "cd ' . $path . ' ; ' . $npmCmd . ' install"');
    $this->cmdThemeExec($cmd, $opts['no-docker'], !$opts['not-interactive']);
  }

  /**
   * Compile CSS in all theme.
   *
   * @param array $opts
   *   Restrict the output to configuration values for a specific section.
   *
   * @option $no-docker
   *   Command run in the shell.
   *
   * @option $not-interactive
   *   Disable shell interactive mode.
   *
   * @option $force
   *   Run all build components.
   */
  public function compileCSS(array $opts = ['no-docker' => FALSE, 'not-interactive' => FALSE, 'force' => FALSE]) {
    $theme_build = $this->config->get('settings.ThemeBuild');
    if (!$theme_build) {
      $this->say('Disabled theme building!');
      return;
    }
    $sources = $this->getThemeSources();

    $npmCmd = $this->config->get('settings.NpmCmd');
    $themeTask = $this->config->get('settings.ThemeTask');
    foreach ($sources as $source) {
      if (is_dir($source)) {
        $themeDirs = $this->getThemeDirs($source, 0, $opts, TRUE);
        foreach ($themeDirs as $dir => $file) {
          $this->execCssCompile($dir, $file, $npmCmd, $themeTask, $opts);
        }
      }
    }

  }

  /**
   * Exec theme CSS compile.
   *
   * @param string $path
   *   Theme path.
   * @param string $name
   *   Theme name.
   * @param string $npmCmd
   *   Npm command.
   * @param string $themeTask
   *   Theme task name.
   * @param array $opts
   *   Exec command options.
   */
  protected function execCssCompile(string $path, string $name, string $npmCmd, string $themeTask, array $opts) {
    $this->say('Create CSS ' . $name . ' file.');

    if (is_file($path . '/gulpfile.js')) {
      $cmd = $this->taskExec('/bin/bash -c "cd ' . $path . ' ; \$(' . $npmCmd . ' bin)/gulp ' . $themeTask . '"');
    }
    elseif (is_file($path . '/postcss.config.js')) {
      $cmd = $this->taskExec('/bin/bash -c "cd ' . $path . ' ; ' . $npmCmd . ' run ' . $themeTask . '"');
    }

    $this->cmdThemeExec($cmd, $opts['no-docker'], !$opts['not-interactive']);
  }

  /**
   * Watch theme files.
   *
   * @param string $theme
   *   Theme name.
   * @param array $opts
   *   Restrict the output to configuration values for a specific section.
   *
   * @option $no-docker
   *   Command run in the shell.
   *
   * @option $not-interactive
   *   Disable shell interactive mode.
   *
   * @option $profile
   *   Install profile name.
   *
   * @aliases watch w
   */
  public function watchCSS($theme, array $opts = ['no-docker' => FALSE, 'not-interactive' => FALSE, 'profile' => '', 'force' => TRUE]) {
    if (empty($theme)) {
      return;
    }

    $sources = $this->getThemeSources();

    $themeDir = '';
    foreach ($sources as $source) {
      if (is_dir($source)) {
        $themeDirs = $this->getThemeDirs($source, 0, $opts);
        foreach ($themeDirs as $dir => $name) {
          if ($name === $theme) {
            $themeDir = $dir;
            break;
          }
        }
      }

      if (!empty($themeDir)) {
        break;
      }
    }

    if (empty($themeDir)) {
      return;
    }

    $npmCmd = $this->config->get('settings.NpmCmd');

    $this->say('Watch ' . $theme . ' theme file.');

    if (is_file($themeDir . '/gulpfile.js')) {
      $cmd = $this->taskExec('/bin/bash -c "cd ' . $themeDir . ' ; \$(' . $npmCmd . ' bin)/gulp watch-docker"');
    }
    elseif (is_file($themeDir . '/postcss.config.js')) {
      $cmd = $this->taskExec('/bin/bash -c "cd ' . $themeDir . ' ; ' . $npmCmd . ' run watch"');
    }

    $this->cmdThemeExec($cmd, $opts['no-docker'], !$opts['not-interactive']);
  }

  /**
   * Compass build CSS files.
   *
   * @param array $opts
   *   Restrict the output to configuration values for a specific section.
   *
   * @option $no-docker
   *   Command run in the shell.
   *
   * @option $not-interactive
   *   Disable shell interactive mode.
   *
   * @aliases compass
   */
  public function compassBuild(array $opts = ['no-docker' => FALSE, 'not-interactive' => FALSE]) {
    $source = 'themes';
    if (is_dir($source)) {
      $files = scandir($source);

      foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
          if (is_dir($source . '/' . $file) && is_file($source . '/' . $file . '/config.rb')) {
            $this->say('Create CSS ' . $file . ' file.');
            $cmd = $this->taskExec('/bin/bash -c "cd ' . $source . '/' . $file . ' ; compass compile"');
            $this->cmdThemeExec($cmd, $opts['no-docker'], !$opts['not-interactive']);
          }
        }
      }
    }
  }

}
