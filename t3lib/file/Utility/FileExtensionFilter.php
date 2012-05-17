<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Ingmar Schlecht <ingmar.schlecht@typo3.org>
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
 * Utility methods for filtering filenames
 *
 * @author Ingmar Schlecht <ingmar.schlecht@typo3.org>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_file_Utility_FileExtensionFilter {

	/**
	 * Allowed file extensions
	 *
	 * @var array
	 */
	protected $allowedFileExtensions = NULL;

	/**
	 * Disallowed file extensions
	 *
	 * @var array
	 */
	protected $disallowedFileExtensions = NULL;

	/**
	 * Entry method for use as TCEMain "inline" field filter
	 *
	 * @param $parameters
	 * @param $tceMain
	 * @return array
	 */
	public function filterInlineChildren($parameters, t3lib_TCEmain $tceMain) {
		$values = $parameters['values'];

		if ($parameters['allowedFileExtensions']) {
			$this->setAllowedFileExtensions($parameters['allowedFileExtensions']);
		}

		if ($parameters['disallowedFileExtensions']) {
			$this->setDisallowedFileExtensions($parameters['disallowedFileExtensions']);
		}

		$cleanValues = array();

		foreach ($values as $value) {
			$parts = t3lib_div::revExplode('_', $value, 2);
			$fileReferenceUid = $parts[count($parts)-1];

			$fileReferenceRecord = t3lib_BEfunc::getRecord('sys_file_reference', $fileReferenceUid);
			$fileUid = $fileReferenceRecord['uid_local'];

			$file = t3lib_file_Factory::getInstance()->getFileObject($fileUid);

			if ($this->isAllowed($file)) {
				$cleanValues[] = $value;
			} else {
					// Remove the erroneously created reference record again
				$tceMain->deleteAction('sys_file_reference', $fileReferenceUid);
			}
		}

		return $cleanValues;
	}

	/**
	 * Entry method for use as file list filter.
	 *
	 * We have to use -1 as the „don't include“ return value, as call_user_func() will return FALSE
	 * if calling the method failed and thus we can't use that as a return value.
	 *
	 * @param string $itemName
	 * @param string $itemIdentifier
	 * @param string $parentIdentifier
	 * @param t3lib_file_Driver_AbstractDriver $driver
	 * @return boolean|integer -1 if the file should not be included in a listing
	 */
	public function filterFileList($itemName, $itemIdentifier, $parentIdentifier, t3lib_file_Driver_AbstractDriver $driver) {
		$returnCode = TRUE;

			// Check that this is a file and not a folder
		if ($driver->fileExists($itemIdentifier)) {
			$file = $driver->getFile($itemIdentifier);

			if (!$this->isAllowed($file)) {
				$returnCode = -1;
			}
		}

		return $returnCode;
	}

	/**
	 * Checks whether a file is allowed according to the criteria defined in the class variables ($this->allowedFileExtensions etc.)
	 *
	 * @param t3lib_file_FileInterface $file
	 * @return bool
	 */
	protected function isAllowed(t3lib_file_FileInterface $file) {
		$result = TRUE;

		$fileExt = $file->getExtension();

			// Check allowed file extensions
		if ($this->allowedFileExtensions !== NULL && count($this->allowedFileExtensions) > 0 && !in_array($fileExt, $this->allowedFileExtensions)) {
			$result = FALSE;
		}

			// Check disallowed file extensions
		if ($this->disallowedFileExtensions !== NULL && count($this->disallowedFileExtensions) > 0 && in_array($fileExt, $this->disallowedFileExtensions)) {

			$result = FALSE;
		}

		return $result;
	}


	/**
	 * Allowed file extensions
	 *
	 * @param mixed $allowedFileExtensions  Comma-separated list or array, of allowed file extensions
	 */
	public function setAllowedFileExtensions($allowedFileExtensions) {
		$this->allowedFileExtensions = $this->convertToArray($allowedFileExtensions);
	}


	/**
	 * Allowed file extensions
	 *
	 * @param mixed $disallowedFileExtensions  Comma-separated list or array, of allowed file extensions
	 */
	public function setDisallowedFileExtensions($disallowedFileExtensions) {
		$this->disallowedFileExtensions = $this->convertToArray($disallowedFileExtensions);
	}

	/**
	 * Converts mixed (string or array) input arguments into an array, NULL if empty.
	 *
	 * @param mixed $inputArgument Comma-separated list or array.
	 * @return array
	 */
	protected function convertToArray($inputArgument) {
		$returnValue = NULL;

		if (is_array($inputArgument)) {
			$returnValue = $inputArgument;
		} elseif (strlen($inputArgument) > 0) {
			$returnValue = t3lib_div::trimExplode(',', $inputArgument);
		}

		return $returnValue;
	}
}

?>