<?php
/* Copyright (C) 2026 Mathieu Moulin / iProspective <contact@iprospective.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once 'env.inc.php';
require_once 'main_load.inc.php';

$langs->loadLangs(array('mmishipping@mmishipping'));
dol_include_once('/mmicommon/lib/mmi_home.lib.php');

$title = $langs->trans('MMIShippingArea');
$tabs = array(
	'home' => array('url' => dol_buildpath('/mmishipping/mmishippingindex.php', 1), 'label' => $langs->trans('MMIShippingHome')),
	'carrier' => array('url' => dol_buildpath('/mmishipping/carrier_cost_analyse.php', 1), 'label' => $langs->trans('MMI_SHIPPING_CARRIER')),
);
MMIHomeHeader($title, $tabs, 'home');
print '<div class="opacitymedium">'.$langs->trans('MMIShippingHomeIntro').'</div>';
$links = array(
	$langs->trans('MMI_SHIPPING_CARRIER') => dol_buildpath('/mmishipping/carrier_cost_analyse.php', 1),
	$langs->trans('Settings') => dol_buildpath('/mmishipping/admin/setup.php', 1),
);
print MMIHomeBlock($langs->trans('MMIShippingHomeLinks'), MMIHomeLinks($links));
MMIHomeFooter();
