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
 *	\file       mmishipping/csv_analyse.php
 *	\ingroup    mmishipping
 *	\brief      Analyse shippings CSV file from supplier and record cost prices
 */

// Load Dolibarr environment
require_once 'env.inc.php';
require_once 'main_load.inc.php';

$foldername =  DOL_DATA_ROOT.'/mmishipping';
// @todo put it in module install
if (!file_exists($foldername)) {
	mkdir($foldername, 0755);
}

// @todo : Make it configurable
$carriers = ['skipper'];

$carrier_name = GETPOST('carrier_name', 'aZ09_-');
//var_dump($carrier_name);

if (empty($carrier_name)) {
	require_once('inc/carrier_cost_analyse/form.inc.php');
}
else {
	require_once('inc/carrier_cost_analyse/analyse.inc.php');
}
