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
 * Language model
 *
 * @author Sebastian Fischer <typo3@evoweb.de>
 * @package lang
 * @subpackage Language
 */
class Tx_Lang_Domain_Model_Language extends Tx_Extbase_DomainObject_AbstractEntity {
	/**
	 * @var string
	 */
	protected $locale;

	/**
	 * @var string
	 */
	protected $language;

	/**
	 * @var boolean
	 */
	protected $selected;


	/**
	 * @param string $locale
	 * @param string $language
	 * @param boolean $selected
	 * @return Tx_Lang_Domain_Model_Language
	 */
	public function __construct($locale, $language, $selected) {
		$this->setLocale($locale);
		$this->setLanguage($language);
		$this->setSelected($selected);
	}


	/**
	 * @param string $lable
	 * @return void
	 */
	public function setLanguage($language) {
		$this->language = $language;
	}

	/**
	 * @return string
	 */
	public function getLanguage() {
		return $this->language;
	}

	/**
	 * @param string $locale
	 * @return void
	 */
	public function setLocale($locale) {
		$this->locale = $locale;
	}

	/**
	 * @return string
	 */
	public function getLocale() {
		return $this->locale;
	}

	/**
	 * @param boolean $selected
	 * @return void
	 */
	public function setSelected($selected) {
		$this->selected = $selected ? TRUE : FALSE;
	}

	/**
	 * @return boolean
	 */
	public function getSelected() {
		return $this->selected;
	}
}

?>