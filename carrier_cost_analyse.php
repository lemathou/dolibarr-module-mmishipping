<?php

// Load Dolibarr environment
require_once 'main_load.inc.php';

if (empty($user->id) || !$user->hasRight('expedition', 'lire')) {
	accessforbidden();
}

$tpl_name = 'carrier_cost_analyse';

require_once DOL_DOCUMENT_ROOT.'/custom/mmicommon/inc/tpl/module.tpl.php';
