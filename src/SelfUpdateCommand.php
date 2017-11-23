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

class SelfUpdateCommand extends Command
{
    protected function configure()
    {
        $this->setName('self-update');
        $this->setDescription('Обновление утилиты до последней версии');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!\Phar::running()) {
            throw new \Exception('Обновить можно только phar версию утилиты');
        }

        /** @var Application $app */
        $app = $this->getApplication();

        $selfPath = $app->getExecutable(false);

        if (!is_writable($selfPath)) {
            throw new \Exception("У вас нет прав для записи файла $selfPath");
        }

        $content = file_get_contents($this->getUpdateUrl());
        if (!$content) {
            throw new \Exception('Не удалось загрузить новую версию с сервера');
        }

        if (!file_put_contents($selfPath, $content)) {
            throw new \Exception('Не удалось обновить утилиту');
        }

        $executable = $app->getExecutable();

        $ver = trim(shell_exec("$executable -V"));

        $output->writeln($ver);
        $output->writeln('<info>Утилита успешно обновлена</info>');
    }

    protected function getUpdateUrl()
    {
        return 'http://deploy.div26.ru/deploy.phar';
    }
}
