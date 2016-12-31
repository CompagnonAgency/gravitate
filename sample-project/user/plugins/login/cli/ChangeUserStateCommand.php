<?php
namespace Grav\Plugin\Console;

use Grav\Console\ConsoleCommand;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\User\User;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class CleanCommand
 *
 * @package Grav\Console\Cli
 */
class ChangeUserStateCommand extends ConsoleCommand
{

    /**
     * @var array
     */
    protected $options = [];

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName('toggle-user')
            ->setAliases(['disableuser', 'enableuser', 'toggleuser', 'change-user-state'])
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED,
                'The username'
            )
            ->addOption(
                'state',
                's',
                InputOption::VALUE_REQUIRED,
                'The state of the account. Can be either `enabled` or `disabled`. [default: "enabled"]'
            )
            ->setDescription('Changes whether user can login or not')
            ->setHelp('The <info>toggle-user</info> sets a user\'s login status to enabled or disabled.')
        ;
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $this->options = [
            'user'        => $this->input->getOption('user'),
            'state'       => $this->input->getOption('state')
        ];

        $this->validateOptions();

        $helper = $this->getHelper('question');
        $data   = [];

        $this->output->writeln('<green>Setting User State</green>');
        $this->output->writeln('');

        if (!$this->options['user']) {
            // Get username and validate
            $question = new Question('Enter a <yellow>username</yellow>: ');
            $question->setValidator(function ($value) {
                return $this->validate('user', $value);
            });

            $username = $helper->ask($this->input, $this->output, $question);
        } else {
            $username = $this->options['user'];
        }


        if (!$this->options['state'] && !count(array_filter($this->options))) {
            // Choose State
            $question = new ChoiceQuestion(
                'Please choose the <yellow>state</yellow> for the account:',
                array('enabled' => 'Enabled', 'disabled' => 'Disabled'),
                'enabled'
            );

            $question->setErrorMessage('State %s is invalid.');
            $data['state'] = $helper->ask($this->input, $this->output, $question);
        } else {
            $data['state'] = $this->options['state'] ?: 'enabled';
        }

        // Lowercase the username for the filename
        $username = strtolower($username);

        // Grab the account file and read in the information before setting the file (prevent setting erase)
        $oldUserFile = CompiledYamlFile::instance(self::getGrav()['locator']->findResource('account://' . $username . YAML_EXT, true, true));
        $oldData = $oldUserFile->content();
        
        //Set the state feild to new state
        $oldData['state'] = $data['state'];
        
        // Create user object and save it using oldData (with updated state)
        $user = new User($oldData);
        $file = CompiledYamlFile::instance(self::getGrav()['locator']->findResource('account://' . $username . YAML_EXT, true, true));
        $user->file($file);
        $user->save();

        $this->output->writeln('');
        $this->output->writeln('<green>Success!</green> User <cyan>' . $username . '</cyan> state set to .' . $data['state']);
    }

    /**
     *
     */
    protected function validateOptions()
    {
        foreach (array_filter($this->options) as $type => $value) {
            $this->validate($type, $value);
        }
    }

    /**
     * @param        $type
     * @param        $value
     * @param string $extra
     *
     * @return mixed
     */
    protected function validate($type, $value, $extra = '')
    {
        switch ($type) {
            case 'user':
                if (!preg_match('/^[a-z0-9_-]{3,16}$/', $value)) {
                    throw new \RuntimeException('Username should be between 3 and 16 characters, including lowercase letters, numbers, underscores, and hyphens. Uppercase letters, spaces, and special characters are not allowed');
                }
                if (!file_exists(self::getGrav()['locator']->findResource('account://' . $value . YAML_EXT))) {
                    throw new \RuntimeException('Username "' . $value . '" does not exist, please pick another username');
                }

                break;

            case 'state':
                if ($value !== 'enabled' && $value !== 'disabled') {
                    throw new \RuntimeException('State is not valid');
                }

                break;
        }

        return $value;
    }
}
