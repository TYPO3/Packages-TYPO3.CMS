<?php
namespace TYPO3\CMS\Core\Package;

use TYPO3\Flow\Annotations as Flow;

/**
 * The default TYPO3 Package Manager
 *
 * @api
 * @Flow\Scope("singleton")
 */
class PackageManager extends \TYPO3\Flow\Package\PackageManager implements \TYPO3\CMS\Core\SingletonInterface {


	/**
	 * @var \TYPO3\CMS\Core\Core\ClassLoader
	 */
	protected $classLoader;

	/**
	 * @var \TYPO3\CMS\Core\Core\Bootstrap
	 */
	protected $bootstrap;

	/**
	 * @var \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend
	 */
	protected $coreCache;

	/**
	 * @var string
	 */
	protected $cacheIdentifier;

	/**
	 * @var array
	 */
	protected $extAutoloadClassFiles;

	/**
	 * @var array
	 */
	protected $packagesBasePaths = array();

	/**
	 * @var array
	 */
	protected $packageAliasMap = array();

	/**
	 *
	 */
	public function __construct() {
		$this->packagesBasePaths = array(
			'local'     => PATH_typo3conf . 'ext',
			'global'    => PATH_typo3 . 'ext',
			'sysext'    => PATH_typo3 . 'sysext',
			'composer'  => PATH_typo3conf . 'Packages/Libraries',
		);
	}

	/**
	 * @param \TYPO3\CMS\Core\Core\ClassLoader $classLoader
	 */
	public function injectClassLoader(\TYPO3\CMS\Core\Core\ClassLoader $classLoader) {
		$this->classLoader = $classLoader;
	}

	/**
	 * @param \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend $coreCache
	 */
	public function injectCoreCache(\TYPO3\CMS\Core\Cache\Frontend\PhpFrontend $coreCache) {
		$this->coreCache = $coreCache;
	}

	/**
	 * Initializes the package manager
	 *
	 * @param \TYPO3\CMS\Core\Core\Bootstrap $bootstrap The current bootstrap
	 * @param string $packagesBasePath Absolute path of the Packages directory
	 * @param string $packageStatesPathAndFilename
	 * @return void
	 */
	public function initialize(\TYPO3\CMS\Core\Core\Bootstrap $bootstrap, $packagesBasePath = PATH_site, $packageStatesPathAndFilename = '') {

		$this->bootstrap = $bootstrap;
		$this->packagesBasePath = $packagesBasePath;
		$this->packageStatesPathAndFilename = ($packageStatesPathAndFilename === '') ? PATH_typo3conf . 'PackageStates.php' : $packageStatesPathAndFilename;
		$this->packageFactory = new PackageFactory($this);

		$this->loadPackageStates();

		$requiredList = array();
		foreach ($this->packages as $packageKey => $package) {
			$protected = $package->isProtected();
			if ($protected) {
				$requiredList[] = $packageKey;
			}
			if ($protected || (isset($this->packageStatesConfiguration['packages'][$packageKey]['state']) && $this->packageStatesConfiguration['packages'][$packageKey]['state'] === 'active')) {
				$this->activePackages[$packageKey] = $package;
			}
		}

		//@deprecated since 6.2, don't use
		if (!defined('REQUIRED_EXTENSIONS')) {
			// List of extensions required to run the core
			define('REQUIRED_EXTENSIONS', implode(',', $requiredList));
		}

		$cacheIdentifier = $this->getCacheIdentifier();
		if ($cacheIdentifier === NULL) {
			// Create an artificial cache identifier if the package states file is not available yet
			// in order that the class loader and class alias map can cache anyways.
			$cacheIdentifier = substr(md5(implode('###', array_keys($this->activePackages))), 0, 8);
		}
		$this->classLoader->setCacheIdentifier($cacheIdentifier)->setPackages($this->activePackages);

		foreach ($this->activePackages as $package) {
			$package->boot($bootstrap);
		}

		$this->saveToPackageCache();
	}

	/**
	 * @return string
	 */
	protected function getCacheIdentifier() {
		if ($this->cacheIdentifier === NULL) {
			if (file_exists($this->packageStatesPathAndFilename)) {
				$this->cacheIdentifier = substr(md5_file($this->packageStatesPathAndFilename), 0, 8);
			} else {
				$this->cacheIdentifier = NULL;
			}
		}
		return $this->cacheIdentifier;
	}

