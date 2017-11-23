<?php
/**
 * Created by PhpStorm.
 * User: Vladimir
 * Date: 23.11.17
 */

namespace div\DeployAgent;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearOpCacheCommand extends Command
{
    protected function configure()
    {
        $this->setName('clear-opcache');
        $this->setDescription('Сброс opcache');
    }

    protected function execute(InputInterface$input, OutputInterface $output)
    {
        /** @var Application $app */
        $app = $this->getApplication();

        $config = $app->readConfig();

        if (!isset($config['webRoot'])) {
            throw new \Exception('В конфигурации не указан параметр webRoot');
        }
        if (!isset($config['host'])) {
            throw new \Exception('В конфигурации не указан параметр host');
        }

        $fileName = md5(time() . rand(0, 99999) . '28hd2c99chsdc') . '.php';

        $path = getcwd() . DIRECTORY_SEPARATOR . $config['webRoot'] . DIRECTORY_SEPARATOR . $fileName;

        $content = <<<'EOT'
<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'success';
}

EOT;
        file_put_contents($path, $content);

        $ch = curl_init("http://$config[host]/$fileName");

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

        $output->writeln('<info>Opcache успешно очищен</info>');
    }
}
