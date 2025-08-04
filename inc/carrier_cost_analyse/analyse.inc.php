<?php

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';

$filename = $foldername.'/'.$carrier_name.'.csv';

if (!file_exists($filename)) {
	echo '<p class="error">File not found: '.$filename.'</p>';
	exit;
}


echo "<h1>Analyse CSV file</h1>\n";
echo '<p>CSV file: '.$filename.'</p>';

$fp = fopen($filename, 'r');

if (!$fp) {
	echo '<p>Error opening file: '.$filename.'</p>';
	exit;
}
if (($file_fields = fgetcsv($fp, 1000, ";")) === FALSE) {
	echo '<pEmpty file: '.$filename.'</p>';
	exit;
}
var_dump($file_fields);

$fields = [
	'num' => [
		'label'=>'Numéro de colis',
		'values'=>[' Numéro de colis ']
	],
	'ref' =>  [
		'label'=>'Réf commande',
		'values'=>['RéféRence']
	],
	'date' =>  [
		'label'=>'Date expé',
		'values'=>['Date Expédition']
	],
	'weight' =>  [
		'label'=>'Poids',
		'values'=>[' Poids ']
	],
	'nb' =>  [
		'label'=>'Nb Colis',
		'values'=>[' Colis ']
	],
	'comment' =>  [
		'label'=>'Comment',
		'values'=>['Commentaires taxes diverses']
	],
	// Réseau, type
	'type' => [
		'label'=>'Flux détail factu',
		'values'=>['Flux détail factu']
	],
	'network' => [
		'label'=>'National',
		'values'=>['National/ International']
	],
	// Prices
	'shipping_price' =>  [
		'label'=>'Mt transport',
		'values'=>[' Montant Transport ']
	],
	'surete_price' =>  [
		'label'=>'Mt taxe sureté',
		'values'=>[' Taxe Sûreté ']
	],
	'multicolis_price' =>  [
		'label'=>'Mt taxe multicolis',
		'values'=>[' Taxe multicolis/ Taxe Eco Responsable ']
	],
	'urbaine_price' =>  [
		'label'=>'Mt taxe urbaine',
		'values'=>[' Taxe multicolis/ Taxe Eco Responsable ']
	],
	// Péage
	// Marchandise Dangereuse
	'souffrance_price' =>  [
		'label'=>'Mt souffrance',
		'values'=>[' Souffrance ']
	],
	// Corse & îles
	// Douane
	// TVA import/export
	// Avance tréso - crédit enlèvement
	'douane_ddp_price' =>  [
		'label'=>'Mt prestation douane DDP',
		'values'=>[' Prestation de douane en DDP ']
	],
	'misc_price' =>  [
		'label'=>'Mt taxes divers',
		'values'=>[' Taxes diverses ']
	],
	'gazole_price' =>  [
		'label'=>'Mt taxe Gazole',
		'values'=>[' GAZOLE ']
	],
];
$fields_i = [];
foreach ($file_fields as $i=>$field) {
	foreach($fields as $key => $options) {
		if (isset($fields_i[$key])) {
			continue; // already found
		}
		foreach($options['values'] as $value) {
			if ($field == $value) {
				$fields_i[$key] = $i;
				continue;
			}
		}
	}
}
var_dump($fields_i);

$shipping_product_id = getDolGlobalInt('MMI_SHIPPING_CARRIER_SHIPPING_PRODUCT_ID');
$shipping_product_ids = getDolGlobalString('MMI_SHIPPING_CARRIER_SHIPPING_PRODUCT_IDS');
$shipping_product_ids = !empty($shipping_product_ids) ?explode(',', $shipping_product_ids) :[];
if (!empty($shipping_product_id) && !in_array($shipping_product_id, $shipping_product_ids)) {
	$shipping_product_ids[] = $shipping_product_id;
}
$shipping_product_desc = 'Frais de port';
$shipping_product_tvatx = 20.0; // TVA 20%

$error = [
	'noorder' => 0, // No order found
	'multiorder' => 0, // More than 1 order found
	'noshipping' => 0, // No shipping found
	'notshipped' => 0, // Order not shipped
	'multiexpe' => 0, // More than 1 compatible shipping found for order
	'shipnotfound' => 0, // fetch shipping error using id 
	'shiptoomuchinorder' => 0,
];

$row = 0;