	/**
	 * @return string
	 */
	protected function getCacheEntryIdentifier() {
		$cacheIdentifier = $this->getCacheIdentifier();
		return $cacheIdentifier !== NULL ? 'PackageManager_' . $cacheIdentifier : NULL;
	}

	/**
	 *
	 */
	protected function saveToPackageCache() {
		$cacheEntryIdentifier = $this->getCacheEntryIdentifier();
		if ($cacheEntryIdentifier !== NULL && !$this->coreCache->has($cacheEntryIdentifier)) {
			// Look up cache entry file path
			$this->coreCache->set($cacheEntryIdentifier, 'return __FILE__;');
			$cacheEntryPath = $this->coreCache->requireOnce($cacheEntryIdentifier);
			// Build cache file
			$packageCache = array(
				'packageStatesConfiguration'  => $this->packageStatesConfiguration,
				'packageAliasMap' => $this->packageAliasMap,
				'packageKeys' => $this->packageKeys,
				'declaringPackageClassPathsAndFilenames' => array(),
				'packages' => serialize($this->packages)
			);
			foreach ($this->packages as $package) {
				if (!isset($packageCache['declaringPackageClassPathsAndFilenames'][$packageClassName = get_class($package)])) {
					$reflectionPackageClass = new \ReflectionClass($packageClassName);
					$packageCache['declaringPackageClassPathsAndFilenames'][$packageClassName] = $reflectionPackageClass->getFileName();
				}
			}
			$this->coreCache->set(
				$cacheEntryIdentifier,
				'return __FILE__ !== \'' . $cacheEntryPath . '\' ? FALSE : ' . PHP_EOL .
					var_export($packageCache, TRUE) . ';'
			);
		}
	}

	/**
	 * Loads the states of available packages from the PackageStates.php file.
	 * The result is stored in $this->packageStatesConfiguration.
	 *
	 * @return void
	 */
	protected function loadPackageStates() {
		$cacheEntryIdentifier = $this->getCacheEntryIdentifier();
		if ($cacheEntryIdentifier !== NULL && $this->coreCache->has($cacheEntryIdentifier) && $packageCache = $this->coreCache->requireOnce($cacheEntryIdentifier)) {
			foreach ($packageCache['declaringPackageClassPathsAndFilenames'] as $packageClassPathAndFilename) {
				require_once $packageClassPathAndFilename;
			}
			$this->packageStatesConfiguration = $packageCache['packageStatesConfiguration'];
			$this->packageAliasMap = $packageCache['packageAliasMap'];
			$this->packageKeys = $packageCache['packageKeys'];
			$GLOBALS['TYPO3_currentPackageManager'] = $this;
			$this->packages = unserialize($packageCache['packages']);
			unset($GLOBALS['TYPO3_currentPackageManager']);
		} else {
			$this->packageStatesConfiguration = @include($this->packageStatesPathAndFilename) ?: array();
			if (!isset($this->packageStatesConfiguration['version']) || $this->packageStatesConfiguration['version'] < 4) {
				$this->packageStatesConfiguration = array();
			}
			if ($this->packageStatesConfiguration === array()) {
				throw new Exception\PackageStatesUnavailableException('The package states file is not available. Please use the install tool and the package states upgrade wizard to create one.', 1362420232);
			} else {
				$this->registerPackagesFromConfiguration();
			}
		}
	}


