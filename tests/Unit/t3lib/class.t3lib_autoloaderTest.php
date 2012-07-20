<?php
/***************************************************************
 * Copyright notice
 *
 * (c) 2011 Andreas Wolf <andreas.wolf@ikt-werk.de>
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
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Testcase for the t3lib_autoloader class in the TYPO3 Core.
 *
 * @package TYPO3
 * @subpackage t3lib
 *
 * @author Andreas Wolf <andreas.wolf@ikt-werk.de>
 */
class t3lib_autoloaderTest extends Tx_Phpunit_TestCase {

	/**
	 * @var boolean Enable backup of global and system variables
	 */
	protected $backupGlobals = TRUE;

	/**
	 * Exclude TYPO3_DB from backup/ restore of $GLOBALS
	 * because resource types cannot be handled during serializing
	 *
	 * @var array
	 */
	protected $backupGlobalsBlacklist = array('TYPO3_DB');

	/**
	 * @var array Backup of typo3CacheManager
	 */
	protected $typo3CacheManager = NULL;

	/**
	 * @var array Register of temporary extensions in typo3temp
	 */
	protected $fakedExtensions = array();

	/**
	 * Fix a race condition that t3lib_div is not available
	 * during tearDown if fiddling with the autoloader where
	 * backupGlobals is not set up again yet
	 */
	public function setUp() {
		$this->typo3CacheManager = $GLOBALS['typo3CacheManager'];
	}

	/**
	 * Clean up
	 * Warning: Since phpunit itself is php and we are fiddling with php
	 * autoloader code here, the tests are a bit fragile. This tearDown
	 * method ensures that all main classes are available again during
	 * tear down of a testcase.
	 * This construct will fail if the class under test is changed and
	 * not compatible anymore. Make sure to always run the whole test
	 * suite if fiddling with the autoloader unit tests to ensure that
	 * there is no fatal error thrown in other unit test classes triggered
	 * by errors in this one.
	 */
	public function tearDown() {
		$GLOBALS['typo3CacheManager'] = $this->typo3CacheManager;
		t3lib_autoloader::unregisterAutoloader();
		t3lib_autoloader::registerAutoloader();
		foreach ($this->fakedExtensions as $extension) {
			t3lib_div::rmdir(PATH_site . 'typo3temp/' . $extension, TRUE);
		}
	}

	/**
	 * Creates a fake extension inside typo3temp/. No configuration is created,
	 * just the folder, plus the extension is registered in $TYPO3_LOADED_EXT
	 *
	 * @return string The extension key
	 */
	protected function createFakeExtension() {
		$extKey = strtolower(uniqid('testing'));
		$absExtPath = PATH_site . 'typo3temp/' . $extKey . '/';
		$relPath = 'typo3temp/' . $extKey . '/';
		t3lib_div::mkdir($absExtPath);

		$GLOBALS['TYPO3_LOADED_EXT'][$extKey] = array(
			'siteRelPath' => $relPath
		);
		$GLOBALS['TYPO3_CONF_VARS']['EXT']['extListArray'][] = $extKey;

		$this->fakedExtensions[] = $extKey;
		t3lib_extMgm::clearExtensionKeyMap();

		return $extKey;
	}

	/**
	 * @test
	 */
	public function unregisterAndRegisterAgainDoesNotFatal() {
		t3lib_autoloader::unregisterAutoloader();
		t3lib_autoloader::registerAutoloader();
			// If this fatals the autoload re registering went wrong
		t3lib_div::makeInstance('t3lib_timetracknull');
	}

