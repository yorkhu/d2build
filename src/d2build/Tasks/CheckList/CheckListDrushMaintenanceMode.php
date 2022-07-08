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
 * Checklist drush maintinance mode.
 *
 * @package d2build\Tasks\CheckList
 */
class CheckListDrushMaintenanceMode extends BaseTask implements BuilderAwareInterface {
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
   * Check maintinance mode.
   */
  protected function checkMaintenanceMode() {
    $webdir = $this->webDir;
    $appDir = $this->config->get('settings.AppDir');
    $drupalRoot = $appDir . '/' . $webdir;

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

      // Get drupal maintenance mode:
      $cmd = $this->taskDrushStack()
        ->drupalRootDirectory($drupalRoot)
        ->silent(TRUE)
        ->printOutput(FALSE)
        ->printMetadata(FALSE)
        ->uri($uri)
        ->drush("sget system.maintenance_mode --format=php");

      $result = $this->runCmd($cmd);

      $this->exitCode = 1;
      $this->errorMessage = 'Maintenance mode information not found!';

      $message = $result->getMessage();
      if (!empty($message)) {
        // Unserialize php variable:
        $mmode = unserialize($message);
        if (!empty($mmode) && isset($mmode['system.maintenance_mode'])) {
          if ($mmode['system.maintenance_mode']) {
            $this->errorMessage = 'Maintenance mode enabled!';
          }
          else {
            $this->exitCode = 0;
            $this->errorMessage = '';

            $this->yell(' Maintenance mode is disabled. ', 0, 'green');
          }
        }
      }
    }

    // Set default logger:
    $this->setLogger($logger);
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->checkMaintenanceMode();

    return new Result(
      $this,
      $this->exitCode,
      $this->errorMessage,
      []
    );
  }

}