	/**
	 * Scans all directories in the packages directories for available packages.
	 * For each package a Package object is created and stored in $this->packages.
	 *
	 * @return void
	 * @throws \TYPO3\Flow\Package\Exception\DuplicatePackageException
	 */
	protected function scanAvailablePackages() {
		if (isset($this->packageStatesConfiguration['packages'])) {
			foreach ($this->packageStatesConfiguration['packages'] as $packageKey => $configuration) {
				if (!file_exists($this->packagesBasePath . $configuration['packagePath'])) {
					unset($this->packageStatesConfiguration['packages'][$packageKey]);
				}
			}
		} else {
			$this->packageStatesConfiguration['packages'] = array();
		}

		foreach ($this->packagesBasePaths as $packagesBasePath) {
			if (!is_dir($packagesBasePath)) {
				\TYPO3\CMS\Core\Utility\GeneralUtility::mkdir_deep($packagesBasePath);
			}
		}

		$packagePaths = $this->scanLegacyExtensions();
		foreach ($this->packagesBasePaths as $packagesBasePath) {
			$this->scanPackagesInPath($packagesBasePath, $packagePaths);
		}

		foreach ($packagePaths as $packagePath => $composerManifestPath) {
			$packagesBasePath = PATH_site;
			foreach ($this->packagesBasePaths as $basePath) {
				if (strpos($packagePath, $basePath) === 0) {
					$packagesBasePath = $basePath;
					break;
				}
			}
			try {
				$composerManifest = self::getComposerManifest($composerManifestPath);
				$packageKey = \TYPO3\CMS\Core\Package\PackageFactory::getPackageKeyFromManifest($composerManifest, $packagePath, $packagesBasePath);
				$this->composerNameToPackageKeyMap[strtolower($composerManifest->name)] = $packageKey;
				$this->packageStatesConfiguration['packages'][$packageKey]['manifestPath'] = substr($composerManifestPath, strlen($packagePath)) ? : '';
				$this->packageStatesConfiguration['packages'][$packageKey]['composerName'] = $composerManifest->name;
			}
			catch (\TYPO3\Flow\Package\Exception\MissingPackageManifestException $exception) {
				$relativePackagePath = substr($packagePath, strlen($packagesBasePath));
				$packageKey = substr($relativePackagePath, strpos($relativePackagePath, '/') + 1, -1);
			}
			if (!isset($this->packageStatesConfiguration['packages'][$packageKey]['state'])) {
				$this->packageStatesConfiguration['packages'][$packageKey]['state'] = 'inactive';
			}

			$this->packageStatesConfiguration['packages'][$packageKey]['packagePath'] = str_replace($this->packagesBasePath, '', $packagePath);

			// Change this to read the target from Composer or any other source
			$this->packageStatesConfiguration['packages'][$packageKey]['classesPath'] = \TYPO3\Flow\Package\Package::DIRECTORY_CLASSES;
		}

		$this->registerPackagesFromConfiguration();
	}

	/**
	 * @return array
	 */
	protected function scanLegacyExtensions(&$collectedExtensionPaths = array()) {
		$legacyCmsPackageBasePathTypes = array('sysext', 'global', 'local');
		foreach ($this->packagesBasePaths as $type => $packageBasePath) {
			if (!in_array($type, $legacyCmsPackageBasePathTypes)) {
				continue;
			}
			/** @var $fileInfo \SplFileInfo */
			foreach (new \DirectoryIterator($packageBasePath) as $fileInfo) {
				if (!$fileInfo->isDir()) {
					continue;
				}
				$filename = $fileInfo->getFilename();
				if ($filename[0] !== '.') {
					$currentPath = \TYPO3\Flow\Utility\Files::getUnixStylePath($fileInfo->getPathName()) . '/';
					$collectedExtensionPaths[$currentPath] = $currentPath;
				}
			}
		}
		return $collectedExtensionPaths;
	}

	/**
	 * Register a native Flow package
	 *
	 * @param string $packageKey The Package to be registered
	 * @param boolean $sortAndSave allows for not saving packagestates when used in loops etc.
	 * @return \TYPO3\Flow\Package\PackageInterface
	 * @throws \TYPO3\Flow\Package\Exception\CorruptPackageException
	 */
	public function registerPackage(\TYPO3\Flow\Package\PackageInterface $package, $sortAndSave = TRUE) {
		$package = parent::registerPackage($package, $sortAndSave);
		if ($package instanceof PackageInterface) {
			foreach ($package->getPackageReplacementKeys() as $packageToReplace => $versionConstraint) {
				$this->packageAliasMap[strtolower($packageToReplace)] = $package->getPackageKey();
			}
		}
		return $package;
	}

	/**
	 * Unregisters a package from the list of available packages
	 *
	 * @param string $packageKey Package Key of the package to be unregistered
	 * @return void
	 */
	protected function unregisterPackageByPackageKey($packageKey) {
		$package = $this->getPackage($packageKey);
		if ($package instanceof PackageInterface) {
			foreach ($package->getPackageReplacementKeys() as $packageToReplace => $versionConstraint) {
				unset($this->packageAliasMap[strtolower($packageToReplace)]);
			}
		}
		parent::unregisterPackageByPackageKey($package->getPackageKey());
	}

