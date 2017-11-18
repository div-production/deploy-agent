<?php

namespace div\DeployAgent\helpers;

/**
 * Created by PhpStorm.
 * User: Vladimir
 * Date: 17.11.17
 */
class GitHelper
{
    protected $root;

    protected $git;

    public function __construct($root = null, $git = 'git')
    {
        if (is_null($root)) {
            $this->root = getcwd();
        }

        $this->git = $git;
    }

    public function getRepositoryName($remote = 'origin')
    {
        $url = $this->getRemoteUrl($remote);

        return basename($url, '.git');
    }

    public function getOwnerName($remote = 'origin')
    {
        $url = $this->getRemoteUrl($remote);

        $path = parse_url($url, PHP_URL_PATH);
        $path = preg_replace('/.+\:/', '', $path);

        return basename(dirname($path));
    }

    public function getRemoteUrl($remote)
    {
        return $this->exec("config --get remote.$remote.url");
    }

    public function exec($command)
    {
        return trim(shell_exec("cd $this->root && $this->git $command"));
    }
}
