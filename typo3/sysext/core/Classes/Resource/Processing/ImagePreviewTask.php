<?php
namespace TYPO3\CMS\Core\Resource\Processing;

use \TYPO3\CMS\Core\Resource;

/**
 * A task for generating an image preview.
 */
class ImagePreviewTask extends AbstractGraphicalTask {

	/**
	 * @var string
	 */
	protected $type = 'Image';

	/**
	 * @var string
	 */
	protected $name = 'Preview';

	/**
	 * Returns the target filename for this task.
	 *
	 * @return string
	 */
	public function getTargetFileName() {
		return 'preview_' . parent::getTargetFilename();
	}

	/**
	 * Checks if the given configuration is sensible for this task, i.e. if all required parameters
	 * are given, within the boundaries and don't conflict with each other.
	 *
	 * @param array $configuration
	 * @return boolean
	 */
	protected function isValidConfiguration(array $configuration) {
		/**
		 * Checks to perform:
		 * - width and/or height given, integer values?
		 */
	}

	/**
	 * Returns TRUE if the file has to be processed at all, such as e.g. the original file does.
	 *
	 * Note: This does not indicate if the concrete ProcessedFile attached to this task has to be (re)processed.
	 * This check is done in ProcessedFile::isOutdated(). TODO isOutdated()/needsReprocessing()?
	 *
	 * @return bool
	 */
	public function fileNeedsProcessing() {
		// TODO: Implement fileNeedsProcessing() method.

		/**
		 * Checks to perform:
		 * - width/height smaller than image, keeping aspect ratio?
		 */
	}
}

?>