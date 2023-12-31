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
				if (!empty($commande) && !empty($user->rights->mmishipping->df->affect)) {
					$ok = !empty(mmishipping::supplier_order_shipping_address($user, $object, $commande)) && ($object->statut >= 0) && ($object->statut <= 3);
					$link = '?id='.$object->id.'&action=adresse_assign_auto';
					echo "<a class='".($ok ?'butAction' :'butActionRefused')."'".($ok ?" href='".$link."'" :"onclick='return false;' title=\"Multiple customer addresses or order closed\"").">".$langs->trans("MMIShippingAssignAddress")."</a>";
				}
				if (!empty($commande) && !empty($user->rights->mmishipping->df->autoliquidation)) {
					$ok = !empty($object->array_options['options_fk_adresse']) && ($object->statut == 3);
					$link = '?id='.$object->id.'&action=receive_and_send';
					echo "<a class='".($ok ?'butAction' :'butActionRefused')."'".($ok ?" href='".$link."'" :"onclick='return false;' title=\"Missing customer address or order closed or not ordered\"").">".$langs->trans("MMIShippingSupplierOrderReceiveAndSend")."</a>";
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
			if ($object->statut > 3) {
				$error++;
				// @todo : mettre le message en traduction
				$this->errors[] = 'Order is closed';
			}
			elseif ($object->statut < 2) {
				$error++;
				// @todo : mettre le message en traduction
				$this->errors[] = 'Order is not ordered';
			}
			else {
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
						elseif ($qty>0) {
							$todo[$line->id] = $qty;
						}
					}
					if (empty($todo)) {
						$ok = false;
						$error++;
						// @todo : mettre le message en traduction
						$this->errors[] = 'Nothing to send';
					}
					// Créer réception & expé
					if ($ok) {
						//
						//var_dump($todo);
						$reception = mmishipping::commande_fourn_to_reception($user, $object);
						$shipping = mmishipping::commande_fourn_to_shipping($user, $object);
						// Lien entre les deux
						$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'element_element
							(fk_source, sourcetype, fk_target, targettype)
							VALUES
							('.$reception->id.', "reception", '.$shipping->id.', "shipping")';
						$this->db->query($sql);
					}
					//var_dump($ok);
				}
				else {
					$error++;
					// @todo : mettre le message en traduction
					$this->errors[] = 'Missing entrepot '.$conf->global->MMISHIPPING_DF_ENTREPOT.', bad config MMISHIPPING_DF_ENTREPOT';
				}
			}
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			//$this->errors[] = 'Error message';
			return -1;
		}
	}


	public function getLabel($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs, $db;

		$error = 0; // Error counter

		// Display supplier & supplier_email in reception popup
		if ($this->in_context($parameters, 'receptiondao')) {
			// Recherche commande liée
			//var_dump($object); die();
			// Pas les infos dans le contexte => c'est qu'on est peut-être dans une liste et on va pas tout aller chercher...
			if (empty($object->origin) || empty($object->origin_id))
				return 0;
			if (! is_object($object->{$object->origin}))
				$object->fetch_origin();
			if(is_object($object->{$object->origin})) {
				$parameters['label'] .= '<br><b>'.$langs->trans($object->origin).':</b> '.$object->{$object->origin}->ref;
			}
			if (! is_object($object->thirdparty))
				$object->fetch_thirdparty();
			if(is_object($object->thirdparty)) {
				$parameters['label'] .= '<br><b>'.$langs->trans('Supplier').':</b> '.$object->thirdparty->name;
				$parameters['label'] .= '<br><b>'.$langs->trans('Supplier').' '.$langs->trans('email').':</b> '.$object->thirdparty->email;
				$parameters['label'] .= '<br><b>'.$langs->trans('Supplier').' '.$langs->trans('telephone').':</b> '.$object->thirdparty->phone;
			}
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}
}
