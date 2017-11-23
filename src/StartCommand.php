<?php
/**
 * Created by PhpStorm.
 * User: Vladimir
 * Date: 18.11.17
 */

namespace div\DeployAgent;


use Cocur\BackgroundProcess\BackgroundProcess;
use div\DeployAgent\helpers\GitHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StartCommand extends Command
{
    protected $pid;

    protected $remote;

    protected $branch;

    protected function configure()
    {
        $this->setName('start');
        $this->setDescription('Запуск процесса деплоя');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Запуск процесса деплоя даже если в репозитории нет изменений');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Application $app */
        $app = $this->getApplication();

        if (!getenv('HOME')) {
            $home = $app->getUserHome();
            putenv("HOME=$home");
        }

        $config = $app->readConfig();

        $git = new GitHelper();

        if (empty($config['remote'])) {
            throw new \Exception('В конфигурации не указан удалённый репозиторий');
        }

        $this->remote = $config['remote'];

        if (empty($config['branch'])) {
            throw new \Exception('В конфигурации не указана ветка');
        }

        $this->branch = $config['branch'];

        if ($app->getPid($config['remote'], $config['branch'])) {
            throw new \Exception('Уже запущен другой процесс деплоя');
        }

        $this->checkIsRunning($config);

        $this->pid = getmypid();
        $app->savePid($this->pid, $config['remote'], $config['branch']);
        $this->registerShutdown();

        if (empty($config['host'])) {
            throw new \Exception('В конфигурации не указан хост');
        }

        $oldHead = $git->getHead();

        $git->exec("fetch $config[remote] $config[branch]");
        $git->exec("checkout FETCH_HEAD");

        $newHead = $git->getHead();

        if (!$input->getOption('force') && $oldHead == $newHead) {
            $output->writeln('<comment>В репозитории нет изменений</comment>');
            return;
        }

        if (!empty($config['commands'])) {
            $this->execCommands($config['commands']);
        }

        if (!empty($config['clearOpCache'])) {
            try {
                $opcacheCommand = $app->find('clear-opcache');
                $opcacheCommand->execute($input, $output);
            } catch (\Exception $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
            }
        }

        $output->writeln('<info>Деплой успешно завершён</info>');
    }

    protected function execCommands(array $commands)
    {
        foreach ($commands as $command) {
            shell_exec($command);
        }
    }

    protected function checkIsRunning($config)
    {
        /** @var Application $app */
        $app = $this->getApplication();

        $existingPid = $app->getPid($config['remote'], $config['branch']);

        /** @var BackgroundProcess $existingProcess */
        $existingProcess = BackgroundProcess::createFromPID($existingPid);

        if ($existingProcess->isRunning()) {
            throw new \Exception('Уже запущен другой процесс деплоя в фоновом режиме');
        }
    }

    protected function registerShutdown()
    {
        register_shutdown_function(function () {
            /** @var Application $app */
            $app = $this->getApplication();

            $app->removePid($this->pid, $this->remote, $this->branch);
        });
    }
}
