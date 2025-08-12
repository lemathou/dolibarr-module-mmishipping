<?php
/* Copyright (C) 2025 Mathieu Moulin <mathieu@iprospective.fr>
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

/**
 * \file    core/triggers/interface_99_modMMIShipping_CarrierCostTriggers.class.php
 * \ingroup mmishipping
 * \brief   Update orders, bills, when shipping cost is updated.
 *
 * Put detailed description here.
 *
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

/**
 *  Class of triggers for MMIShipping module
 */
class InterfaceCarrierCostTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "MMIShipping Carrier Cost Trigger";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = 'development';
		$this->picto = 'mmishipping@mmishipping';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}


	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (empty($conf->mmishipping) || empty($conf->mmishipping->enabled)) {
			return 0; // If module is not enabled, we do nothing
		}

		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action

		// You can isolate code for each action in a separate method: this method should be named like the trigger in camelCase.
		// For example : COMPANY_CREATE => public function companyCreate($action, $object, User $user, Translate $langs, Conf $conf)
		$methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));

		$callback = array($this, $methodName);
		if (is_callable($callback)) {
			dol_syslog(
				"Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id
			);

			return call_user_func($callback, $action, $object, $user, $langs, $conf);
		};

		// Or you can execute some code here
		switch ($action) {

			// Customer orders
			//case 'ORDER_CREATE':
			//case 'ORDER_MODIFY':

			// Bills
			//case 'BILL_CREATE':
			//case 'BILL_MODIFY':

			// Shipping
			//case 'SHIPPING_CREATE':
			case 'SHIPMENT_MODIFY':
			case 'SHIPPING_MODIFY':
				// @todo pas très précis comme test ...
				if (isset($object->array_options['options_total_shipping_real_price']) && is_numeric($object->array_options['options_total_shipping_real_price'])) {
					$price = (float) $object->array_options['options_total_shipping_real_price'];
					$commande_line_id = isset($object->array_options['options_fk_order_line']) ?(int)$object->array_options['options_fk_order_line'] :NULL;

					$object_update = false;

					if ($commande_line_id) {
						$line = new OrderLine($this->db);
						$line->fetch($commande_line_id);

						if ($line->pa_ht != $price) {
							$line->pa_ht = $price;
							$line->update($user);
						}
					}
					else {

						/** @var Expedition $object */
						// @todo : WTF why is this not defined sometimes ?
						if (!$object->origin)
							$object->origin = 'commande';
						$object->fetch_origin();
						$order = $object->commande;

						$shipping_product_id = getDolGlobalInt('MMI_SHIPPING_CARRIER_SHIPPING_PRODUCT_ID');
						$shipping_product_ids = getDolGlobalString('MMI_SHIPPING_CARRIER_SHIPPING_PRODUCT_IDS');
						$shipping_product_ids = !empty($shipping_product_ids) ?explode(',', $shipping_product_ids) :[];
						if (!empty($shipping_product_id) && !in_array($shipping_product_id, $shipping_product_ids)) {
							$shipping_product_ids[] = $shipping_product_id;
						}
						$shipping_product_desc = 'Frais de port';
						$shipping_product_tvatx = 20.0; // TVA 20%

						$error_list = [
							'noorder' => 'Aucune commande trouvée pour la référence',
							'multiorder' => 'Plusieurs commandes trouvées pour la référence',
							'noshippings' => 'Pas d\'expédition trouvée pour la commande',
							'notshipped' => 'La commande n\'est pas livrée',
							'multiexpe' => 'Plusieurs expéditions trouvées pour le numéro de suivi',
							'shipnotfound' => 'Expédition non trouvée pour l\'id',
							'shiptoomuchinorder' => 'Trop d\'expéditions dans la commande',
							'addshiplineerror' => 'Erreur lors de l\'ajout de la ligne de transport',
						];
						$error = [];
						foreach($error_list as $k => $v) {
							$error[$k] = 0;
						}
						$msgs = [];

						$found=0;
						// @todo map everything so if I found only one shipping not mapped it is OK 
						foreach($order->lines as $line) {
							if (in_array($line->fk_product, $shipping_product_ids)) {
								$found++;
								if ($found>1)
									break;
							}
						}
						// None => create one
						if ($found==0) {
							$order_status = $order->statut;
							$order->statut = Commande::STATUS_DRAFT; // Draftify order
							$added = $order->addline('Transport offert', 0, 1, $shipping_product_tvatx, 0, 0, $shipping_product_id, 0, 0, 0, 'HT', 0, '', '', 1, -1, 0, 0, null, $price, $shipping_product_desc);
							$order->statut = $order_status; // UnDraftify order
							if ($added>0) {
								$order->update($user);
								$object->array_options['options_fk_order_line'] = $order->line->id;
								$object->array_options['options_carrier_invoice_updated'] = 1;
								$object_update = true;
								$msgs[] = 'Created shipping line for order '.$order->ref.' with price '.$price;
							}
							else {
								$error['addshiplineerror']++;
								$msgs[] = 'Error creating shipping line for order '.$order->ref.' with price '.$price;
							}
						}
						// At least 2 => by-pass
						elseif ($found>1) {
							$error['shiptoomuchinorder']++;
							$msgs[] = 'Too many shippings for order '.$order->ref;
							continue;
						}
						// One => Update
						else {
							foreach($order->lines as $line) {
								if (in_array($line->fk_product, $shipping_product_ids)) {
									// Mise à jour de la ligne de transport
									$line->pa_ht = (float) $price;
									$msgs[] = 'Update line '.$line->id.' for order '.$order->ref.' with price '.$price;
									$line->update($user);
									$object->array_options['options_fk_order_line'] = $line->id;
									$object->array_options['options_carrier_invoice_updated'] = 1;
									$object_update = true;
									//var_dump($ret, $ret2);
								}
							}
						}
					}
					// Update shipping object
					if ($object_update) {
						$object->insertExtraFields(NULL, $user);
					}
				}

				break;

			default:
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;
		}

		return 0;
	}
}
