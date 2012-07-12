<?php
/***************************************************************
 * Copyright notice
 *
 * (c) 2011 Susanne Moog <typo3@susanne-moog.de>
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the textfile GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class with helper functions for array handling
 *
 * @author Susanne Moog <typo3@susanne-moog.de>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_utility_Array {

	/**
	 * Reduce an array by a search value and keep the array structure.
	 *
	 * Comparison is type strict:
	 * - For a given needle of type string, integer, array or boolean,
	 * value and value type must match to occur in result array
	 * - For a given object, a object within the array must be a reference to
	 * the same object to match (not just different instance of same class)
	 *
	 * Example:
	 * - Needle: 'findMe'
	 * - Given array:
	 * 	array(
	 * 		'foo' => 'noMatch',
	 * 		'bar' => 'findMe',
	 * 		'foobar => array(
	 * 			'foo' => 'findMe',
	 * 		),
	 * 	);
	 * - Result:
	 * 	array(
	 * 		'bar' => 'findMe',
	 * 		'foobar' => array(
	 * 			'foo' => findMe',
	 * 		),
	 * 	);
	 *
	 * See the unit tests for more examples and expected behaviour
	 *
	 * @param mixed $needle The value to search for
	 * @param array $haystack The array in which to search
	 * @return array $haystack array reduced matching $needle values
	 */
	public static function filterByValueRecursive($needle = '', array $haystack = array()) {
		$resultArray = array();

			// Define a lambda function to be applied to all members of this array dimension
			// Call recursive if current value is of type array
			// Write to $resultArray (by reference!) if types and value match
		$callback = function(&$value, $key) use ($needle, &$resultArray) {
			if ($value === $needle) {
				$resultArray[$key] = $value;
			} elseif (is_array($value)) {
					// self does not work in lambda functions, use t3lib_utility_Array for recursion
				$subArrayMatches = t3lib_utility_Array::filterByValueRecursive($needle, $value);
				if (count($subArrayMatches) > 0) {
					$resultArray[$key] = $subArrayMatches;
				}
			}
		};

			// array_walk() is not affected by the internal pointers, no need to reset
		array_walk($haystack, $callback);

			// Pointers to result array are reset internally
		return $resultArray;
	}

	/**
	 * Check if a given path exists in array
	 *
	 * array:
	 * array(
	 * 	'foo' => array(
	 * 		'bar' = 'test',
	 * 	)
	 * );
	 * path: 'foo/bar'
	 * return: TRUE
	 *
	 * @param array $array Given array
	 * @param string $path Path to test, 'foo/bar/foobar'
	 * @param string $delimiter Delimeter for path, default /
	 * @return bool True if path exists in array
	 * @throws RuntimeException
	 */
	public static function isValidPath(array $array, $path,  $delimiter = '/') {
		$isValid = TRUE;

		try {
				// Use late static binding to enable mocking of this call in unit tests
			static::getValueByPath(
				$array,
				$path,
				$delimiter
			);
		} catch(RuntimeException $e) {
			$isValid = FALSE;
		}
		return $isValid;
	}

	/**
	 * Returns a value by given path
	 *
	 * Simple example
	 * Input array:
	 * array(
	 * 	'foo' => array(
	 * 		'bar' => array(
	 * 			'baz' => 42
	 * 		)
	 * 	)
	 * );
	 * Path to get: foo/bar/baz
	 * Will return: 42
	 *
	 * If a path segments contains a delimeter character, the path segment
	 * must be enclodsed by " (doubletick), see unit tests for details
	 *
	 * @param array $array Input array
	 * @param string $path Path within the array
	 * @param string $delimiter Defined path delimeter, default /
	 * @return mixed
	 * @throws RuntimeException
	 */
	public static function getValueByPath(array $array, $path,  $delimiter = '/') {
		if (empty($path)) {
			throw new RuntimeException(
				'Path must not be empty',
				1341397767
			);
		}

			// Extract parts of the path
		$path = str_getcsv($path, $delimiter);

			// Loop through each part and extract its value
		$value = $array;
		foreach ($path as $segment) {
			if (array_key_exists($segment, $value)) {
					// Replace current value with child
				$value = $value[$segment];
			} else {
					// Fail if key does not exist
				throw new RuntimeException(
					'Path does not exist is array',
					1341397869
				);
			}
		}

		return $value;
	}

	/**
	 *
	 *
	 * @static
	 * @param array $array
	 * @param string $path
	 * @param mixed $value
	 * @param string $delimiter
	 * @return array
	 * @throws RuntimeException
	 */
	public static function setValueByPath(array $array, $path, $value, $delimiter = '/') {
			// fail if the path is empty
		if (empty($path)) {
			throw new RuntimeException(
				'Path cannot be empty',
				1341406194
			);
		}

			// fail if path is not a string
		if (is_string($path) === FALSE) {
			throw new RuntimeException(
				'Path must be a string',
				1341406402
			);
		}

			// split the path in into separate segments
		$path = t3lib_div::trimExplode($delimiter, $path);

			// initially point to the root of the array
		$pointer =& $array;

			// loop through each segment and ensure that the cell is there
		foreach ($path as $segment) {

				// fail if the part is empty
			if (empty($segment)) {
				throw new RuntimeException(
					'Invalid path specified: ' . $path,
					1341406846
				);
			}

				// create the cell if it doesn't exist
			if (isset($pointer[$segment]) === FALSE) {
				$pointer[$segment] = array();
			}

				// redirect the pointer to the new cell
			$pointer =& $pointer[$segment];
		}

			// set value of the target cell
		$pointer = $value;

		return $array;
	}

	/**
	 * @static
	 * @param $array
	 */
	public static function sortByKeyRecursive(&$array) {
		ksort($array);
		foreach ($array as &$entry) {
			if (is_array($entry) && !empty($entry)) {
				self::sortByKeyRecursive($entry);
			}
		}
	}
}

?>