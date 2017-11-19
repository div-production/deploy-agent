<?php
/**
 * Created by PhpStorm.
 * User: Vladimir
 * Date: 18.11.17
 */

namespace div\DeployAgent;


use Cocur\BackgroundProcess\BackgroundProcess;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StartWebDeployCommand extends Command
{
    protected function configure()
    {
        $this->setName('start-web-deploy');
        $this->setDescription('Запуск деплоя из веб скрипта');
        $this->addArgument('key', InputArgument::REQUIRED, 'Ключ веб деплоя');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $root = $this->getProjectRoot();

        if (is_null($root)) {
            throw new \Exception('Не найден корень проекта');
        }

        chdir($root);

        /** @var Application $app */
        $app = $this->getApplication();

        $config = $app->readConfig();

        if (!$config['webDeploy']) {
            throw new \Exception('Для проекта отключен веб деплой');
        }

        if ($config['deployKey'] !== $input->getArgument('key')) {
            throw new \Exception('Передан невалидный ключ деплоя');
        }

        $executable = $app->getExecutablePath();

        $process = new BackgroundProcess("$executable start");

        $process->run();

        if ($process->isRunning()) {
            $output->writeln('<info>Деплой успешно запущен</info>');
        } else {
            throw new \Exception('Не удалось запустить процесс деплоя');
        }
    }

    protected function getProjectRoot()
    {
        $current = getcwd();

        $parts = explode(DIRECTORY_SEPARATOR, $current);

        $count = count($parts);

        for ($i = 0; $i < $count; $i++) {
            $path = implode(DIRECTORY_SEPARATOR, $parts) . DIRECTORY_SEPARATOR . 'deploy.json';

            if (file_exists($path)) {
                return dirname($path);
            } else {
                array_pop($parts);
            }
        }

        return null;
    }
}
