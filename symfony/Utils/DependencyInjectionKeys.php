<?php

/**
 * TechDivision\Import\App\Utils\DependencyInjectionKeys
 *
 * PHP version 7
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   https://opensource.org/licenses/MIT
 * @link      https://github.com/techdivision/import-app-simple
 * @link      http://www.techdivision.com
 */

namespace TechDivision\Import\App\Utils;

/**
 * A utility class for the DI service keys.
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   https://opensource.org/licenses/MIT
 * @link      https://github.com/techdivision/import-app-simple
 * @link      http://www.techdivision.com
 */
class DependencyInjectionKeys extends \TechDivision\Import\Utils\DependencyInjectionKeys
{

    /**
     * The key for the application service.
     *
     * @var string
     */
    const SIMPLE = 'import_app_simple.simple';
}
