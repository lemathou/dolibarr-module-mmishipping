<?php

// Protection to avoid direct call of file
if (!defined('DOL_VERSION'))
	die('Dolibarr must be loaded');

$carrier_foldername = $foldername.'/'.$carrier_name;
if (!file_exists($carrier_foldername)) {
	mkdir($carrier_foldername, 0755);
}

$filelist = [];
$fp = opendir($carrier_foldername);
while($f = readdir($fp)) {
	if ($f == '.' || $f == '..') continue;
	if (is_dir($carrier_foldername.'/'.$f)) continue;
	$filename = $carrier_foldername.'/'.$f;
	$filelist[] = [
		'filename' => $f,
		'filedate' => date('Y-m-d H:i:s', filemtime($filename)),
	];
}

echo '<h3>'.$carrier_name.'</h3>';

if (empty($filelist)) {
	echo '<p class="error">No file found</p>';
}
else {
	echo '<p>Fichiers CSV existant à réanalyser :</p>';
	foreach($filelist as $file) {
		$filename = $file['filename'];
		$filedate = $file['filedate'];
		echo '<p><a href="?carrier_name='.$carrier_name.'&file='.$filename.'&action=analyse">'.$filename.'</a> ('.$filedate.') <a href="?carrier_name='.$carrier_name.'&file='.$filename.'&action=delete" class="delete warning">X</a></p>';
	}
}

?>
<form method="POST">
<input type="hidden" name="action" value="save" />
	<p>
		<label for="file">Choisir un (nouveau) fichier CSV à uploader :</label>
		<input name="file" type="file" accept=".csv" />
	</p>
	<p>
		<input type="submit" value="Analyser le nouveau fichier" />
	</p>
</form>