	/**
	 * @test
	 */
	public function unregisterAutoloaderSetsCacheEntryWithT3libAutoloaderAndCoreTag() {
		$mockCache = $this->getMock('t3lib_cache_frontend_AbstractFrontend', array('getIdentifier', 'set', 'get', 'getByTag', 'has', 'remove', 'flush', 'flushByTag', 'requireOnce'), array(), '', FALSE);
			// Expect the mock cache set method to be called
			// once with t3lib_autoloader as third parameter
		$mockCache->expects($this->once())->method('set')
			->with($this->anything(), $this->anything(), array('t3lib_autoloader', 'core'));
		$GLOBALS['typo3CacheManager'] = $this->getMock('t3lib_cache_Manager', array('getCache'));
		$GLOBALS['typo3CacheManager']->expects($this->any())->method('getCache')->will($this->returnValue($mockCache));
		t3lib_autoloader::unregisterAutoloader();
	}


	/**
	 * @test
	 */
	public function autoloadFindsClassFileDefinedInExtAutoloadFile() {
		$extKey = $this->createFakeExtension();
		$extPath = PATH_site . 'typo3temp/' . $extKey . '/';
		$autoloaderFile = $extPath . "ext_autoload.php";

		$class = strtolower("tx_${extKey}_" . uniqid(''));
		$file = $extPath . uniqid('') . '.php';

		file_put_contents($file, "<?php\n\nthrow new RuntimeException('', 1310203812);\n\n?>");
		file_put_contents($autoloaderFile, "<?php\n\nreturn array('" . $class. "' => '" . $file . "');\n\n?>");

			// Inject a dummy for the core_phpcode cache to force the autoloader
			// to re calculate the registry
		$mockCache = $this->getMock('t3lib_cache_frontend_AbstractFrontend', array('getIdentifier', 'set', 'get', 'getByTag', 'has', 'remove', 'flush', 'flushByTag', 'requireOnce'), array(), '', FALSE);
		$GLOBALS['typo3CacheManager'] = $this->getMock('t3lib_cache_Manager', array('getCache'));
		$GLOBALS['typo3CacheManager']->expects($this->any())->method('getCache')->will($this->returnValue($mockCache));

			// Re-initialize autoloader registry to force it to recognize the new extension
		t3lib_autoloader::unregisterAutoloader();
		t3lib_autoloader::registerAutoloader();

			// Expect the exception of the file to be thrown
		$this->setExpectedException('RuntimeException', '', 1310203812);
		t3lib_autoloader::autoload($class);
	}

	/**
	 * @test
	 */
	public function unregisterAutoloaderWritesLowerCasedClassFileToCache() {
		$extKey = $this->createFakeExtension();
		$extPath = PATH_site . 'typo3temp/' . $extKey . '/';
		$autoloaderFile = $extPath . "ext_autoload.php";

			// A case sensitive key (FooBar) in ext_autoload file
		$class = "tx_${extKey}_" . uniqid('FooBar');
		$file = $extPath . uniqid('') . '.php';

		file_put_contents($autoloaderFile, "<?php\n\nreturn array('" . $class . "' => '" . $file . "');\n\n?>");

			// Inject a dummy for the core_phpcode cache to force the autoloader
			// to re calculate the registry
		$mockCache = $this->getMock('t3lib_cache_frontend_AbstractFrontend', array('getIdentifier', 'set', 'get', 'getByTag', 'has', 'remove', 'flush', 'flushByTag', 'requireOnce'), array(), '', FALSE);
		$GLOBALS['typo3CacheManager'] = $this->getMock('t3lib_cache_Manager', array('getCache'));
		$GLOBALS['typo3CacheManager']->expects($this->any())->method('getCache')->will($this->returnValue($mockCache));

			// Expect that the lower case version of the class name is written to cache
		$mockCache->expects($this->at(2))->method('set')->with($this->anything(), $this->stringContains(strtolower($class), FALSE));

			// Re-initialize autoloader registry to force it to recognize the new extension
		t3lib_autoloader::unregisterAutoloader();
		t3lib_autoloader::registerAutoloader();
		t3lib_autoloader::unregisterAutoloader();
	}

