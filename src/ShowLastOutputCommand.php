<?php
/**
 * Created by PhpStorm.
 * User: Vladimir
 * Date: 26.11.17
 */

namespace div\DeployAgent;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowLastOutputCommand extends Command
{
    protected function configure()
    {
        $this->setName('show-last-output');
        $this->setDescription('Получение послендего вывода деплоя');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Application $app */
        $app = $this->getApplication();

        $file = $app->getDeployOutputFile();

        if (file_exists($file)) {
            $output->writeLn(file_get_contents($file));
        } else {
            throw new \Exception('Отсутствует файл с выводом');
        }
    }
}
