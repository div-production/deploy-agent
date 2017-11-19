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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Application $app */
        $app = $this->getApplication();

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

        if ($oldHead == $newHead) {
            $output->writeln('<comment>В репозитории нет изменений</comment>');
            return;
        }

        if (!empty($config['commands'])) {
            $this->execCommands($config['commands']);
        }

        if (!empty($config['clearOpCache'])) {
            try {
                $this->clearOpCache($config['webRoot'], $config['host']);
                $output->writeln('<info>Opcache успешно очищен</info>');
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

    protected function clearOpCache($webRoot, $host)
    {
        $fileName = md5(time() . rand(0, 99999) . '28hd2c99chsdc') . '.php';

        $path = getcwd() . DIRECTORY_SEPARATOR . $webRoot . DIRECTORY_SEPARATOR . $fileName;

        $content = <<<'EOT'
<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'success';
}

EOT;
        file_put_contents($path, $content);

        $ch = curl_init("http://$host/$fileName");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $result = curl_exec($ch);

        curl_close($ch);

        unlink($path);

        if ($result != 'success') {
            throw new \Exception('Не удалось очистить opcache');
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