	/**
	 * @test
	 * @expectedException RuntimeException
	 */
	public function autoloadFindsClassFileIfExtAutoloadEntryIsCamelCased() {
		$extKey = $this->createFakeExtension();
		$extPath = PATH_site . 'typo3temp/' . $extKey . '/';

			// A case sensitive key (FooBar) in ext_autoload file
		$class = "tx_${extKey}_" . uniqid('FooBar');

		$file = $extPath . uniqid('') . '.php';
		file_put_contents($file, "<?php\n\nthrow new RuntimeException('', 1336756850);\n\n?>");

		$extAutoloadFile = $extPath . 'ext_autoload.php';
		file_put_contents($extAutoloadFile, "<?php\n\nreturn array('" . $class . "' => '" . $file . "');\n\n?>");

			// Inject cache and return false, so autoloader is forced to read ext_autoloads from extensions
		$mockCache = $this->getMock('t3lib_cache_frontend_AbstractFrontend', array('getIdentifier', 'set', 'get', 'getByTag', 'has', 'remove', 'flush', 'flushByTag', 'requireOnce'), array(), '', FALSE);
		$GLOBALS['typo3CacheManager'] = $this->getMock('t3lib_cache_Manager', array('getCache'));
		$GLOBALS['typo3CacheManager']->expects($this->any())->method('getCache')->will($this->returnValue($mockCache));
		$mockCache->expects($this->any())->method('has')->will($this->returnValue(FALSE));

			// Re-initialize autoloader registry to force it to recognize the new extension
		t3lib_autoloader::unregisterAutoloader();
		t3lib_autoloader::registerAutoloader();
		t3lib_autoloader::autoload($class);
	}

	/**
	 * @test
	 * @expectedException RuntimeException
	 */
	public function autoloadFindsCamelCasedClassFileIfExtAutoloadEntryIsReadLowerCasedFromCache() {
		$extKey = $this->createFakeExtension();
		$extPath = PATH_site . 'typo3temp/' . $extKey . '/';

			// A case sensitive key (FooBar) in ext_autoload file
		$class = "tx_${extKey}_" . uniqid('FooBar');
		$file = $extPath . uniqid('') . '.php';

		file_put_contents($file, "<?php\n\nthrow new RuntimeException('', 1336756850);\n\n?>");

			// Inject cache mock and let the cache entry return the lowercased class name as key
		$mockCache = $this->getMock('t3lib_cache_frontend_AbstractFrontend', array('getIdentifier', 'set', 'get', 'getByTag', 'has', 'remove', 'flush', 'flushByTag', 'requireOnce'), array(), '', FALSE);
		$GLOBALS['typo3CacheManager'] = $this->getMock('t3lib_cache_Manager', array('getCache'));
		$GLOBALS['typo3CacheManager']->expects($this->any())->method('getCache')->will($this->returnValue($mockCache));
		$mockCache->expects($this->any())->method('has')->will($this->returnValue(TRUE));
		$mockCache->expects($this->once())->method('requireOnce')->will($this->returnValue(array(strtolower($class) => $file)));

			// Re-initialize autoloader registry to force it to recognize the new extension
		t3lib_autoloader::unregisterAutoloader();
		t3lib_autoloader::registerAutoloader();
		t3lib_autoloader::autoload($class);
	}

	/**
	 * @test
	 */
	public function autoloadFindsClassFileThatRespectsExtbaseNamingSchemeWithoutExtAutoloadFile() {
		$extKey = $this->createFakeExtension();
		$extPath = PATH_site . 'typo3temp/' . $extKey . '/';

			// Create a class named Tx_Extension_Foo123_Bar456
			// to find file extension/Classes/Foo123/Bar456.php
		$pathSegment = 'Foo' . uniqid();
		$fileName = 'Bar' . uniqid();
		$class = 'Tx_' . $extKey . '_' . $pathSegment . '_' . $fileName;
		$file = $extPath . 'Classes/' . $pathSegment . '/' . $fileName . '.php';

		t3lib_div::mkdir_deep($extPath . 'Classes/' . $pathSegment);
		file_put_contents($file, "<?php\n\nthrow new RuntimeException('', 1310203813);\n\n?>");

			// Inject a dummy for the core_phpcode cache to cache
			// the calculated cache entry to a dummy cache
		$mockCache = $this->getMock('t3lib_cache_frontend_AbstractFrontend', array('getIdentifier', 'set', 'get', 'getByTag', 'has', 'remove', 'flush', 'flushByTag', 'requireOnce'), array(), '', FALSE);
		$GLOBALS['typo3CacheManager'] = $this->getMock('t3lib_cache_Manager', array('getCache'));
		$GLOBALS['typo3CacheManager']->expects($this->any())->method('getCache')->will($this->returnValue($mockCache));

			// Expect the exception of the file to be thrown
		$this->setExpectedException('RuntimeException', '', 1310203813);
		t3lib_autoloader::autoload($class);
	}

