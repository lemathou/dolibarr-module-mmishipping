<?php

// Protection to avoid direct call of file
if (!defined('DOL_VERSION'))
	die('Dolibarr must be loaded');

$carrier_foldername = $foldername.'/'.$carrier_name;
if (!file_exists($carrier_foldername)) {
	mkdir($carrier_foldername, 0755);
}

if ($delete=GETPOST('delete', 'alpha')) {
	if (!preg_match('/^[0-9\-\.]+\.csv$/', $delete)) {
		echo '<p class="error">Invalid filename for deletion: '.$delete.'</p>';
		exit;
	}
	$filename = $carrier_foldername.'/'.$delete;
	if (file_exists($filename)) {
		if (unlink($filename)) {
			echo '<p class="ok">File deleted successfully: '.$delete.'</p>';
		} else {
			echo '<p class="error">Failed to delete file: '.$delete.'</p>';
		}
	} else {
		echo '<p class="error">File not found: '.$delete.'</p>';
	}
}

$filelist = [];
$fp = opendir($carrier_foldername);
while($f = readdir($fp)) {
	if ($f == '.' || $f == '..') continue;
	if (is_dir($carrier_foldername.'/'.$f)) continue;
	$filename = $carrier_foldername.'/'.$f;
	$fe = explode('-', substr($f, 0, -4));
	$filelist[] = [
		'filename' => $f,
		'linecount' => $fe[1],
		'date_min' => substr($fe[2], 0, 4).'-'.substr($fe[2], 4, 2).'-'.substr($fe[2], 6, 2),
		'date_max' => substr($fe[3], 0, 4).'-'.substr($fe[3], 4, 2).'-'.substr($fe[3], 6, 2),
		'filedate' => date('Y-m-d H:i:s', filemtime($filename)),
	];
}

echo '<h3><a href="?action=list&carrier_name='.$carrier_name.'">Transporteur : '.$carrier_name.'</a></h3>';

if (empty($filelist)) {
	echo '<p class="error">No file found</p>';
}
else {
	echo '<p>Fichiers CSV existant à réanalyser :</p>';
	echo '<table border="">'
		.'<thead><tr>'
		.'<th>Fichier</th>'
		.'<th>Date upload</th>'
		.'<th>Nb lignes</th>'
		.'<th>Date expé min</th>'
		.'<th>Date expé min</th>'
		.'</tr></thead>'
		.'<tbody>';
	foreach($filelist as $file) {
		$filename = $file['filename'];
		$filedate = $file['filedate'];
		echo '<tr>'
			.'<td><a href="?carrier_name='.$carrier_name.'&filename='.$filename.'&action=analyse">'.$filename.'</a></td>'
			.'<td>'.$file['filedate'].'</td>'
			.'<td>'.$file['linecount'].'</td>'
			.'<td>'.$file['date_min'].'</td>'
			.'<td>'.$file['date_max'].'</td>'
			.'<td><a href="?carrier_name='.$carrier_name.'&action=list&delete='.$filename.'" class="delete warning" onclick="return confirm(\'Êtes-vous certain de vouloir supprimer ce fichier ?\');">X</a></td>'
			.'</tr>';
	}
	echo '</tbody>'
		.'</table>';
}

?>
<div style="border: 1px solid black;margin-top: 20px;">
<form method="POST" action="?carrier_name=<?php echo $carrier_name; ?>&action=save" enctype="multipart/form-data">
	<p>
		<label for="file">Choisir un (nouveau) fichier CSV à uploader :</label>
		<input name="file" type="file" accept=".csv" />
	</p>
	<p>
		<input type="submit" value="Analyser le nouveau fichier" />
	</p>
</form>
</div>