while (($data = fgetcsv($fp, 1000, ";")) !== FALSE) {
	$row++;
	echo '<hr />';
	$msgs = [];
	echo "<p>line $row: <br /></p>\n";
	echo '<div style="display: none;">';
	$metadata = [];
	foreach($fields_i as $key=>$i) {
		$metadata[$key] = $data[$i];
	}

	foreach($metadata as $key => $value) {
		echo $fields[$key]['label'].': '.$value . "<br />\n";
	}

	// Recherche expédition... Attention si plusieurs !
	$sql = 'SELECT o2.*, o.*, GROUP_CONCAT(e.rowid SEPARATOR ",") AS expeditions_id'
		.' FROM '.MAIN_DB_PREFIX.'commande AS o'
		.' INNER JOIN '.MAIN_DB_PREFIX.'commande_extrafields AS o2 ON o2.fk_object = o.rowid'
		.' LEFT JOIN '.MAIN_DB_PREFIX.'element_element AS ee ON (ee.sourcetype="commande" AND ee.fk_source=o.rowid AND ee.targettype="shipping")'
		.' LEFT JOIN '.MAIN_DB_PREFIX.'expedition AS e ON ee.targettype="shipping" AND e.rowid=ee.fk_target'
		.' WHERE o2.p_ref = \''.$db->escape($data[$fields_i['ref']]).'\''
		.'   OR o.ref = \''.$db->escape($data[$fields_i['ref']]).'\''
		.'   OR e.tracking_number = \''.$db->escape($metadata['num']).'\''
		.' GROUP BY o.rowid';
	$resql = $db->query($sql);
	$num_rows = $db->num_rows($resql);

	if ($num_rows>1) {
		echo '</div>';
		echo '<p class="error">Plusieurs commandes trouvées pour ref: '.$data[$fields_i['ref']].'</p>';
		$error['multiorder']++;
		$error['multiorder']++;
		var_dump($metadata);
		continue;
	}
	elseif ($num_rows==0) {
		echo '</div>';
		echo '<p class="error">Commande not found for ref: '.$data[$fields_i['ref']].'</p>';
		$error['noorder']++;
		var_dump($metadata);
		continue;
	}

	$infos = $db->fetch_object($resql);

	//var_dump($commande);
	// $commande = new Commande($db);
	// $commande->fetch($infos->rowid);

	if (empty($infos->expeditions_id)) {
		echo '</div>';
		echo '<p class="error">Pas d\'expédition...</p>';
		$error['noshippings']++;
		var_dump($metadata);
		continue;
	}

	if ($infos->fk_statut != Commande::STATUS_CLOSED) {
		echo '</div>';
		echo '<p class="error">Statut de commande non livré</p>';
		$error['notshipped']++;
		var_dump($metadata);
		continue;
	}

	$object = new Expedition($db);
	$expeditions = [];
	$expedition_ids = explode(',', $infos->expeditions_id);
	$expedition_id = NULL;

	$error_shipnotfound = false;
	foreach($expedition_ids as $expe_id) {
		$expeditions[$expe_id] = new Expedition($db);
		$expeditions[$expe_id]->fetch($expe_id);
		if (!$expeditions[$expe_id]->id) {
			echo '</div>';
			echo '<p class="error">Expédition not found for id: '.$expe_id.'</p>';
			$error['shipnotfound']++;
			$error_shipnotfound = true;
			var_dump($metadata);
			break;
		}
		if ($expeditions[$expe_id]->tracking_number == $metadata['num']) {
			$expedition_id = $expe_id;
			break; // On prend la première trouvée
		}
	}
	if ($error_shipnotfound) {
		continue;
	}
	if (count($expedition_ids)>1 && !$expedition_id) {
		echo '</div>';
		echo '<p class="error">Plusieurs expéditions => Ne peux pas calculer (première version de l\'outil)</p>';
		$error['multiexpe']++;
		var_dump($metadata);
		continue;
	}

	foreach($expeditions as $expe_id=>$expedition) {
		if ($expedition_id) {
			if ($expe_id != $expedition_id) {
				continue; // On ne traite que l'expédition trouvée
			}
			else {
				$object = $expeditions[$expe_id];
			}
		}

		//var_dump($expedition);
		$price = 0;
		foreach(['shipping_price', 'surete_price', 'multicolis_price', 'urbaine_price', 'souffrance_price'] as $i) {
			$metadata[$i] = trim(str_replace(',', '.', $metadata[$i]));
			$price += is_numeric($metadata[$i]) && !empty($metadata[$i]) ?$metadata[$i] :0;
		}

		// Total price calculation
		echo '<p class="error">Expédition: '.$object->ref.'</p>';
		$object->date_shipping = dol_mktime(0, 0, 0, substr($data[$fields_i['date']], 3, 2), substr($data[$fields_i['date']], 0, 2), substr($data[$fields_i['date']], 6, 4));
		$object->tracking_number = $metadata['num'];
		//$object->method_expe = '';
		//$object->status = ''; // Attention workflow !
		$object->array_options['options_total_shipping_real_price'] = (float) str_replace(',', '.', $price);
		$object->array_options['options_real_weight'] = (float) str_replace(',', '.', $metadata['weight']);
		$object->array_options['options_carrier_metadata'] = json_encode($metadata);
		//$object->array_options['options_carrier_invoice_updated'] = 1;
		
		$res = $object->update($user);
		$res2 =  $object->insertExtraFields();
		//var_dump($object->array_options, $res, $res2);

		// Updated one expedition, finished
		break;
	}
	
	echo '</div>';

	foreach($msgs as $msg) {
		echo '<p class="ok">'.$msg.'</p>';
	}
}

echo '<hr />';
echo '<h3 class="ok">Analyse terminée</h3>';
echo '<p class="error">Errors :</p>';
foreach($error as $i=>$j)
	echo '<p>'.$i.' : '.$j.'</p>';

fclose($fp);
