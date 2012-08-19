<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Sebastian Fischer <typo3@evoweb.de>
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


/**
 * Model to contain all information from the form requesting to update translations
 *
 * @author Sebastian Fischer <typo3@evoweb.de>
 * @package lang
 * @subpackage UpdateTranslationForm
 */
class Tx_Lang_Domain_Model_UpdateTranslationForm extends Tx_Extbase_DomainObject_AbstractEntity {
	/**
	 * @var array
	 */
	protected $selectedLanguages = array();

	/**
	 * @var array
	 */
	protected $extensions = array();

	/**
	 * @param array $extensions
	 * @return void
	 */
	public function setExtensions($extensions) {
		$this->extensions = $extensions;
	}

	/**
	 * @return array
	 */
	public function getExtensions() {
		return $this->extensions;
	}

	/**
	 * @param array $selectedLanguages
	 * @return void
	 */
	public function setSelectedLanguages($selectedLanguages) {
		$this->selectedLanguages = $selectedLanguages;
	}

	/**
	 * @return array
	 */
	public function getSelectedLanguages() {
		return $this->selectedLanguages;
	}
}

?>