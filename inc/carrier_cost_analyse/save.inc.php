<?php

// Protection to avoid direct call of file
if (!defined('DOL_VERSION'))
	die('Dolibarr must be loaded');


if(!empty($_FILES['file'])) {
	$ts = date('YmdHis');
	$filename = $ts.'.csv';

	if (move_uploaded_file($_FILES['file']['tmp_name'], $foldername.'/'.$carrier_name.'/'.$filename)) {
		echo '<p class="ok">File uploaded successfully: '.$filename.'</p>';

		$linecount = 0;
		$date_min = null;
		$date_max = null;
		$fp = fopen($foldername.'/'.$carrier_name.'/'.$filename, "r");
		if (!$fp) {
			echo '<p class="error">Error opening file: '.$filename.'</p>';
			exit;
		}
		$date_i = NULL;
		$file_fields = fgetcsv($fp, 1000, ";");
		if (empty($file_fields)) {
			echo '<p class="error">Empty file: '.$filename.'</p>';
			fclose($fp);
			exit;
		}
		foreach ($file_fields as $i=>$field) {
			if (in_array($field, ['Date', 'Date Expédition'])) {
				$date_i = $i;
				break;
			}
		}
		if (empty($date_i)) {
			echo '<p class="error">No date field found in file: '.$filename.'</p>';
			fclose($fp);
			exit;
		}
		while (($line = fgetcsv($fp, 1000, ";")) !== FALSE) {
			$linecount++;
			$date = implode('', array_reverse(explode('/', $line[$date_i])));
			if (!$date_min || $date<$date_min)
				$date_min = $date;
			if (!$date_max || $date>$date_max)
				$date_max = $date;
		}
		fclose($fp);

		$oldfilename = $filename;
		$filename = $ts.'-'.$linecount.'-'.$date_min.'-'.$date_max.'.csv';
		echo 'rename '.$foldername.'/'.$carrier_name.'/'.$oldfilename.' to '.$foldername.'/'.$carrier_name.'/'.$filename;
		rename($foldername.'/'.$carrier_name.'/'.$oldfilename, $foldername.'/'.$carrier_name.'/'.$filename);
		
	} else {
		echo '<p class="error">Failed to upload file '.$_FILES['file']['name'].'</p>';
	}
} else {
	echo '<p class="error">No file uploaded.</p>';
}
