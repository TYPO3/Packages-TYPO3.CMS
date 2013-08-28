<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Christian Kuhn <lolli@schwarzbu.ch>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/** TODO */


/**
 * This file is defined in FuntionalTests.xml and called by phpunit
 * before instantiating the test suites, it must also be included
 * with phpunit parameter --bootstrap if executing single test case classes.
 */

/**
 * Require classes the functional test classes extend from or use for further bootstrap.
 * Only files required for "new TestCaseClass" are required here and a general exception
 * that is thrown by setUp() code.
 */
require_once(__DIR__ . '/../Tests/BaseTestCase.php');
require_once(__DIR__ . '/../Tests/UnitTestCase.php');
require_once(__DIR__ . '/../Tests/Exception.php');

if (!defined('ORIGINAL_ROOT')) {
	define('ORIGINAL_ROOT', $_SERVER['PWD'] . '/');
}

$instancePath = ORIGINAL_ROOT;
$_SERVER['PWD'] = $instancePath;
$_SERVER['argv'][0] = 'index.php';

define('TYPO3_MODE', 'BE');
define('TYPO3_cliMode', TRUE);

require $instancePath . '/typo3/sysext/core/Classes/Core/CliBootstrap.php';
\TYPO3\CMS\Core\Core\CliBootstrap::checkEnvironmentOrDie();

require $instancePath . '/typo3/sysext/core/Classes/Core/Bootstrap.php';
\TYPO3\CMS\Core\Core\Bootstrap::getInstance()
	->baseSetup('')

	/** do we want that? */
	->loadConfigurationAndInitialize(FALSE)
	->loadTypo3LoadedExtAndExtLocalconf(FALSE)
	->applyAdditionalConfigurationSettings()
	->initializeTypo3DbGlobal();


?>