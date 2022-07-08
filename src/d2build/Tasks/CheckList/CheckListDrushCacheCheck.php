<?php

namespace d2build\Tasks\CheckList;

use Robo\Contract\BuilderAwareInterface;
use Robo\Robo;
use Robo\TaskAccessor;
use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Common\IO;
use Robo\Task\Base;
use Robo\Task\Docker;
use Boedah\Robo\Task\Drush;

/**
 * Checklist cache settings.
 *
 * @package d2build\Tasks\CheckList
 */
class CheckListDrushCacheCheck extends BaseTask implements BuilderAwareInterface {
  use TaskAccessor;
  use IO;
  use Base\Tasks;
  use Drush\loadTasks;
  use Docker\Tasks;

  /**
   * Set docker container name.
   *
   * @var array
   */
  protected $dockerContainers;

  /**
   * Website directory.
   *
   * @var string
   */
  protected $webDir;

  /**
   * Drush cmd.
   *
   * @var string
   */
  protected $drushCmd;

  /**
   * Exit code.
   *
   * @var int
   */
  protected $exitCode = 0;

  /**
   * Error message.
   *
   * @var string
   */
  protected $errorMessage = '';

  /**
   * Multisite list.
   *
   * @var int
   */
  protected $multiSites = 0;

  /**
   * Set docker container name.
   *
   * @param string $dockerContainers
   *   Docker containers.
   *
   * @return $this
   */
  public function setDockerContainers($dockerContainers) {
    $this->dockerContainers = $dockerContainers;

    return $this;
  }

  /**
   * Set website directory.
   *
   * @param string $webdir
   *   Website directory.
   *
   * @return $this
   */
  public function setWebsiteDirectory($webdir) {
    if (!empty($webdir)) {
      $webdir .= '/';
    }
    $this->webDir = $webdir;

    return $this;
  }

  /**
   * Set drush command.
   *
   * @param string $drushCmd
   *   Drush command.
   *
   * @return $this
   */
  public function setDrushCmd($drushCmd) {
    if (empty($drushCmd)) {
      $drushCmd = 'drush';
    }
    $this->drushCmd = $drushCmd;

    return $this;
  }

  /**
   * Set multisite.
   *
   * @param int $multisite
   *   1 - Is multi site.
   *
   * @return $this
   */
  public function setMultiSite($multisite) {
    $this->multiSites = $multisite;

    return $this;
  }

  /**
   * Run command docker or shell.
   *
   * @param $cmd
   *   Robo command.
   */
  protected function runCmd($cmd) {
    if (!empty($this->dockerContainers)) {
      return $this->taskDockerExec($this->dockerContainers['webContainerID'])
        ->silent(TRUE)
        ->printOutput(FALSE)
        ->printMetadata(FALSE)
        ->interactive()
        ->option('user', posix_getuid())
        ->exec($cmd)
        ->run();
    }
    else {
      return $cmd->run();
    }
  }

  /**
   * Check drupal cache settings.
   */
  protected function checkCache() {
    $webdir = $this->webDir;
    $appDir = $this->config->get('settings.AppDir');
    $drupalRoot = $appDir . '/' . $webdir;
    $errorMsg = [];

    // Get multisite info:
    $site_uris = [];
    if (!empty($this->multiSites)) {
      foreach ($this->multiSites as $uri => $dbs) {
        $site_uris[] = $uri;
      }
    }
    else {
      $site_uris[] = 'default';
    }

    // Disable logger:
    $logger = Robo::logger();
    $this->setLogger(new \Psr\Log\NullLogger());

    // Set base output:
    $color = 'cyan';
    $format = "<fg=white;bg=$color;options=bold>%s</fg=white;bg=$color;options=bold>";

    // Check config diff:
    foreach ($site_uris as $uri) {
      if ($this->multiSites) {
        $this->writeln(sprintf($format, " Site $uri "));
      }

      // Get drupal system cache settings:
      $cmd = $this->taskDrushStack()
        ->drupalRootDirectory($drupalRoot)
        ->silent(TRUE)
        ->printOutput(FALSE)
        ->printMetadata(FALSE)
        ->uri($uri)
        ->drush("cget system.performance --format=php");

      $result = $this->runCmd($cmd);

      $message = $result->getMessage();
      if (!empty($message)) {
        // Unserialize php variable:
        $cache = unserialize($message);

        if (!empty($cache) && (isset($cache['css'])) && (isset($cache['js']))) {
          if (isset($cache['css']['gzip']) && !$cache['css']['gzip']) {
            $this->exitCode = 1;
            $errorMsg[] = 'CSS gzip compression is disabled.';
          }
          if (isset($cache['css']['preprocess']) && !$cache['css']['preprocess']) {
            $this->exitCode = 1;
            $errorMsg[] = 'CSS aggregation is disabled.';
          }
          if (isset($cache['js']['gzip']) && !$cache['js']['gzip']) {
            $this->exitCode = 1;
            $errorMsg[] = 'JS gzip compress is disabled.';
          }
          if (isset($cache['js']['preprocess']) && !$cache['js']['preprocess']) {
            $this->exitCode = 1;
            $errorMsg[] = 'JS aggregation is disabled.';
          }
        }
      }

      // Get drupal advagg settings:
      $cmd = $this->taskDrushStack()
        ->drupalRootDirectory($drupalRoot)
        ->silent(TRUE)
        ->printOutput(FALSE)
        ->printMetadata(FALSE)
        ->uri($uri)
        ->drush("cget advagg.settings --format=php");

      $result = $this->runCmd($cmd);
      $message = $result->getMessage();
      if (!empty($message)) {
        // Unserialize php variable:
        $adv_cache = unserialize($message);
        if (!empty($adv_cache)) {
          if ($adv_cache['enabled']) {
            if ($adv_cache['cache_level'] < 2) {
              $this->exitCode = 1;
              $errorMsg[] = 'Cache level to low!';
            }
          }
          else {
            $this->exitCode = 1;
            $errorMsg[] = 'Advanced Aggregation is disabled.';
          }
        }
      }
      else {
        $errorMsg[] = 'Advanced Aggregation module is disabled.';
      }
    }

    if (!empty($errorMsg)) {
      $this->errorMessage = implode("\n", $errorMsg);
    }

    // Set default logger:
    $this->setLogger($logger);
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->checkCache();

    if (!$this->exitCode) {
      $this->yell('Cache settings: ok!', 0, 'green');
    }

    return new Result(
      $this,
      $this->exitCode,
      $this->errorMessage,
      []
    );
  }

}
