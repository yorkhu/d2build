<?php

namespace d2build\Build\Component\Docker;

/**
 * @file
 * Docker trait file.
 */

const DOCKER_DEFAULT_WEB_PORT = '9481';

/**
 * Trait DockerTrait.
 *
 * @package d2build\Build\Component\Docker
 */
trait DockerTrait {

  use \Boedah\Robo\Task\Drush\loadTasks;
  use \Droath\RoboDockerCompose\Task\loadTasks;
  use \Droath\RoboDockerSync\Task\loadTasks;

  /**
   * Env variable.
   *
   * @var array
   */
  protected $envExtra = [];

  /**
   * Build and start docker containers.
   *
   * @aliases up start
   */
  public function dockerUp() {
    if (is_file('docker-sync.yml')) {
      // Run docker-sync in background.
      $this->taskDockerSyncStart()
        ->run();
    }

    $this->taskDockerComposeUp()
      ->detachedMode()
      ->env($this->getEnv())
      ->run();
  }

  /**
   * Stop docker containers.
   *
   * @aliases down stop
   */
  public function dockerDown() {
    $this->taskDockerComposeDown()
      ->env($this->getEnv())
      ->run();

    if (is_file('docker-sync.yml')) {
      // Stop docker-sync from running.
      $this->taskDockerSyncStop()
        ->run();
    }
  }

  /**
   * Restart docker containers.
   *
   * @aliases restart
   */
  public function dockerRestart() {
    $this->dockerDown();
    $this->dockerUp();
  }

  /**
   * Destroy all docker containers.
   *
   * @aliases destroy
   */
  public function dockerDestroy() {
    if ($this->isLocked()) {
      $this->say('The build system is currently locked. Syncing and reinitializing functions are disabled. If you would like to run them delete the .robo-locked file.');
      return;
    }

    // Stop docker instances.
    $this->dockerDown();

    // Destroy docker.
    $this->taskDockerComposeDown()
      ->rmi('local')
      ->volumes()
      ->env($this->getEnv())
      ->run();
  }

  /**
   * Docker shell.
   *
   * @param string $container
   *   Docker container.
   *
   * @aliases shell sh
   */
  public function dockerShell($container = NULL) {
    if (empty($container)) {
      $container = $this->getDockerWebContainerName();
    }
    if (!empty($container)) {
      $this->taskDockerExec($this->getDockerContainerID($container))
        ->interactive()
        ->option('-t')
        ->option('user', posix_getuid())
        ->exec('/bin/bash')
        ->env($this->getEnv())
        ->run();
    }
  }

  /**
   * Docker root shell.
   *
   * @param string $container
   *   Docker container.
   *
   * @aliases rootshell rsh rs su
   */
  public function dockerRootShell($container = NULL) {
    if (empty($container)) {
      $container = $this->getDockerWebContainerName();
    }
    if (!empty($container)) {
      $this->taskDockerExec($this->getDockerContainerID($container))
        ->interactive()
        ->option('-t')
        ->exec('/bin/bash')
        ->env($this->getEnv())
        ->run();
    }
  }

  /**
   * Docker drush.
   *
   * @param array $cmd
   *   Docker commands.
   *
   * @param array $opts
   *   Restrict the output to configuration values for a specific section.
   *
   * @option $not-interactive
   *   Disable shell interactive mode.
   *
   * @aliases drush dr
   */
  public function dockerDrush(array $cmd, array $opts = ['not-interactive' => FALSE]) {
    $dockerConf = $this->config->get('settings.docker');
    if (!empty($dockerConf)) {
      $webdir = $this->config->get('settings.WebDir');
      $drushCmd = $this->config->get('settings.DrushCmd');
      $appDir = $this->config->get('settings.AppDir');
      $drupalRoot = $appDir . '/' . $webdir;

      $cmd = implode(' ', $cmd);

      $this->taskDockerExec($this->getDockerContainerID($this->getDockerWebContainerName()))
        ->interactive(!$opts['not-interactive'])
        ->option('-t')
        ->option('user', posix_getuid())
        ->exec($drushCmd . ' ' . $cmd . ' -y -r ' . $drupalRoot)
        ->env($this->getEnv())
        ->run();
    }
  }