	/**
	 * @test
	 */
	public function unregisterAutoloaderWritesClassFileThatRespectsExtbaseNamingSchemeToCacheFile() {
		$extKey = $this->createFakeExtension();
		$extPath = PATH_site . 'typo3temp/' . $extKey . '/';

		$pathSegment = 'Foo' . uniqid();
		$fileName = 'Bar' . uniqid();
		$class = 'Tx_' . $extKey . '_' . $pathSegment . '_' . $fileName;
		$file = $extPath . 'Classes/' . $pathSegment . '/' . $fileName . '.php';

		t3lib_div::mkdir_deep($extPath . 'Classes/' . $pathSegment);
		file_put_contents($file, "<?php\n\n\$foo = 'bar';\n\n?>");

		$mockCache = $this->getMock('t3lib_cache_frontend_AbstractFrontend', array('getIdentifier', 'set', 'get', 'getByTag', 'has', 'remove', 'flush', 'flushByTag', 'requireOnce'), array(), '', FALSE);
		$GLOBALS['typo3CacheManager'] = $this->getMock('t3lib_cache_Manager', array('getCache'));
		$GLOBALS['typo3CacheManager']->expects($this->any())->method('getCache')->will($this->returnValue($mockCache));

			// Expect that an entry to the cache is written containing the newly found class
		$mockCache->expects($this->once())->method('set')->with($this->anything(), $this->stringContains(strtolower($class), $this->anything()));

		t3lib_autoloader::autoload($class);
		t3lib_autoloader::unregisterAutoloader();
	}

	/**
	 * @test
	 */
	public function unregisterAutoloaderWritesClassFileLocationOfClassRespectingExtbaseNamingSchemeToCacheFile() {
		$extKey = $this->createFakeExtension();
		$extPath = PATH_site . 'typo3temp/' . $extKey . '/';

		$pathSegment = 'Foo' . uniqid();
		$fileName = 'Bar' . uniqid();
		$class = 'Tx_' . $extKey . '_' . $pathSegment . '_' . $fileName;
		$file = $extPath . 'Classes/' . $pathSegment . '/' . $fileName . '.php';

		t3lib_div::mkdir_deep($extPath . 'Classes/' . $pathSegment);
		file_put_contents($file, "<?php\n\n\$foo = 'bar';\n\n?>");

		$mockCache = $this->getMock('t3lib_cache_frontend_AbstractFrontend', array('getIdentifier', 'set', 'get', 'getByTag', 'has', 'remove', 'flush', 'flushByTag', 'requireOnce'), array(), '', FALSE);
		$GLOBALS['typo3CacheManager'] = $this->getMock('t3lib_cache_Manager', array('getCache'));
		$GLOBALS['typo3CacheManager']->expects($this->any())->method('getCache')->will($this->returnValue($mockCache));

			// Expect that an entry to the cache is written containing the newly found class
		$mockCache->expects($this->once())->method('set')->with($this->anything(), $this->stringContains(strtolower($file), $this->anything()));

		t3lib_autoloader::autoload($class);
		t3lib_autoloader::unregisterAutoloader();
	}

