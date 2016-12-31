<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use Grav\Common\Cache;
use Grav\Console\ConsoleCommand;
use Symfony\Component\Console\Input\InputOption;

class ClearCacheCommand extends ConsoleCommand
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('clear-cache')
            ->setAliases(['clearcache'])
            ->setDescription('Clears Grav cache')
            ->addOption('all', null, InputOption::VALUE_NONE, 'If set will remove all including compiled, twig, doctrine caches')
            ->addOption('assets-only', null, InputOption::VALUE_NONE, 'If set will remove only assets/*')
            ->addOption('images-only', null, InputOption::VALUE_NONE, 'If set will remove only images/*')
            ->addOption('cache-only', null, InputOption::VALUE_NONE, 'If set will remove only cache/*')
            ->addOption('tmp-only', null, InputOption::VALUE_NONE, 'If set will remove only tmp/*')
            ->setHelp('The <info>clear-cache</info> deletes all cache files');
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $this->cleanPaths();
    }

    /**
     * loops over the array of paths and deletes the files/folders
     */
    private function cleanPaths()
    {
        $this->output->writeln('');
        $this->output->writeln('<magenta>Clearing cache</magenta>');
        $this->output->writeln('');

        if ($this->input->getOption('all')) {
            $remove = 'all';
        } elseif ($this->input->getOption('assets-only')) {
            $remove = 'assets-only';
        } elseif ($this->input->getOption('images-only')) {
            $remove = 'images-only';
        } elseif ($this->input->getOption('cache-only')) {
            $remove = 'cache-only';
        } elseif ($this->input->getOption('tmp-only')) {
            $remove = 'tmp-only';
        } else {
            $remove = 'standard';
        }

        foreach (Cache::clearCache($remove) as $result) {
            $this->output->writeln($result);
        }
    }
}

