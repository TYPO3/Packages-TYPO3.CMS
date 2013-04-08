<?php
namespace TYPO3\CMS\Install\Package;

/***************************************************************
*  Copyright notice
*
*  (c) 2013 Thomas Maroschik <tmaroschik@dfau.de>
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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This is an intermediate package manager that loads just
 * the required extensions for the install tool to work
 */
class PackageStatesUnavailablePackageManager extends \TYPO3\CMS\Core\Package\PackageManager {

	/**
	 * @var \TYPO3\CMS\Core\Configuration\ConfigurationManager
	 */
	protected $configurationManager;

	/**
	 * @param \TYPO3\CMS\Core\Configuration\ConfigurationManager $configurationManager
	 */
	public function __construct(\TYPO3\CMS\Core\Configuration\ConfigurationManager $configurationManager) {
		$this->configurationManager = $configurationManager;
		parent::__construct();
	}

	/**
	 * Loads the states of available packages from the PackageStates.php file.
	 * The result is stored in $this->packageStatesConfiguration.
	 *
	 * @return void
	 */
	protected function loadPackageStates() {
		$this->packageStatesConfiguration = array();
		$this->scanAvailablePackages();
	}

	/**
	 * Requires and registers all packages which were defined in packageStatesConfiguration
	 *
	 * @return void
	 * @throws \TYPO3\Flow\Package\Exception\CorruptPackageException
	 */
	protected function registerPackagesFromConfiguration() {
		$this->packageStatesConfiguration['packages']['install']['state'] = 'active';
		parent::registerPackagesFromConfiguration();
	}
}