	/**
	 * @test
	 */
	public function getClassPathByRegistryLookupFindsClassPrefixedWithUxRegisteredInExtAutoloadFile() {
			// Create a dummy extension with a path to a 'ux_' prefixed php file
		$extKey = $this->createFakeExtension();
		$extPath = PATH_site . 'typo3temp/' . $extKey . '/';
		$autoloaderFile = $extPath . "ext_autoload.php";

		$class = strtolower("ux_tx_${extKey}_" . uniqid(''));
		$file = $extPath . uniqid('') . '.php';

		file_put_contents($autoloaderFile, "<?php\n\nreturn array('" . $class . "' => '" . $file . "');\n\n?>");

			// Inject a dummy for the core_phpcode cache to force the autoloader
			// to re calculate the registry
		$mockCache = $this->getMock('t3lib_cache_frontend_AbstractFrontend', array('getIdentifier', 'set', 'get', 'getByTag', 'has', 'remove', 'flush', 'flushByTag', 'requireOnce'), array(), '', FALSE);
		$GLOBALS['typo3CacheManager'] = $this->getMock('t3lib_cache_Manager', array('getCache'));
		$GLOBALS['typo3CacheManager']->expects($this->any())->method('getCache')->will($this->returnValue($mockCache));

			// Re-initialize autoloader registry to force it to recognize the new extension with the ux_ autoload definition
		t3lib_autoloader::unregisterAutoloader();
		t3lib_autoloader::registerAutoloader();

		$this->assertSame($file, t3lib_autoloader::getClassPathByRegistryLookup($class));
	}

	/**
	 * @test
	 */
	public function unregisterAutoloaderWritesNotExistingUxCLassLookupFromGetClassPathByRegistryLookupToCache() {
		$uxClassName = 'ux_Tx_Foo' . uniqid();

		$mockCache = $this->getMock('t3lib_cache_frontend_AbstractFrontend', array('getIdentifier', 'set', 'get', 'getByTag', 'has', 'remove', 'flush', 'flushByTag', 'requireOnce'), array(), '', FALSE);
		$GLOBALS['typo3CacheManager'] = $this->getMock('t3lib_cache_Manager', array('getCache'));
		$GLOBALS['typo3CacheManager']->expects($this->any())->method('getCache')->will($this->returnValue($mockCache));

		t3lib_autoloader::unregisterAutoloader();
		t3lib_autoloader::registerAutoloader();

			// Class is not found by returning NULL
		$this->assertSame(NULL, t3lib_autoloader::getClassPathByRegistryLookup($uxClassName));

			// Expect NULL lookup is cached
		$expectedCacheString = '\'' . strtolower($uxClassName) . '\' => NULL,';
		$mockCache->expects($this->once())->method('set')->with($this->anything(), $this->stringContains($expectedCacheString));

			// Trigger writing new cache file
		t3lib_autoloader::unregisterAutoloader();
	}

	/**
	 * @test
	 */
	public function unregisterAutoloaderWritesDeprecatedTypo3ConfVarsRegisteredXclassClassFoundByGetClassPathByRegistryLookupToCache() {
			// Create a fake extension
		$extKey = $this->createFakeExtension();
		$extPath = PATH_site . 'typo3temp/' . $extKey . '/';
		$autoloaderFile = $extPath . "ext_autoload.php";

			// Feed ext_autoload with a base file and the class file
		$class = strtolower("tx_${extKey}_" . uniqid(''));
		$fileName = uniqid('') . '.php';
		$file = $extPath . $fileName;
		$xClassFile = 'typo3temp/' . $extKey . '/xclassFile';
		file_put_contents($autoloaderFile, "<?php\n\nreturn array('" . $class . "' => '" . $file . "');\n\n?>");
		file_put_contents(PATH_site . $xClassFile, "<?php\n\ndie();\n\n?>");

			// Register a xclass for the base file
		$GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['typo3temp/' . $extKey . '/' . $fileName] = $xClassFile;

			// Inject a dummy for the core_phpcode cache to force the autoloader
			// to re calculate the registry
		$mockCache = $this->getMock('t3lib_cache_frontend_AbstractFrontend', array('getIdentifier', 'set', 'get', 'getByTag', 'has', 'remove', 'flush', 'flushByTag', 'requireOnce'), array(), '', FALSE);
		$GLOBALS['typo3CacheManager'] = $this->getMock('t3lib_cache_Manager', array('getCache'));
		$GLOBALS['typo3CacheManager']->expects($this->any())->method('getCache')->will($this->returnValue($mockCache));

			// Excpect the cache entry to be called once with the new class name
		$mockCache->expects($this->at(2))->method('set')->with($this->anything(), $this->stringContains('ux_' . $class));

		t3lib_autoloader::unregisterAutoloader();
		t3lib_autoloader::registerAutoloader();

		t3lib_autoloader::getClassPathByRegistryLookup('ux_' . $class);
		t3lib_autoloader::unregisterAutoloader();
	}

