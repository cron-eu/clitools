<?php

namespace CliTools\Console\Command\Traits;

/*
 * CliTools Command
 * Copyright (C) 2016 WebDevOps.io
 * Copyright (C) 2026 Philipp Kitzberger <typo3@kitze.net>
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

use CliTools\Console\Command\AbstractDockerCommand;
use CliTools\Database\DatabaseConnection;
use CliTools\Reader\ConfigReader;
use CliTools\Utility\DockerUtility;
use CliTools\Utility\PhpUtility;
use CliTools\Utility\UnixUtility;
use Symfony\Component\Yaml\Yaml;

trait ClisyncConfigTrait
{
    /**
     * Init database configuration from clisync.yml if present
     *
     * @param ConfigReader|null $config Optional config reader (if already loaded)
     */
    protected function initDatabaseConfigurationFromClisync(?ConfigReader $config = null)
    {
        if ($config === null) {
            $config = $this->loadClisyncConfig();
            if ($config === null) {
                return;
            }
        }

        // Handle Docker container configuration first (takes precedence)
        if ($this->initDockerContainerFromConfig($config)) {
            return;
        }

        // Apply standard MySQL credentials
        $this->applyMysqlCredentialsFromConfig($config);
    }

    /**
     * Load clisync.yml configuration
     *
     * @return ConfigReader|null
     */
    protected function loadClisyncConfig(): ?ConfigReader
    {
        $confFileList = array(
            'clisync.yml',
            '.clisync.yml',
        );

        $confFilePath = UnixUtility::findFileInDirectortyTree($confFileList);
        if (empty($confFilePath) || !file_exists($confFilePath)) {
            return null;
        }

        $conf = Yaml::parse(PhpUtility::fileGetContents($confFilePath));
        if (empty($conf)) {
            return null;
        }

        $config = new ConfigReader();
        $config->setData($conf);

        return $config;
    }

    /**
     * Apply MySQL credentials from config to DatabaseConnection
     *
     * @param ConfigReader $config
     */
    protected function applyMysqlCredentialsFromConfig(ConfigReader $config)
    {
        $hostname = DatabaseConnection::getDbHostname();
        $port = DatabaseConnection::getDbPort();
        $username = DatabaseConnection::getDbUsername();
        $password = DatabaseConnection::getDbPassword();

        if ($config->exists('LOCAL.mysql.hostname')) {
            $hostname = $config->get('LOCAL.mysql.hostname');
        }

        if ($config->exists('LOCAL.mysql.port')) {
            $port = $config->get('LOCAL.mysql.port');
        }

        if ($config->exists('LOCAL.mysql.username')) {
            $username = $config->get('LOCAL.mysql.username');
        }

        if ($config->exists('LOCAL.mysql.password')) {
            $password = $config->get('LOCAL.mysql.password');
        }

        $dsn = 'mysql:host=' . urlencode($hostname) . ';port=' . (int)$port;

        DatabaseConnection::setDsn($dsn, $username, $password);
    }

    /**
     * Init Docker container from config
     *
     * @param ConfigReader $config
     * @return bool True if Docker was configured
     */
    protected function initDockerContainerFromConfig(ConfigReader $config): bool
    {
        $useDockerMysql = false;

        if ($config->exists('LOCAL.mysql.docker')) {
            $this->setLocalDockerContainer(AbstractDockerCommand::DOCKER_ALIAS_MYSQL, $config->get('LOCAL.mysql.docker'));
            $useDockerMysql = true;
        } elseif ($config->exists('LOCAL.mysql.docker-compose')) {
            $this->setLocalDockerContainer(AbstractDockerCommand::DOCKER_ALIAS_MYSQL, $config->get('LOCAL.mysql.docker-compose'), true);
            $useDockerMysql = true;
        }

        if ($useDockerMysql) {
            $container = $this->getLocalDockerContainer(AbstractDockerCommand::DOCKER_ALIAS_MYSQL);
            $password = DockerUtility::getDockerContainerEnv($container, 'MYSQL_ROOT_PASSWORD');
            if (empty($password)) {
                $password = DockerUtility::getDockerContainerEnv($container, 'MARIADB_ROOT_PASSWORD');
            }
            DatabaseConnection::setDsn('mysql:host=localhost', 'root', $password);
            return true;
        }

        return false;
    }
}
