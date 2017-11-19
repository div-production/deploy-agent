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
    /**
     * @var GitHelper
     */
    protected $git;

    public function __construct()
    {
        $this->git = new GitHelper();

        parent::__construct();
    }

    public function readConfig()
    {
        $file = getcwd() . DIRECTORY_SEPARATOR . 'deploy.json';

        if (!file_exists($file)) {
            throw new \Exception('Не найден файл конфигурации');
        }

        $json = file_get_contents($file);
        $config = @json_decode($json, true);

        if ($config === null) {
            throw new \Exception('Файл deploy.json имеет некорректный формат');
        }

        return $config;
    }

    public function getPid($remote, $branch)
    {
        $storage = $this->getProjectStorage($remote, $branch);

        $pidFile = $this->getPidFile($remote, $branch);

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

    public function savePid($pid, $remote, $branch)
    {
        $storage = $this->getProjectStorage($remote, $branch);

        if (!is_dir($storage)) {
            mkdir($storage, 0755, true);
        }

        $pidFile = $this->getPidFile($remote, $branch);

        file_put_contents($pidFile, $pid);
    }

    public function removePid($pid, $remote, $branch)
    {
        $pidFile = $this->getPidFile($remote, $branch);
        $existingPid = file_get_contents($pidFile);

        if ($existingPid == $pid) {
            unlink($pidFile);
        }
    }

    public function getExecutablePath()
    {
        $self = $_SERVER['PHP_SELF'];

        if (strpos($self, DIRECTORY_SEPARATOR) === 0) {
            return $self;
        } else {
            return getcwd() . DIRECTORY_SEPARATOR . $self;
        }
    }

    protected function getProjectStorage($remote, $branch)
    {
        $owner = $this->git->getOwnerName($remote);
        $name = $this->git->getRepositoryName($remote);

        $ds = DIRECTORY_SEPARATOR;

        $home = getenv('HOME');

        return "{$home}{$ds}.deploy{$ds}projects{$ds}{$owner}__{$name}__{$branch}";
    }

    protected function getPidFile($remote, $branch)
    {
        return $this->getProjectStorage($remote, $branch) . DIRECTORY_SEPARATOR . 'deploy.pid';
    }
}
