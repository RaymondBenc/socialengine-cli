<?php

namespace SocialEngine\Console\Helper;

use SocialEngine\Console\Command;

/**
 * SE Packages Helper Class
 *
 * @package SocialEngine\Console\Helper
 */
class Packages
{
    /**
     * Base structure of manifest data.
     *
     * @var array
     */
    private $structure = array(
        'base' => array(
            'path' => '/',
            'manifest' => 'application/settings/manifest.php',
            'array' => false,
            'type' => 'core',
        ),
        'install' => array(
            'path' => 'install',
            'manifest' => 'config/manifest.php',
            'array' => false,
            'type' => 'core',
        ),

        'externals' => array(
            'path' => 'externals',
            'manifest' => 'manifest.php',
            'array' => true,
        ),
        'languages' => array(
            'path' => 'application/languages',
            'manifest' => 'manifest.php',
            'array' => true,
        ),
        'libraries' => array(
            'path' => 'application/libraries',
            'manifest' => 'manifest.php',
            'array' => true,
        ),
        'modules' => array(
            'path' => 'application/modules',
            'manifest' => 'settings/manifest.php',
            'array' => true,
        ),
        'plugins' => array(
            'path' => 'application/plugins',
            'manifest' => 'manifest.php',
            'array' => true,
        ),
        'themes' => array(
            'path' => 'application/themes',
            'manifest' => 'manifest.php',
            'array' => true,
        ),
        'widgets' => array(
            'path' => 'application/widgets',
            'manifest' => 'manifest.php',
            'array' => true,
        ),

        'patch' => array(
            'path' => null,
            'manifest' => 'manifest.php',
            'array' => false,
        ),
    );

    /**
     * Direct access to SE DB config file.
     *
     * @var array
     */
    private $config;

    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    private $db;

    /**
     * @var Command
     */
    private $command;

    public function __construct(Command $command)
    {
        defined('APPLICATION_PATH') || define('APPLICATION_PATH', SE_CONSOLE_DIR);
        defined('DS') || define('DS', DIRECTORY_SEPARATOR);
        defined('PS') || define('PS', PATH_SEPARATOR);
        defined('_ENGINE') || define('_ENGINE', true);

        $configFile = APPLICATION_PATH . 'application/settings/database.php';
        if (!file_exists($configFile)) {
            throw new \Exception('This command requires SE to be installed.');
        }

        $this->command = $command;
        $this->config = require($configFile);
        $this->db = \Zend_Db::factory($this->config['adapter'], $this->config['params']);
    }

    /**
     * Get all SE folders that may have a package.
     *
     * @return array
     */
    public function getActions()
    {
        $actions = [];
        $actions[] = 'base';
        $actions[] = 'install';
        $actions[] = 'externals';
        $actions[] = 'languages';
        $actions[] = 'libraries';
        $actions[] = 'modules';
        $actions[] = 'plugins';
        $actions[] = 'themes';
        $actions[] = 'widgets';
        $actions[] = 'themes';

        return $actions;
    }

    /**
     * List all installed packages, based on JSON file.
     *
     * @return \SplFileInfo[]
     */
    public function getJsonFiles()
    {
        $files = [];
        foreach (scandir(SE_CONSOLE_DIR . 'application/packages') as $child) {
            $childFile = SE_CONSOLE_DIR . 'application/packages/' . $child;
            if (is_file($childFile) && substr($childFile, -5) == '.json') {
                $files[] = new \SplFileInfo($childFile);
            }
        }

        return $files;
    }

    /**
     * Return the base package structure.
     *
     * @return array
     */
    public function getStructure()
    {
        return $this->structure;
    }

    /**
     * Install packages and run install operations.
     */
    public function buildPackageDb()
    {
        $packageManager = new \Engine_Package_Manager([
            'basePath' => APPLICATION_PATH,
            'db' => $this->db
        ]);

        $operations = array();
        foreach ($packageManager->listInstalledPackages() as $package) {
            $operations[] = new \Engine_Package_Manager_Operation_Install($packageManager, $package);
        }

        foreach (['preinstall', 'install', 'postinstall'] as $type) {
            $ret = $packageManager->callback($operations, $type);
            $this->command->write(' ----- ' . $type . ' ----- ');
            foreach ($ret as $result) {
                $messages = trim(join("\n", array_filter(array_merge($result['errors'], $result['messages']))));
                if (!empty($messages)) {
                    $this->command->write('  -> ' . $result['key']);
                    $this->command->write('  ----> ' . $messages);
                }
            }
        }
    }

    /**
     * @param string $manifestPath Path to manifest file.
     *
     * @return \Engine_Package_Manifest
     *
     * @throws \Exception
     */
    public function buildPackageFile($manifestPath)
    {
        $date = time();

        $manifestData = require $manifestPath;
        if (empty($manifestData['package'])) {
            throw new \Exception(sprintf('Missing package data for package in path: %s', $manifestPath));
        }
        $manifestData = $manifestData['package'];
        $manifestData['date'] = $date;

        $package = new \Engine_Package_Manifest($manifestData);

        return $package;
    }
}