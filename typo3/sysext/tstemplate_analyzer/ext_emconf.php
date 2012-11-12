<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "tstemplate_analyzer".
 *
 * Auto generated 12-11-2012 21:58
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Web>Template, Template analyzer',
	'description' => 'Analyzes the hierarchy of included static and custom template records.',
	'category' => 'module',
	'shy' => 1,
	'dependencies' => 'tstemplate',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'doNotLoadInFE' => 1,
	'state' => 'stable',
	'internal' => 0,
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author' => 'Kasper Skaarhoj',
	'author_email' => 'kasperYYYY@typo3.com',
	'author_company' => 'Curby Soft Multimedia',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'version' => '1.1.0',
	'_md5_values_when_last_written' => 'a:5:{s:9:"ChangeLog";s:4:"b76f";s:31:"class.tx_tstemplateanalyzer.php";s:4:"e80a";s:12:"ext_icon.gif";s:4:"5630";s:14:"ext_tables.php";s:4:"6463";s:13:"locallang.xlf";s:4:"4f3f";}',
	'constraints' => array(
		'depends' => array(
			'tstemplate' => '',
			'php' => '5.3.0-0.0.0',
			'typo3' => '4.6.0-0.0.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
);

?>