	/**
	 * @test
	 */
	public function autoloadFindsClassFileThatRespectsExtbaseNamingSchemeWithNamespaceWithoutExtAutoloadFile() {
		$extKey = $this->createFakeExtension();
		$extPath = PATH_site . 'typo3temp/' . $extKey . '/';

			// Create a class named Tx_Extension_Foo123_Bar456
			// to find file extension/Classes/Foo123/Bar456.php
		$pathSegment = 'Foo' . uniqid();
		$fileName = 'Bar' . uniqid();
		$namespacedClass = '\Tx\\' . $extKey . '\\' . $pathSegment . '\\' . $fileName;

		$file = $extPath . 'Classes/' . $pathSegment . '/' . $fileName . '.php';

		t3lib_div::mkdir_deep($extPath . 'Classes/' . $pathSegment);
		file_put_contents($file, "<?php".LF.
			"throw new RuntimeException('', 1342800577);".LF.
			"?>");

			// Re-initialize autoloader registry to force it to recognize the new extension
		t3lib_autoloader::unregisterAutoloader();
		t3lib_autoloader::registerAutoloader();

			// Expect the exception of the file to be thrown
		$this->setExpectedException('RuntimeException', '', 1342800577);
		t3lib_autoloader::autoload($namespacedClass);

	}


	/**
	 * @test
	 */
	public function unregisterAutoloaderWritesClassFileLocationOfClassRespectingExtbaseNamingSchemeWithNamespaceToCacheFile() {
		$extKey = $this->createFakeExtension();
		$extPath = PATH_site . 'typo3temp/' . $extKey . '/';

		$pathSegment = 'Foo' . uniqid();
		$fileName = 'Bar' . uniqid();
		$namespacedClass = '\Tx\\' . $extKey . '\\' . $pathSegment . '\\' . $fileName;
		$file = $extPath . 'Classes/' . $pathSegment . '/' . $fileName . '.php';

		t3lib_div::mkdir_deep($extPath . 'Classes/' . $pathSegment);
		file_put_contents($file, '<?php'.LF . $foo = 'bar;'.LF.'?>');

		$mockCache = $this->getMock('t3lib_cache_frontend_AbstractFrontend', array('getIdentifier', 'set', 'get', 'getByTag', 'has', 'remove', 'flush', 'flushByTag', 'requireOnce'), array(), '', FALSE);
		$GLOBALS['typo3CacheManager'] = $this->getMock('t3lib_cache_Manager', array('getCache'));
		$GLOBALS['typo3CacheManager']->expects($this->any())->method('getCache')->will($this->returnValue($mockCache));

			// Expect that an entry to the cache is written containing the newly found class
		$mockCache->expects($this->once())->method('set')->with($this->anything(), $this->stringContains(strtolower($file), $this->anything()));

		t3lib_autoloader::autoload($namespacedClass);
		t3lib_autoloader::unregisterAutoloader();
	}
}
?>