<?php

// Protection to avoid direct call of file
if (!defined('DOL_VERSION'))
	die('Dolibarr must be loaded');

$foldername = DOL_DATA_ROOT.'/mmishipping';

// @todo : Make it configurable
$carriers = ['skipper'];

// Folder creation
// @todo put it in module install
if (!file_exists($foldername)) {
	mkdir($foldername, 0755);
}

$carrier_name = GETPOST('carrier_name', 'aZ09_-');
//var_dump($carrier_name);

$filename = GETPOST('filename');
if ($filename && !preg_match('/^[0-9-]+\.csv$/', $filename)) {
	echo '<p class="error">Invalid filename: '.$filename.'</p>';
	exit;
};

$file_id = GETPOST('file_id', 'int');
//var_dump($file_id);
