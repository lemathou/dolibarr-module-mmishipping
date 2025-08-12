<?php
/* Copyright (C) 2025      Mathieu Moulin       <mathieu@iprospective.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       mmishipping/carrier_cost_analyse.php
 *	\ingroup    mmishipping
 *	\brief      Analyse shippings CSV file from supplier and record cost prices
 */

echo '<h2><a href="?action=form">Import et Analyse CSV facturation transporteur</a></h2>';

$actions = array('list', 'save', 'analyse', 'delete');
$action = GETPOST('action', 'aZ09');

require_once('inc/carrier_cost_analyse/common.inc.php');

if (empty($carrier_name)) {
	require_once('inc/carrier_cost_analyse/form.inc.php');
}
elseif ($action=='save') {
	require_once('inc/carrier_cost_analyse/save.inc.php');
	require_once('inc/carrier_cost_analyse/analyse.inc.php');
}
elseif ($action=='analyse') {
	require_once('inc/carrier_cost_analyse/analyse.inc.php');
}
elseif ($action=='delete') {
	require_once('inc/carrier_cost_analyse/delete.inc.php');
	require_once('inc/carrier_cost_analyse/list.inc.php');
}
else {// list
	require_once('inc/carrier_cost_analyse/list.inc.php');
}