	/**
	 * Resolves a Flow package key from a composer package name.
	 *
	 * @param string $composerName
	 * @return string
	 * @throws \TYPO3\Flow\Package\Exception\InvalidPackageStateException
	 */
	public function getPackageKeyFromComposerName($composerName) {
		if (isset($this->packageAliasMap[$composerName])) {
			return $this->packageAliasMap[$composerName];
		}
		return parent::getPackageKeyFromComposerName($composerName);
	}

	/**
	 * @return array
	 */
	public function getExtAutoloadRegistry() {
		if (!isset($this->extAutoloadClassFiles)) {
			$classRegistry = array();
			foreach ($this->activePackages as $packageKey => $packageData) {
				try {
					$extensionAutoloadFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($packageKey, 'ext_autoload.php');
					if (@file_exists($extensionAutoloadFile)) {
						$classRegistry = array_merge($classRegistry, require $extensionAutoloadFile);
					}
				} catch (\BadFunctionCallException $e) {
				}
			}
			$this->extAutoloadClassFiles = $classRegistry;
		}
		return $this->extAutoloadClassFiles;
	}

	/**
	 * Returns a PackageInterface object for the specified package.
	 * A package is available, if the package directory contains valid MetaData information.
	 *
	 * @param string $packageKey
	 * @return \TYPO3\Flow\Package\PackageInterface The requested package object
	 * @throws \TYPO3\Flow\Package\Exception\UnknownPackageException if the specified package is not known
	 * @api
	 */
	public function getPackage($packageKey) {
		if (isset($this->packageAliasMap[$lowercasedPackageKey = strtolower($packageKey)])) {
			$packageKey = $this->packageAliasMap[$lowercasedPackageKey];
		}
		return parent::getPackage($packageKey);
	}

	/**
	 * Returns TRUE if a package is available (the package's files exist in the packages directory)
	 * or FALSE if it's not. If a package is available it doesn't mean necessarily that it's active!
	 *
	 * @param string $packageKey The key of the package to check
	 * @return boolean TRUE if the package is available, otherwise FALSE
	 * @api
	 */
	public function isPackageAvailable($packageKey) {
		if (isset($this->packageAliasMap[$lowercasedPackageKey = strtolower($packageKey)])) {
			$packageKey = $this->packageAliasMap[$lowercasedPackageKey];
		}
		return parent::isPackageAvailable($packageKey);
	}

	/**
	 * Returns TRUE if a package is activated or FALSE if it's not.
	 *
	 * @param string $packageKey The key of the package to check
	 * @return boolean TRUE if package is active, otherwise FALSE
	 * @api
	 */
	public function isPackageActive($packageKey) {
		if (isset($this->packageAliasMap[$lowercasedPackageKey = strtolower($packageKey)])) {
			$packageKey = $this->packageAliasMap[$lowercasedPackageKey];
		}
		return parent::isPackageActive($packageKey);
	}

	/**
	 * @param string $packageKey
	 */
	public function deactivatePackage($packageKey) {
		$package = $this->getPackage($packageKey);
		parent::deactivatePackage($package->getPackageKey());
	}

	/**
	 * @param string $packageKey
	 */
	public function activatePackage($packageKey) {
		$package = $this->getPackage($packageKey);
		parent::activatePackage($package->getPackageKey());
	}


	/**
	 * @param string $packageKey
	 */
	public function deletePackage($packageKey) {
		$package = $this->getPackage($packageKey);
		parent::deletePackage($package->getPackageKey());
	}


	/**
	 * @param string $packageKey
	 */
	public function freezePackage($packageKey) {
		$package = $this->getPackage($packageKey);
		parent::freezePackage($package->getPackageKey());
	}

	/**
	 * @param string $packageKey
	 */
	public function isPackageFrozen($packageKey) {
		$package = $this->getPackage($packageKey);
		parent::isPackageFrozen($package->getPackageKey());
	}

	/**
	 * @param string $packageKey
	 */
	public function unfreezePackage($packageKey) {
		$package = $this->getPackage($packageKey);
		parent::unfreezePackage($package->getPackageKey());
	}

	/**
	 * @param string $packageKey
	 */
	public function refreezePackage($packageKey) {
		$package = $this->getPackage($packageKey);
		parent::refreezePackage($package->getPackageKey());
	}

}

?>