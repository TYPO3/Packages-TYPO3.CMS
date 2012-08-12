<?php
/***************************************************************
 * Copyright notice
 *
 * (c) 2012 Steffen Gebert (steffen.gebert@typo3.org)
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
 * A log writer that always fails to write (for testing purposes ;-))
 *
 * @author Steffen Gebert <steffen.gebert@typo3.org>
 */
class t3lib_log_writer_Failing implements t3lib_log_writer_Writer {

	/**
	 * Try to write the log entry - but throw an exception in our case
	 *
	 * @param t3lib_log_Record $record
	 * @return t3lib_log_writer_Writer|void
	 * @throws RuntimeException
	 */
	public function writeLog(t3lib_log_Record $record) {
		throw new RuntimeException("t3lib_log_writer_Failing failed");
	}

}

?>