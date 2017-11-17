<?php
/**
 * Created by PhpStorm.
 * User: Vladimir
 * Date: 16.11.17
 */

namespace div\DeployAgent;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class InitCommand extends Command
{
    const PRESET = 'p';
    const COMMANDS_SET = 'c';

    protected $input;

    protected $output;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    protected function configure()
    {
        $this->setName('init');
        $this->setDescription('Инициализация файла конфигурации');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        $this->initialCheck($helper);

        $result = [];

        $result['remote'] = $this->askRemote($helper);
        $result['branch'] = $this->askBranch($helper);
        $commandsOrPreset = $this->askCommandsOrPreset($helper);

        switch ($commandsOrPreset) {
            case self::PRESET:
                $preset = $this->askPreset($helper);
                $result['commands'] = $this->getCommandsFromPreset($preset);
                break;
            case self::COMMANDS_SET:
                $output->writeln('Укажите список комманд, ввод коммад закончится, когда будет введено пустое значение');
                while ($command = $this->askCommand($helper)) {
                    $result['commands'][] = $command;
                }
                break;
        }

        $webDeploy = $this->askWebDeploy($helper);

        if ($webDeploy) {
            $result['webRoot'] = $this->askWebRoot($helper);
            $result['deployKey'] = $this->askDeployKey($helper);

            $webHooks = $this->askWebHooks($helper);

            if ($webHooks) {
                $result['apiKey'] = $this->askApiKey($helper);
            }
        }

        $this->generateConfig($result);
    }

    protected function initialCheck(QuestionHelper $helper)
    {
        if (file_exists($this->getConfigPath())) {
            $q = new ConfirmationQuestion('Файл конфигурации уже существует, заменить[y/N]? ', false);

            if (!$helper->ask($this->input, $this->output, $q)) {
                exit(0);
            }
        }
    }

    protected function askRemote(QuestionHelper $helper)
    {
        $q = new Question('Укажите удалённый репозиторий [origin]: ', 'origin');

        return $helper->ask($this->input, $this->output, $q);
    }

    protected function askBranch(QuestionHelper $helper)
    {
        $q = new Question('Укажите ветку [master]: ', 'master');

        return $helper->ask($this->input, $this->output, $q);
    }

    protected function askCommandsOrPreset(QuestionHelper $helper)
    {
        $q = new ChoiceQuestion('Вводить набор комманд или пресет [p]: ', [
            self::PRESET => 'Пресет',
            self::COMMANDS_SET => 'Набор комманд',
        ], 'p');

        return $helper->ask($this->input, $this->output, $q);
    }

    protected function askPreset(QuestionHelper $helper)
    {
        $q = new Question('Укажите название пресета: ');

        $q->setValidator(function ($val) {
            if (!$val) {
                throw new \Exception('Укажите название пресета');
            }
        });

        return $helper->ask($this->input, $this->output, $q);
    }

    protected function askCommand(QuestionHelper $helper)
    {
        $q = new Question('Укажите комманду: ');

        return $helper->ask($this->input, $this->output, $q);
    }

    protected function askWebDeploy(QuestionHelper $helper)
    {
        $q = new ConfirmationQuestion('Настроить возможность деплоя через http запросы?[Y/n]: ');

        return $helper->ask($this->input, $this->output, $q);
    }

    protected function askWebRoot(QuestionHelper $helper)
    {
        $q = new Question('Укажите путь до корневой папки веб сервера [по умолчанию текущая директория]: ', '');
        $q->setValidator(function ($val) {
            if (!$val) {
                return $val;
            }

            if (strpos($val, DIRECTORY_SEPARATOR) === 0) {
                $dir = $val;
            } else {
                $dir = getcwd() . DIRECTORY_SEPARATOR . $val;
            }

            if (!is_dir($dir)) {
                throw new \Exception('Такой директории не существует');
            }

            return $val;
        });

        return $helper->ask($this->input, $this->output, $q);
    }

    protected function askDeployKey(QuestionHelper $helper)
    {
        $default = sha1(time() . rand(0, 999999) . '236t374fh2383c3x8f');

        $q = new Question('Укажите ключ для деплоя [по умолчанию сгенерируется случайное значение]: ', $default);

        $q->setValidator(function ($val) {
            if (strlen($val) < 32) {
                throw new \Exception('Минимальная длина ключа 32 символа');
            }

            return $val;
        });

        return $helper->ask($this->input, $this->output, $q);
    }

    protected function askWebHooks(QuestionHelper $helper)
    {
        $q = new ConfirmationQuestion('Настроить веб хуки[Y/n]?: ');

        return $helper->ask($this->input, $this->output, $q);
    }

    protected function askApiKey(QuestionHelper $helper)
    {
        $q = new Question('Укажите API ключ для битбакета: ');

        $q->setValidator(function ($val) {
            if (!$val) {
                throw new \Exception('Укажите ключ');
            }

            return $val;
        });

        return $helper->ask($this->input, $this->output, $q);
    }

    protected function generateConfig(array $data)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        file_put_contents($this->getConfigPath(), $json);
    }

    protected function getConfigPath()
    {
        return getcwd() . DIRECTORY_SEPARATOR . '/deploy.json';
    }

    protected function getCommandsFromPreset($preset)
    {
        return [];
    }
}
