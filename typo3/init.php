<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2011 Kasper Skårhøj (kasperYYYY@typo3.com)
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
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * TYPO3 Backend initialization
 *
 * This script is called by every backend script.
 * The script authenticates the backend user.
 * In addition this script also initializes the database and other stuff by including the script localconf.php
 *
 * IMPORTANT:
 * This script exits if no user is logged in!
 * If you want the script to return even if no user is logged in,
 * you must define the constant TYPO3_PROCEED_IF_NO_USER=1
 * before you include this script.
 *
 *
 * This script does the following:
 * - extracts and defines path's
 * - includes certain libraries
 * - authenticates the user
 * - sets the configuration values (localconf.php)
 * - includes tables.php that sets more values and possibly overrides others
 * - load the groupdata for the user and set filemounts / webmounts
 *
 * For a detailed description of this script, the scope of constants and variables in it,
 * please refer to the document "Inside TYPO3"
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 * @package TYPO3
 * @subpackage core
 */

	// Prevent any unwanted output that may corrupt AJAX/compression. Note: this does
	// not interfeer with "die()" or "echo"+"exit()" messages!
ob_start();

	// Define constants
define('TYPO3_MODE', 'BE');

	// We use require instead of require_once here so we get a fatal error if
	// classes/Bootstrap/Backend.php is accidentally included twice (which would indicate a clear bug).
require('classes/Bootstrap/Backend.php');
Typo3_Bootstrap_Backend::getInstance()
	->checkEnvironmentOrDie()
	->defineBaseConstants()
	->defineAndCheckPaths('typo3/')
	->requireBaseClasses()
	->setUpEnvironment();

require(PATH_t3lib . 'config_default.php');

Typo3_Bootstrap_Backend::getInstance()
	->initializeTypo3DbGlobal(FALSE)
	->checkLockedBackendAndRedirectOrDie()
	->checkBackendIpOrDie()
	->checkSslBackendAndRedirectIfNeeded();

	// Connect to the database
	// Redirect to install tool if database host and database are not defined
if (!TYPO3_db_host && !TYPO3_db) {
	t3lib_utility_Http::redirect('install/index.php?mode=123&step=1&password=joh316');
} else {
	$TYPO3_DB->connectDB();
}

	// Checks for proper browser
if (!$CLIENT['BROWSER']) {
	throw new RuntimeException('Browser Error: Your browser version looks incompatible with this TYPO3 version!', 1294587023);
}

Typo3_Bootstrap_Backend::getInstance()
	->loadExtensionTables()
	->initializeSpriteManager(TRUE)
	->initializeBackendUser()
	->initializeBackendUserMounts()
	->initializeLanguageObject();

	// Compression
ob_clean();
if (extension_loaded('zlib') && $TYPO3_CONF_VARS['BE']['compressionLevel']) {
	if (t3lib_utility_Math::canBeInterpretedAsInteger($TYPO3_CONF_VARS['BE']['compressionLevel'])) {
		@ini_set('zlib.output_compression_level', $TYPO3_CONF_VARS['BE']['compressionLevel']);
	}
	ob_start('ob_gzhandler');
}


// ******************************
// The template is loaded
// ******************************
$GLOBALS['TBE_TEMPLATE'] = t3lib_div::makeInstance('template');


?>