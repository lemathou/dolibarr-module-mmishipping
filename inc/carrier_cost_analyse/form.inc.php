<?php

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}

$carrier_name = '';

?>
<form method="GET">
	<p>
		<label for="carrier_name">Sélectionner un transporteur :</label>
		<select name="carrier_name">
		<?php
			foreach ($carriers as $carrier) {
				$selected = ($carrier_name == $carrier) ? ' selected' : '';
				echo '<option value="'.$carrier.'"'.$selected.'>'.$carrier.'</option>';
			}
		?>
		<select>
	</p>
	<p>
		<label for="file">Choisir le fichier CSV à uploader :</label>
		<input name="file" type="file" accept=".csv" />
	</p>
	<p>
		<input type="submit" value="Analyser" />
	</p>
</form>
