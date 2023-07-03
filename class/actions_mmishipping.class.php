<?php
/* Copyright (C) 2023 Moulin Mathieu <contact@iprospective.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

dol_include_once('custom/mmicommon/class/mmi_actions.class.php');
dol_include_once('custom/mmishipping/class/mmishipping.class.php');

require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';

/**
 * Class ActionsMMIShipping
 */
class ActionsMMIShipping extends MMI_Actions_1_0
{
	const MOD_NAME = 'mmishipping';

	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs, $db;

		$error = 0; // Error counter

		// Associated order
		if ($this->in_context($parameters, 'ordersuppliercard') && !empty($conf->global->MMISHIPPING_DF)) {
			// Recherche commande liée
			$commande = mmishipping::order_associated_to_supplier_order($object->id);
		}

		// Fiche Commande
		if ($this->in_context($parameters, 'ordersuppliercard')) {
			// Client réception si commande liée
			if (!empty($conf->global->MMISHIPPING_DF)) {
				if (!empty($commande) && !empty($user->rights->mmishipping->df->affect)){
					$ok = !empty(mmishipping::supplier_order_shipping_address($commande));
					$link = '?id='.$object->id.'&action=adresse_assign_auto';
					echo "<a class='".($ok ?'butAction' :'butActionRefused')."'".($ok ?" href='".$link."'" :"onclick='return false;' title=\"Missing customer address\"").">".$langs->trans("MMIShippingAssignAddress")."</a>";
				}
				if (!empty($commande) && !empty($user->rights->mmishipping->df->autoliquidation)){
					$ok = !empty($object->array_options['options_fk_adresse']);
					$link = '?id='.$object->id.'&action=receive_and_send';
					echo "<a class='".($ok ?'butAction' :'butActionRefused')."'".($ok ?" href='".$link."'" :"onclick='return false;' title=\"Missing customer address\"").">".$langs->trans("MMIShippingSupplierOrderReceiveAndSend")."</a>";
				}
			}
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs, $db;

		$error = 0; // Error counter

		// Associated order
		if ($this->in_context($parameters, 'ordersuppliercard') && in_array($action, ['adresse_assign_auto', 'receive_and_send']) && !empty($conf->global->MMISHIPPING_DF)) {
			// Recherche commande liée
			$commande = mmishipping::order_associated_to_supplier_order($object->id);
			//var_dump($commande);
		}

		// Assign client address
		if ($this->in_context($parameters, 'ordersuppliercard') && $action=='adresse_assign_auto' && !empty($commande) && !empty($conf->global->MMISHIPPING_DF) && !empty($user->rights->mmishipping->df->affect)) {
			//var_dump($commande);
			mmishipping::supplier_order_shipping_address_assign($user, $object, $commande);
		}

		// receive and send
		if ($this->in_context($parameters, 'ordersuppliercard') && $action=='receive_and_send' && !empty($commande) && !empty($conf->global->MMISHIPPING_DF) && !empty($conf->global->MMISHIPPING_DF_ENTREPOT) && !empty($user->rights->mmishipping->df->autoliquidation)) {
			$entrepot = new Entrepot($db);
			$entrepot->fetch($conf->global->MMISHIPPING_DF_ENTREPOT);
			$commande->loadExpeditions();
			$object->loadReceptions();
			if ($entrepot->id) {
				$ok = true;
				$todo = [];
				foreach($object->lines as $line) {
					//var_dump($line);
					// Quantité restant à réceptionner dans la commande fournisseur
					$qty = $line->qty;
					// déjà reçu
					if (isset($object->receptions[$line->id]))
						$qty -= $object->receptions[$line->id];
					// @var $found Quantité encore à expédier depuis la commande
					$found = 0;
					//var_dump($commande->lines);
					foreach($commande->lines as $cline) {
						// Qté dans commande
						if ($cline->fk_product==$line->fk_product) {
							$found += $cline->qty;
							// Qté déjà expédiée
							if (isset($commande->expeditions[$cline->id]))
								$found -= $commande->expeditions[$cline->id];
						}
					}
					//var_dump($found, $qty);
					// Si qté à réceptionner > qté à expédier, BUG car on va en envoyer trop par rapport à ce qui est commandé
					if ($qty>$found) {
						$ok = false;
						$error++;
						// @todo : mettre le message en traduction
						$this->errors[] = 'Qty too high for line "'.$line->libelle.'", found '.$found.' but needed to send only '.$qty;
						break;
					}
					else {
						$todo[$line->id] = $qty;
					}
				}
				// Créer réception & expé
				if ($ok) {
					//
					//var_dump($todo);
					$reception = mmishipping::commande_fourn_to_reception($user, $object);
					$shipping = mmishipping::commande_fourn_to_shipping($user, $object);
				}
				//var_dump($ok);
			}
			else {
				$error++;
				// @todo : mettre le message en traduction
				$this->errors[] = 'Missing entrepot '.$conf->global->MMISHIPPING_DF_ENTREPOT.', bad config MMISHIPPING_DF_ENTREPOT';
			}
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			//$this->errors[] = 'Error message';
			return -1;
		}
	}
}