  /**
   * Docker is run.
   */
  protected function dockerIsRun() {
    $dockerConf = $this->config->get('settings.docker');
    $docker_run = shell_exec('docker-compose ps|grep Up|grep ' . $dockerConf['webContainer']);
    if (!empty($docker_run)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Update docker containers.
   *
   * @param string $container
   *   Docker container.
   *
   * @aliases drefresh
   */
  public function dockerRefresh($container = NULL) {
    $this->dockerDown();
    if (!empty($container)) {
      $this->taskExec('docker-compose pull ' . $container)
        ->env($this->getEnv())
        ->run();
    }
    else {
      $this->taskExec('docker-compose pull')
        ->env($this->getEnv())
        ->run();
    }
    $this->dockerUp();
  }

  /**
   * Docker is enable check.
   */
  protected function dockerEnable($noDocker = NULL) {
    if (NULL === $noDocker) {
      $enableDocker = $this->config->get('settings.EnableDocker');
      return (bool) $enableDocker;
    }
    else {
      return (bool) !$noDocker;
    }
  }

  /**
   * Return the docker web container name.
   *
   * @return string
   *   Container name.
   */
  protected function getDockerWebContainerName() {
    $dockerConf = $this->config->get('settings.docker');
    if (is_file('docker-sync.yml')) {
      return $dockerConf['webContainer'] . 'sh';
    }

    return $dockerConf['webContainer'];
  }

  /**
   * Return the docker theme container name.
   *
   * @return string
   *   Container name.
   */
  protected function getDockerThemeContainerName() {
    $dockerConf = $this->config->get('settings.docker');
    return $dockerConf['themeContainer'];
  }

  /**
   * Return the docker container name.
   *
   * @param string $service
   *   Service name.
   *
   * @return string
   *   Container name.
   */
  protected function getDockerContainerID($service) {
    return exec(sprintf(
      'docker-compose ps -q %s',
      escapeshellarg($service)
    ));
  }

  /**
   * Copies the default docker config files, if not already present.
   */
  protected function copyDockerFiles($base_dir = '') {
    if (empty($base_dir)) {
      $base_dir = $this->roboDir;
    }

    if (!file_exists($base_dir . 'docker-compose.yml') && file_exists($base_dir . 'docker-compose.yml.dist')) {
      $this->taskFilesystemStack()
        ->copy($base_dir . 'docker-compose.yml.dist', $base_dir . 'docker-compose.yml')
        ->run();
      $this->assignPort();
    }
    if (!file_exists($base_dir . 'docker/env/user.env') && file_exists($base_dir . 'docker/env/user.env.dist')) {
      $this->taskFilesystemStack()
        ->copy($base_dir . 'docker/env/user.env.dist', $base_dir . 'docker/env/user.env')
        ->run();
    }

    if (file_exists($base_dir . 'docker-compose.yml')) {
      $uid = posix_getuid();
      $os = '';
      if ($uid < 1000) {
        $os = '-dev-macos';
      }
      $this->taskReplaceInFile($base_dir . 'docker-compose.yml')
        ->from('-[os]')
        ->to($os)
        ->run();
    }

  }

  /**
   * Assigns a unique port, specific to this project, if possible.
   */
  protected function assignPort($base_dir = '') {
    if (empty($base_dir)) {
      $base_dir = $this->roboDir;
    }

    if (file_exists($base_dir . 'docker-compose.yml')) {
      // Generate a random 8xxx port, based on the project name checksum.
      // This will use a pseudo-unique port for every project.
      $newPort = 8000 + (abs(crc32($this->getProjectName())) % 1000);
      // Checks if the port is in use.
      $response = $this->checkOpenPort($newPort);
      // If the port is not in use, replace the docker-compose file with the new port.
      // Otherwise, fallback to the default port in the dist file.
      if (!$response) {
        $this->taskReplaceInFile($base_dir . 'docker-compose.yml')
          ->from('127.0.0.1:' . DOCKER_DEFAULT_WEB_PORT . ':80')
          ->to("127.0.0.1:$newPort:80")
          ->run();
        $this->say('Using port ' . $newPort);
      }
      else {
        $this->say('Using default port ' . DOCKER_DEFAULT_WEB_PORT);
      }
    }
  }

  /**
   * Opens a socket on localhost to check if the given port is in use or not.
   */
  protected function checkOpenPort($port) {
    $connection = shell_exec("php -r '\$c = @fsockopen(\"localhost\", $port); if (is_resource(\$c)) { echo(1); fclose(\$c); exit(0); } else { echo(0); exit(1); }'");
    if ($connection) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Show docker containter logs.
   *
   * @aliases log
   */
  public function dockerLog($container = NULL) {
    if (empty($container)) {
      $dockerConf = $this->config->get('settings.docker');
      $container = $dockerConf['webContainer'];
    }
    if (!empty($container)) {
      $this->taskExec('docker-compose logs --tail 100 --follow ' . $container)
        ->env($this->getEnv())
        ->run();
    }
  }

  /**
   * Get environment variables.
   */
  protected function getEnv() {
    return $this->envExtra;
  }

}
