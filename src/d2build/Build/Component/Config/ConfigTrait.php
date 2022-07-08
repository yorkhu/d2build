<?php

namespace d2build\Build\Component\Config;

/**
 * @file
 * Config trait file.
 */

use Robo\Robo;

/**
 * Trait ConfigTrait.
 *
 * @package d2build\Build\Component\Config
 */
trait ConfigTrait {
  use \d2build\Build\Component\Project\ProjectTrait;

  /**
   * Config variable.
   *
   * @var \Robo\Config\Config
   */
  protected $config;

  /**
   * Load configuration file.
   */
  protected function loadConfiguration($base_dir = '') {
    if (empty($base_dir)) {
      $base_dir = $this->roboDir;
    }
    $config = Robo::createConfiguration([$base_dir . 'robo-config.yml']);
    $env = $this->getConfigEnv($base_dir);

    $config_file = 'robo-config.' . $env . '.yml';
    if (is_file($base_dir . $config_file)) {
      Robo::loadConfiguration([$base_dir . $config_file], $config);
    }

    $settings = $config->get('settings');
    $tokens = [
      '@project-name' => $this->getProjectName(),
      '@branch' => $this->getBranch(),
      '@env' => $this->getConfigEnv(),
    ];

    if (!empty($settings['DrupalExtra'])) {
      $drupal_extra = strtr($settings['DrupalExtra'], $tokens);
      $config->setDefault('settings.DrupalExtra', $drupal_extra);
    }

    if (!isset($settings['SyncDefaultSource'])) {
      $config->setDefault('settings.SyncDefaultSource', 'staging');
    }

    if (isset($settings['SyncServer']) && !isset($settings['SyncOptions'])) {
      $default_remote = $config->get('settings.SyncDefaultSource');

      $connections = $syncOptions = [];
      $con_keys = ['server', 'user', 'port', 'HomeDir', 'WebDir', 'EnableDocker'];
      foreach ($settings['SyncServer'] as $key => $value) {
        if (in_array($key, $con_keys)) {
          $connections[$default_remote][$key] = $value;
        }
        else {
          $syncOptions[$default_remote][$key] = $value;
        }
      }

      $config->setDefault('settings.connections', $connections);
      $config->setDefault('settings.SyncOptions', $syncOptions);
      $settings = $config->get('settings');
    }

    if (isset($settings['connections'])) {
      $connections = $settings['connections'];
      $reload_config = FALSE;
      foreach ($connections as $key => $con) {
        // Set default application dir.
        if (!isset($con['AppDir'])) {
          $config->setDefault('settings.connections.' . $key . '.AppDir', '/var/www');
        }

        if (isset($con['HomeDir'])) {
          $remoteHome = strtr($con['HomeDir'], $tokens);
          $config->setDefault('settings.connections.' . $key . '.HomeDir', $remoteHome);
        }

        if (isset($con['WebDir'])) {
          $remoteWeb = strtr($con['WebDir'], $tokens);
          $config->setDefault('settings.connections.' . $key . '.WebDir', $remoteWeb);
        }

        if (!isset($con['EnableDocker'])) {
          $config->setDefault('settings.connections.' . $key . '.EnableDocker', $config->get('settings.EnableDocker'));
        }

        if (!isset($con['ctype'])) {
          $config->setDefault('settings.connections.' . $key . '.ctype', 'ssh');
        }
        if (!isset($settings['SyncOptions'][$key])) {
          $config->setDefault('settings.SyncOptions.' . $key, []);
          $reload_config = TRUE;
        }
      }
      if ($reload_config) {
        $settings = $config->get('settings');
      }
    }

    if (isset($settings['SyncOptions'])) {
      $options = $settings['SyncOptions'];
      if (empty($options) || !isset($options['local'])) {
        $options['local'] = [];
        $config->setDefault('settings.SyncOptions', $options);
      }
      foreach ($options as $key => $option) {
        if (!isset($option['remoteFilesDir'])) {
          $config->setDefault('settings.SyncOptions.' . $key . '.remoteFilesDir', 'sites/default/files');
        }

        if (!isset($option['localFilesDir'])) {
          $config->setDefault('settings.SyncOptions.' . $key . '.localFilesDir', 'sites/default/files');
        }

        if (!isset($option['adminUser'])) {
          $config->setDefault('settings.SyncOptions.' . $key . '.adminUser', 'admin');
        }

        if (!isset($option['sanitize'])) {
          $config->setDefault('settings.SyncOptions.' . $key . '.sanitize', 1);
        }

        if (!isset($option['resetPassword'])) {
          $config->setDefault('settings.SyncOptions.' . $key . '.resetPassword', 1);
        }
      }
    }

    // Set default application dir.
    if (!isset($settings['AppDir']) || empty($settings['AppDir'])) {
      $config->setDefault('settings.AppDir', '/var/www');
    }

    // Set default drupal version.
    if (!isset($settings['Drupal']) || empty($settings['Drupal'])) {
      $config->setDefault('Drupal', '8');
    }

    // Set default composer command.
    if (!isset($settings['ComposerCmd']) || empty($settings['ComposerCmd'])) {
      $config->setDefault('settings.ComposerCmd', '/usr/local/bin/composer');
    }

    // Set default npm command.
    if (!isset($settings['NpmCmd']) || empty($settings['NpmCmd'])) {
      $config->setDefault('settings.NpmCmd', 'npm');
    }

    // Set default drush command.
    if (!isset($settings['DrushCmd']) || empty($settings['DrushCmd'])) {
      $config->setDefault('settings.DrushCmd', 'drush');
    }

    // Set default theme build options.
    if (!isset($settings['ThemeBuild'])) {
      $config->setDefault('settings.ThemeBuild', 1);
    }

    // Set default theme task.
    if (!isset($settings['ThemeTask']) || empty($settings['ThemeTask'])) {
      $config->setDefault('settings.ThemeTask', 'sass-dev');
    }

    // Set default theme task.
    if (!isset($settings['MultiSite']) || empty($settings['MultiSite'])) {
      $config->setDefault('settings.MultiSite', '');
    }

    // Set default drupal extra directory:
    if (!isset($settings['DrupalExtra']) || empty($settings['DrupalExtra'])) {
      $config->setDefault('settings.DrupalExtra', 'drupal_extra');
    }

    if (isset($settings['EnableDocker']) && $settings['EnableDocker']) {
      // Set default docker web container.
      if (!isset($settings['docker']['webContainer']) || empty($settings['docker']['webContainer'])) {
        $config->setDefault('settings.docker.webContainer', 'phpfpm');
      }

      // Set default docker theme container.
      if (!isset($settings['docker']['themeContainer']) || empty($settings['docker']['themeContainer'])) {
        $config->setDefault('settings.docker.themeContainer', 'phpfpm');
      }
    }

    return $config;
  }

}
