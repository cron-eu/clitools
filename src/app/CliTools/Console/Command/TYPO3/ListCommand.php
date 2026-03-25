<?php

namespace CliTools\Console\Command\TYPO3;

/*
 * CliTools Command
 * Copyright (C) 2016 WebDevOps.io
 * Copyright (C) 2015 Markus Blaschke <markus@familie-blaschke.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

use CliTools\Utility\Typo3Utility;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends \CliTools\Console\Command\AbstractCommand
{

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setName('typo3:list')
             ->setDescription('List all TYPO3 instances')
             ->addArgument(
                 'path',
                 InputArgument::OPTIONAL,
                 'Path to TYPO3 instance'
             );
    }

    /**
     * Execute command
     *
     * @param  InputInterface  $input  Input instance
     * @param  OutputInterface $output Output instance
     *
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // ####################
        // Init
        // ####################
        $basePath = $this->getApplication()
                         ->getConfigValue('config', 'www_base_path', '/var/www/');
        $maxDepth = 3;

        $basePath = Typo3Utility::guessBestTypo3BasePath($basePath, $input, 'path');

        // ####################
        // Find and loop through TYPO3 instances
        // ####################
        $typo3List = array();

        foreach (Typo3Utility::getTypo3InstancePathList($basePath, $maxDepth) as $dirPath) {
            $typo3Version = $this->detectTypo3Version($dirPath);

            if ($typo3Version !== null) {
                $typo3Version = '<info>' . $typo3Version . '</info>';
            } else {
                $typo3Version = '<error>unknown</error>';
            }

            $typo3List[] = array(
                $dirPath,
                $typo3Version,
            );
        }

        $table = new Table($output);
        $table->setHeaders(array('Path', 'Version'));
        foreach ($typo3List as $row) {
            $table->addRow(array_values($row));
        }

        $table->render();

        return 0;
    }

    /**
     * Detect TYPO3 version from installation path
     *
     * @param string $dirPath Path to TYPO3 installation
     * @return string|null
     */
    protected function detectTypo3Version(string $dirPath): ?string
    {
        // Try composer.json in vendor (Composer mode)
        $composerFile = $dirPath . '/vendor/typo3/cms-core/composer.json';
        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true);
            if (isset($composerData['version'])) {
                return $composerData['version'];
            }
        }

        // Try composer.lock in project root
        $composerLock = $dirPath . '/composer.lock';
        if (file_exists($composerLock)) {
            $lockData = json_decode(file_get_contents($composerLock), true);
            if (isset($lockData['packages'])) {
                foreach ($lockData['packages'] as $package) {
                    if ($package['name'] === 'typo3/cms-core') {
                        return $package['version'];
                    }
                }
            }
        }

        // Try ext_emconf.php (classic mode)
        $extEmconfFile = $dirPath . '/typo3/sysext/core/ext_emconf.php';
        if (file_exists($extEmconfFile)) {
            $content = file_get_contents($extEmconfFile);
            if (preg_match('/[\'"]version[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
