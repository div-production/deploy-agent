<?php
/**
 * Created by PhpStorm.
 * User: Vladimir
 * Date: 16.11.17
 */

namespace div\DeployAgent;


use div\DeployAgent\helpers\GitHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class InitCommand extends Command
{
    const PRESET = 'p';
    const COMMANDS_SET = 'c';

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
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
        $result['host'] = $this->askHost($helper);

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

        $result['webDeploy'] = $this->askWebDeploy($helper);

        if ($result['webDeploy']) {
            $result['webRoot'] = $this->askWebRoot($helper);
            $result['deployKey'] = $this->askDeployKey($helper);

            $this->createWebDeployFile($result['webRoot']);
            $this->output->writeln('<info>Файл для веб деплоя успешно создан</info>');

            $webHooks = $this->askWebHooks($helper);

            if ($webHooks) {
                $apiKey = $this->askApiKey($helper);
                try {
                    $this->createWebHooks($apiKey, $result['host'], $result['deployKey']);
                    $output->writeln('<info>Веб хук успешно создан</info>');
                } catch (\Exception $e) {
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                }
            }
        }

        $this->generateConfig($result);

        $this->output->writeln('<info>Файл конфигурации успешно сгенерирован</info>');

        $this->gitignore($helper, $result);
    }

    protected function initialCheck(QuestionHelper $helper)
    {
        if (!is_dir($this->getGitDirectory())) {
            throw new \Exception('Отсутствует директория .git');
        }

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

        $q->setValidator(function ($val) {
            $git = new GitHelper();

            if (!$git->getRemoteUrl($val)) {
                throw new \Exception('Удалённый репозиторий не найден');
            }

            return $val;
        });

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

            return preg_replace('/(\/|\\\)$/', '', $val);
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

    protected function askHost(QuestionHelper $helper)
    {
        $q = new Question('Укажите хост: ');

        $q->setValidator(function ($val) {
            if (!$val) {
                throw new \Exception('Укажите хост');
            }

            return $val;
        });

        return $helper->ask($this->input, $this->output, $q);
    }

    protected function generateConfig(array $data)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($this->getConfigPath(), $json) === false) {
            throw new \Exception('Не удалось сохранить файл конфигурации');
        }
    }

    protected function getConfigPath()
    {
        return getcwd() . DIRECTORY_SEPARATOR . '/deploy.json';
    }

    protected function getCommandsFromPreset($preset)
    {
        return [];
    }

    protected function gitignore(QuestionHelper $helper, $config)
    {
        $q = new ConfirmationQuestion('Добавить сгенерированные файлы в gitignore[Y/n]? ');

        if (!$helper->ask($this->input, $this->output, $q)) {
            return;
        }

        $infoDir = $this->getGitDirectory() . DIRECTORY_SEPARATOR . 'info';
        if (!$infoDir) {
            mkdir($infoDir);
        }

        $excludeFile = $infoDir . DIRECTORY_SEPARATOR . 'exclude';
        if (!file_exists($excludeFile)) {
            file_put_contents($excludeFile, '');
        }

        $excludeLines = explode(PHP_EOL, file_get_contents($excludeFile));

        $configFile = DIRECTORY_SEPARATOR . basename($this->getConfigPath());

        if ($config['webDeploy'] == true) {
            if ($config['webRoot']) {
                $webDeployFile = DIRECTORY_SEPARATOR . $config['webRoot'] . DIRECTORY_SEPARATOR . $this->getWebDeployFile();
            } else {
                $webDeployFile = DIRECTORY_SEPARATOR . $this->getWebDeployFile();
            }
        } else {
            $webDeployFile = null;
        }

        $configFileExists = false;
        $webDeployFileExists = false;

        foreach ($excludeLines as $line) {
            if (strpos($line, $configFile) === 0) {
                $configFileExists = true;
            }
            if ($webDeployFile && strpos($line, $webDeployFile) === 0) {
                $webDeployFileExists = true;
            }
        }

        if (!$configFileExists) {
            $excludeLines[] = $configFile;
        }
        if (!$webDeployFileExists) {
            $excludeLines[] = $webDeployFile;
        }

        if (!$configFileExists || !$webDeployFileExists) {
            $excludeLines[] = '';
            $data = implode(PHP_EOL, $excludeLines);
            file_put_contents($excludeFile, $data);
        }

        $this->output->writeLn('<info>Файлы успешно добавлены в gitignore</info>');
    }

    protected function getGitDirectory()
    {
        return getcwd() . DIRECTORY_SEPARATOR . '/.git';
    }

    protected function getWebDeployFile()
    {
        return 'deploy.php';
    }

    protected function createWebHooks($apiKey, $host, $deployKey)
    {
        $git = new GitHelper();

        $owner = $git->getOwnerName();
        $repo = $git->getRepositoryName();

        $ch = curl_init("https://api.bitbucket.org/2.0/repositories/$owner/$repo/hooks");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $git->getOwnerName() . ':' . $apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        $deployFile = $this->getWebDeployFile();

        $data = [
            'description' => 'Deploy',
            'url' => "http://$host/$deployFile?key=$deployKey",
            'active' => true,
            'events' => [
                'repo:push',
            ],
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        curl_exec($ch);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code != 201) {
            throw new \Exception('При создании веб хука произошла ошибка, код ' . $code);
        }

        curl_close($ch);
    }

    protected function createWebDeployFile($webRoot)
    {
        $file = $this->getWebDeployFile();

        $content = "<?php\n";

        $path = getcwd() . DIRECTORY_SEPARATOR . $webRoot . $file;

        if (file_put_contents($path, $content) === false) {
            throw new \Exception('Не удалрсь создать файл для веб деплоя');
        }
    }
}
