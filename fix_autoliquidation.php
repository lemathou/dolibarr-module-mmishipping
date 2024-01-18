<?php
/**
 * Copyright (C) 2024      Mathieu Moulin	<mathieu@iprospective.fr>
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
 *	\file       mmishipping/fix_autoliquidation.php
 *	\ingroup    mmishipping
 */

// Load Dolibarr environment
require_once 'env.inc.php';
require_once 'main_load.inc.php';

// BLocage
die();

// Recherche doublons d'envois de lignes de commande
// Groupage par expédition : pas deux fois la même ligne de commande dans des expéditions dépôt 7
$sql = 'SELECT DISTINCT GROUP_CONCAT(el.rowid) el_rowids, COUNT(*) nb, el.fk_expedition, el.fk_origin_line, el.qty, e.rowid, e.ref, e.fk_soc, e.ref_customer, e.date_valid, cl.qty cl_qty, cl.fk_commande, c.ref commande_ref
	FROM `llx_expeditiondet` el
	INNER JOIN `llx_expedition` e ON e.rowid=el.fk_expedition
	LEFT JOIN `llx_commandedet` cl ON cl.rowid=el.fk_origin_line
	LEFT JOIN `llx_commande` c ON c.rowid=cl.fk_commande
	WHERE el.fk_entrepot = 7
	GROUP BY el.fk_expedition, el.fk_origin_line
	HAVING COUNT(*)>1';
$q = $db->query($sql);
if ($q) while($row=$q->fetch_assoc()) {
	var_dump($row);
}

// Recherche expé sans réceptions
// Groupage par expédition : pas deux fois la même ligne de commande dans des expéditions dépôt 7
$sql = 'SELECT DISTINCT GROUP_CONCAT(el.rowid) el_rowids, COUNT(*) nb, el.fk_expedition, el.fk_origin_line, el.qty, e.rowid, e.ref, e.fk_soc, e.ref_customer, e.date_valid, cl.qty cl_qty, cl.fk_commande, c.ref commande_ref
	FROM `llx_expeditiondet` el
	INNER JOIN `llx_expedition` e ON e.rowid=el.fk_expedition
	LEFT JOIN `llx_commandedet` cl ON cl.rowid=el.fk_origin_line
	LEFT JOIN `llx_commande` c ON c.rowid=cl.fk_commande
	LEFT JOIN `llx_commande` c ON c.rowid=cl.fk_commande
	WHERE el.fk_entrepot = 7
	GROUP BY el.fk_expedition, el.fk_origin_line
	HAVING COUNT(*)>1';
$q = $db->query($sql);
if ($q) while($row=$q->fetch_assoc()) {
	var_dump($row);
}

// Recherche réceptions sans expé
