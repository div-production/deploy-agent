<?php
/**
 * Created by PhpStorm.
 * User: Vladimir
 * Date: 16.11.17
 */

namespace div\DeployAgent;

use Cocur\BackgroundProcess\BackgroundProcess;
use div\DeployAgent\helpers\GitHelper;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    protected $config;

    public function readConfig()
    {
        if ($this->config) {
            return $this->config;
        }

        $file = getcwd() . DIRECTORY_SEPARATOR . 'deploy.json';

        if (!file_exists($file)) {
            throw new \Exception('Не найден файл конфигурации');
        }

        $json = file_get_contents($file);
        $config = @json_decode($json, true);

        if ($config === null) {
            throw new \Exception('Файл deploy.json имеет некорректный формат');
        }

        $this->config = $config;

        return $config;
    }

    public function getUserHome()
    {
        $home = getenv('HOME');
        if ($home) {
            return $home;
        }

        $line = shell_exec('ls -l | grep deploy.json');
        $cols = explode(' ', $line);
        $cols = array_diff($cols, ['']);
        $cols = array_values($cols);

        $owner = $cols[2];

        $home = shell_exec("echo ~$owner");

        if (strpos($home, '~') === 0) {
            echo 'error';
            throw new \Exception('Не удалось определить домашнюю директорию пользователя');
        } else {
            return trim($home);
        }
    }

    public function getPid()
    {
        $pidFile = $this->getPidFile();

        if (!file_exists($pidFile)) {
            return null;
        }

        $pid = file_get_contents($pidFile);

        /** @var BackgroundProcess $process */
        $process = BackgroundProcess::createFromPID($pid);

        if ($process->isRunning()) {
            return $pid;
        } else {
            return null;
        }
    }

    public function savePid($pid)
    {
        $storage = $this->getProjectStorage();

        if (!is_dir($storage)) {
            mkdir($storage, 0755, true);
        }

        $pidFile = $this->getPidFile();

        file_put_contents($pidFile, $pid);
    }

    public function removePid($pid)
    {
        $pidFile = $this->getPidFile();
        $existingPid = file_get_contents($pidFile);

        if ($existingPid == $pid) {
            unlink($pidFile);
        }
    }

    public function getExecutable($includePhp = true)
    {
        $self = $_SERVER['PHP_SELF'];

        if (strpos($self, DIRECTORY_SEPARATOR) !== 0) {
            $self = getcwd() . DIRECTORY_SEPARATOR . $self;
        }

        return $includePhp ? PHP_BINARY . ' ' . $self : $self;
    }

    public function getProjectStorage()
    {
        $config = $this->readConfig();

        if (empty($config['remote'])) {
            throw new \Exception('В файле конфигурации отсутствует параметр remote');
        }
        if (empty($config['branch'])) {
            throw new \Exception('В файле конфигурации отсутствует параметр branch');
        }

        $git = new GitHelper();

        $owner = $git->getOwnerName($config['remote']);
        $name = $git->getRepositoryName($config['remote']);

        $ds = DIRECTORY_SEPARATOR;

        $home = $this->getUserHome();

        shell_exec("echo $home > test.log");

        return "{$home}{$ds}.deploy{$ds}projects{$ds}{$owner}__{$name}__{$config['branch']}";
    }

    protected function getPidFile()
    {
        return $this->getProjectStorage() . DIRECTORY_SEPARATOR . 'deploy.pid';
    }
}
