<?php

// Protection to avoid direct call of file
if (!defined('DOL_VERSION'))
	die('Dolibarr must be loaded');

?>
<form method="GET">
<input type="hidden" name="action" value="list" />
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
		<input type="submit" value="Choisir le transporteur" />
	</p>
</form>