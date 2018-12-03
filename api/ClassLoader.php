<?php
#########################################################################################
#
# Copyright 2010-2015  Maya Studios (http://www.mayastudios.com)
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#########################################################################################

namespace MSCL;


/**
 * Implements an class loader that automatically loads class files when the classes are first used. Use
 * {@link register()} to register one.
 *
 * NOTE: The class loader respects the PSR-4 autoloader specification ({@link http://www.php-fig.org/psr/psr-4/}).
 */
final class ClassLoader
{
    /**
     * The base namespace this class loader is responsible for; can be a nested namespace (e.g. "\ns1\ns2"). Will have
     * leading but no trailing backslash.
     * @var string
     */
    private $m_baseNamespace;

    /**
     * The absolute path to the directory in which files for $baseNamespace are located.
     * @var string
     */
    private $m_namespaceDir;

    /**
     * Constructor.
     *
     * @param string $baseNamespace the base namespace this class loader is responsible for; can be a nested namespace
     *                              (e.g. "ns1\ns2"). May have a leading backslash or not. The namespace is
     *                              case-sensitive. Must not be the root namespace \.
     * @param string $namespaceDir  the absolute path to the directory in which files for $baseNamespace are located.
     *                              Note that the directory must exist.
     */
    private function __construct($baseNamespace, $namespaceDir)
    {
        $baseNamespace = trim($baseNamespace, '\\');

        if ($baseNamespace == '')
        {
            throw new \InvalidArgumentException("The base namespace must not be empty.");
        }

        $this->m_baseNamespace = $baseNamespace;


        if (!\is_dir($namespaceDir))
        {
            throw new \InvalidArgumentException("The directory '" . $namespaceDir . "' does not exist.");
        }

        $this->m_namespaceDir = $namespaceDir;
    }

    /**
     * Tries to load the specified class name.
     *
     * @param string $className the fully-qualified class name (without leading backslash).
     */
    private function autoload($className)
    {
        # Usually there shouldn't be a leading backslash here but to be on the safe side.
        $className = ltrim($className, '\\');

        if (!string_starts_with($className, $this->m_baseNamespace))
        {
            # We're not responsible for this namespace.
            return;
        }

        # Class name with namespace relative to the base namespace. Will have leading backslash.
        $relClassName = substr($className, strlen($this->m_baseNamespace));

        $fileName = $relClassName;

        if (DIRECTORY_SEPARATOR != '\\')
        {
            $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $relClassName);
        }

        $fileName = $this->m_namespaceDir . $fileName . '.php';

        if (\is_file($fileName))
        {
            /** @noinspection PhpIncludeInspection */
            require($fileName);
        }
    }

    /**
     * Registers a new auto class loader.
     *
     * @param string $baseNamespace the base namespace this class loader is responsible for; can be a nested namespace
     *                              (e.g. "ns1\ns2"). May have a leading backslash or not. The namespace is
     *                              case-sensitive. Must not be the root namespace \.
     * @param string $namespaceDir  the absolute path to the directory in which files for $baseNamespace are located.
     *                              Note that the directory must exist.
     */
    public static function register($baseNamespace, $namespaceDir)
    {
        $loader = new ClassLoader($baseNamespace, $namespaceDir);
        spl_autoload_register(array($loader, 'autoload'));
    }
}
