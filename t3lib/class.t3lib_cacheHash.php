<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Daniel Pötzinger (poetzinger@aoemedia.de)
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
 * Logic for cHash calculation
 *
 * @author Daniel Pötzinger <poetzinger@aoemedia.de>
 * @coauthor Tolleiv Nietsch <typo3@tolleiv.de>
 */
class t3lib_cacheHash implements t3lib_Singleton {

	/**
	 * Calculates the cHash based on the provided parameters
	 *
	 * @param array $params Array of cHash key-value pairs
	 * @return string Hash of all the values
	 */
	public static function calculateCacheHash(array $params) {
		$sanitizedParams = array();
		if (!empty($params)) {
			foreach ($params as $key => $value) {
				$sanitizedParams[self::sanitizeParameterKey($key)] = self::sanitizeParameterValue($value);
			}
		}
		return !empty($params) ? md5(serialize($sanitizedParams)) : '';
	}

	/**
	 * Returns the cHash based on provided query parameters and added values from internal call
	 *
	 * @param string $parameterString Query-parameters: "&xxx=yyy&zzz=uuu"
	 * @return string Hash of all the values
	 * @see t3lib_div::cHashParams(), t3lib_div::calculateCHash()
	 */
	public static function generateForParameters($parameterString) {
		$cacheHashParams = self::getRelevantParameters($parameterString);
		return self::calculateCacheHash($cacheHashParams);
	}

	/**
	 * Checks whether a parameter of the given $queryString requires cHash calculation
	 * @param string $parameterString
	 * @return boolean
	 */
	public static function parametersRequireCacheHash($parameterString) {
		if (empty($GLOBALS['TYPO3_CONF_VARS']['FE']['cHashRequiredParameters'])) {
			return FALSE;
		}
		$hasRequiredParameter = FALSE;
			// Split parameters up, remove leading ?
		$parameters = explode('&', ltrim($parameterString, '?'));
		foreach ($parameters as $parameterString) {
			list($parameterName) = explode('=', $parameterString);
			if (self::isRequiredParameter($parameterName)) {
				$hasRequiredParameter = TRUE;
			}
		}
		return $hasRequiredParameter;
	}

	/**
	 * Splits the input query-parameters into an array with certain parameters filtered out.
	 * Used to create the cHash value
	 *
	 * @param string $parameterString Query-parameters: "&xxx=yyy&zzz=uuu"
	 * @return array Array with key/value pairs of query-parameters WITHOUT a certain list of
	 *		 variable names (like id, type, no_cache etc.) and WITH a variable, encryptionKey, specific
	 *		 for this server/installation
	 * @see tslib_fe::makeCacheHash(), tslib_cObj::typoLink(), t3lib_div::calculateCHash()
	 */
	public static function getRelevantParameters($parameterString) {
			// Split parameters up, remove leading ?
		$parameters = array_filter(explode('&', ltrim($parameterString, '?')));
			// Make array:
		$relevantParameters = array();
		foreach ($parameters as $parameterString) {
				// Split single param by '=' sign
			list($parameterName, $parameterValue) = explode('=', $parameterString);
			if (self::isAdminPanelParameter($parameterName)
					|| self::isExcludedParameter($parameterName)
					|| self::isCoreParameter($parameterName)
			) {
				continue;
			}
			if (self::hasCachedParametersWhiteList() && !self::isInCachedParametersWhiteList($parameterName)) {
				continue;
			}

			$relevantParameters[rawurldecode($parameterName)] = (string) rawurldecode($parameterValue);
		}

		if (!empty($relevantParameters)) {
				// Finish and sort parameters array by keys:
			$relevantParameters['encryptionKey'] = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
			ksort($relevantParameters);
		}

		return $relevantParameters;
	}

	/**
	 * Checks whether the given parameter starts with TSFE_ADMIN_PANEL
	 * stristr check added to avoid bad performance
	 *
	 * @param string $key
	 * @return boolean
	 */
	protected function isAdminPanelParameter($key) {
		return stristr($key, 'TSFE_ADMIN_PANEL') !== FALSE && preg_match('/TSFE_ADMIN_PANEL\[.*?\]/', $key);
	}

	/**
	 * Checks whether the given parameter is a core parameter
	 * @param string $key
	 * @return boolean
	 */
	protected function isCoreParameter($key) {
		return t3lib_div::inList('id,type,no_cache,cHash,MP,ftu', $key);
	}

	/**
	 * Checks whether the given paramter requires cHash calculation
	 *
	 * @param string $key
	 * @return boolean
	 */
	protected function isRequiredParameter($key) {
		return t3lib_div::inList($GLOBALS['TYPO3_CONF_VARS']['FE']['cHashRequiredParameters'], $key);
	}

	/**
	 * Checks whether the given parameter should be exluded from cHash calculation
	 *
	 * @param string $key
	 * @return boolean
	 */
	protected function isExcludedParameter($key) {
		return t3lib_div::inList($GLOBALS['TYPO3_CONF_VARS']['FE']['cHashExcludedParameters'], $key);
	}

	/**
	 * Checks whether the given parameter is an exclusive parameter for cHash calculation
	 *
	 * @param string $key
	 * @return boolean
	 */
	protected function isInCachedParametersWhiteList($key) {
		return t3lib_div::inList($GLOBALS['TYPO3_CONF_VARS']['FE']['cHashOnlyForParameters'], $key);
	}

	/**
	 * Checks whether cachedParametersWhiteList parameters are configured
	 *
	 * @return boolean
	 */
	protected function hasCachedParametersWhiteList() {
		return !empty($GLOBALS['TYPO3_CONF_VARS']['FE']['cHashOnlyForParameters']);
	}

	/**
	 * @static
	 * @param $key
	 * @return mixed
	 */
	protected static function sanitizeParameterKey($key) {
		return $key;
	}

	/**
	 * @static
	 * @param $value
	 * @return mixed
	 */
	protected static function sanitizeParameterValue($value) {
		return $value;
	}
}

?>