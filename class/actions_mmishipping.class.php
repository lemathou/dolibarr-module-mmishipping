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
		//var_dump($this); die();
		//var_dump(__CLASS__, get_called_class(), static::MOD_NAME, $lang);

		// Associated order
		if ($this->in_context($parameters, 'ordersuppliercard') && !empty($conf->global->MMISHIPPING_DF)) {
			// Recherche commande liée
			$commande = mmishipping::order_associated_to_supplier_order($object->id);
		}

		// Fiche Commande client : édition des frais de port sans repasser en brouillon (RM2381)
		if ($this->in_context($parameters, 'ordercard') && getDolGlobalString('MMI_SHIPPING_PORT_EDIT')) {
			if (!empty($user->rights->mmishipping->commande->port_edit) && (int) $object->statut >= Commande::STATUS_VALIDATED) {
				$langs->load('mmishipping@mmishipping');
				$link = '?id='.$object->id.'&action=port_edit&token='.newToken();
				echo "<a class='butAction' href='".$link."'>".$langs->trans("MMIShippingPortEdit")."</a>";
			}
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

	/**
	 * Popup de confirmation "Frais de port" sur la fiche commande (RM2381)
	 */
	public function formConfirm($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs, $db;

		if ($this->in_context($parameters, 'ordercard') && $action === 'port_edit'
			&& getDolGlobalString('MMI_SHIPPING_PORT_EDIT') && !empty($user->rights->mmishipping->commande->port_edit)) {
			$langs->load('mmishipping@mmishipping');

			list($line, $count) = mmishipping::object_shipping_line($object);
			if ($count > 1) {
				setEventMessages($langs->trans('MMIShippingPortEditTooManyLines', $count), null, 'warnings');
				return 0;
			}

			$form = new Form($db);
			$formquestion = [
				['type' => 'text', 'name' => 'port_sell_ht', 'label' => $langs->trans('MMIShippingPortEditSellHT'),
					'value' => $line !== null ?price2num($line->subprice) :''],
				['type' => 'text', 'name' => 'port_buy_ht', 'label' => $langs->trans('MMIShippingPortEditBuyHT'),
					'value' => $line !== null ?price2num($line->pa_ht) :''],
				['type' => 'checkbox', 'name' => 'port_update_invoice', 'label' => $langs->trans('MMIShippingPortEditUpdateInvoice'), 'value' => 1],
				// Commentaire de la ligne de service, imprimé sous le libellé sur les PDF client (RM2559)
				['type' => 'textarea', 'name' => 'port_desc', 'label' => $langs->trans('MMIShippingPortEditDesc'),
					'value' => dol_escape_htmltag($line !== null ?$line->desc :mmishipping::PORT_LABEL, 0, 1),
					'morecss' => 'centpercent', 'moreattr' => 'rows="3"'],
			];
			$text = $langs->trans($line !== null ?'MMIShippingPortEditConfirmUpdate' :'MMIShippingPortEditConfirmCreate', $object->ref);
			$this->resprints = $form->formconfirm(
				$_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('MMIShippingPortEdit'),
				$text, 'confirm_port_edit', $formquestion, 'yes', 1, 350
			);
		}

		return 0;
	}

	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs, $db;

		$error = 0; // Error counter

		// Édition des frais de port d'une commande client (RM2381)
		if ($this->in_context($parameters, 'ordercard') && $action == 'confirm_port_edit' && GETPOST('confirm', 'alpha') == 'yes'
			&& getDolGlobalString('MMI_SHIPPING_PORT_EDIT') && !empty($user->rights->mmishipping->commande->port_edit)) {
			$langs->load('mmishipping@mmishipping');

			$sell = GETPOST('port_sell_ht', 'alphanohtml');
			$buy = GETPOST('port_buy_ht', 'alphanohtml');
			$sell = ($sell !== '' && $sell !== null) ?(float) price2num($sell) :null;
			$buy = ($buy !== '' && $buy !== null) ?(float) price2num($buy) :null;

			// Commentaire client de la ligne de port (RM2559) : null = inchangé
			$desc = trim((string) GETPOST('port_desc', 'restricthtml'));
			list($cur_line, $cur_count) = mmishipping::object_shipping_line($object);
			if ($cur_line !== null)
				$desc = ($desc === trim((string) $cur_line->desc)) ?null :$desc; // vide = commentaire retiré
			else
				$desc = ($desc === '') ?null :$desc; // création : vide = libellé par défaut

			// Sans ligne existante, un commentaire seul ne suffit pas à créer la ligne
			if ($sell === null && $buy === null && ($cur_line === null || $desc === null)) {
				setEventMessages($langs->trans('MMIShippingPortEditNothingToDo'), null, 'warnings');
			}
			else {
				$line_id = mmishipping::order_shipping_line_set($user, $object, $sell, $buy, $desc);
				if ($line_id > 0) {
					$object->fetch($object->id);
					$object->fetch_lines();

					// Régénération du PDF de la commande
					$outputlangs = $langs;
					$model = $object->model_pdf ?: getDolGlobalString('COMMANDE_ADDON_PDF', 'einstein');
					$object->generateDocument($model, $outputlangs, 0, 0, 0);

					// Événement agenda de traçabilité
					require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
					$ac = new ActionComm($db);
					$ac->type_code = 'AC_OTH_AUTO';
					$ac->code = 'AC_MMISHIPPING_PORT_EDIT';
					$ac->label = 'Frais de port modifiés sur commande '.$object->ref
						.($sell !== null ?' (vente '.price($sell).' HT)' :'').($buy !== null ?' (achat '.price($buy).' HT)' :'')
						.($desc !== null ?' (commentaire : '.dol_trunc($desc, 80).')' :'');
					$ac->datep = dol_now();
					$ac->datef = dol_now();
					$ac->percentage = -1;
					$ac->socid = $object->socid;
					$ac->authorid = $user->id;
					$ac->userownerid = $user->id;
					$ac->fk_element = $object->id;
					$ac->elementtype = 'commande';
					$ac->create($user);

					setEventMessages($langs->trans('MMIShippingPortEditDone', $object->ref), null, 'mesgs');

					// Propagation à la (aux) facture(s) liée(s)
					if (GETPOST('port_update_invoice', 'alpha')) {
						$q = $db->query("SELECT fk_target FROM ".MAIN_DB_PREFIX."element_element
							WHERE fk_source=".((int) $object->id)." AND sourcetype='commande' AND targettype='facture'");
						while ($q && ($row = $db->fetch_object($q))) {
							$invoice = new Facture($db);
							if ($invoice->fetch($row->fk_target) <= 0)
								continue;
							$invoice->fetch_lines();
							if (!mmishipping::invoice_adjustable($invoice)) {
								setEventMessages($langs->trans('MMIShippingPortEditInvoiceSkipped', $invoice->ref), null, 'warnings');
								continue;
							}
							$inv_line_id = mmishipping::invoice_shipping_line_set($user, $invoice, $sell, $buy, $desc);
							if ($inv_line_id > 0) {
								$invoice->fetch($invoice->id);
								$invoice->fetch_lines();
								$model = $invoice->model_pdf ?: getDolGlobalString('FACTURE_ADDON_PDF', 'sponge');
								$invoice->generateDocument($model, $outputlangs, 0, 0, 0);
								setEventMessages($langs->trans('MMIShippingPortEditInvoiceDone', $invoice->ref), null, 'mesgs');
							}
							else {
								setEventMessages($langs->trans('MMIShippingPortEditInvoiceError', $invoice->ref).' : '.implode(', ', mmishipping::$errors), null, 'errors');
							}
						}
					}
				}
				else {
					setEventMessages($langs->trans('MMIShippingPortEditError').' : '.implode(', ', mmishipping::$errors), null, 'errors');
				}
			}
			$action = '';
		}

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
			if (mmishipping::autoliquidation($user, $object, $commande) < 0) {
				$error = mmishipping::$error;
				$this->errors = array_merge($this->errors, mmishipping::$errors);
			}
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			//$this->errors[] = 'Error message';
			return -1;
		}
	}

	// MASS Actions

	function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;
		
		$error = 0; // Error counter
		$myvalue = 'test'; // A result value
		$print = '';
		//die();
		//var_dump($this); die();
		//var_dump(__CLASS__, get_called_class(), static::MOD_NAME, $lang);
		//$langs->load('mmishipping@mmishipping');

		if ($this->in_context($parameters, 'supplierorderlist') && getDolGlobalInt('MMISHIPPING_DF'))
		{
			//var_dump($parameters);
			//$this->results = [];
			if (!empty($user->rights->mmishipping->df->affect))
				$print .= '<option value="adresse_assign_auto">'.img_picto('', 'supplier', 'class="pictofixedwidth"').$langs->trans("MMIShippingAssignAddresses").'</option>';
			if (!empty($conf->global->MMISHIPPING_DF_ENTREPOT) && !empty($user->rights->mmishipping->df->autoliquidation))
				$print .= '<option value="receive_and_send">'.img_picto('', 'supplier', 'class="pictofixedwidth"').$langs->trans("MMIShippingSupplierOrdersReceiveAndSend").'</option>';
			//var_dump($print);
		}
		elseif ($this->in_context($parameters, 'shipmentlist') && getDolGlobalInt('MMI_SHIPPING_PREPA_MULTI'))
		{
			//var_dump($parameters);
			//$this->results = [];
			$print .= '<option value="preparationslip">'.img_picto('', 'file-pdf', 'class="pictofixedwidth"').$langs->trans("MMIShippingPreparationSlip").'</option>';
		}

		if (! $error)
		{
			$this->resprints = $print;
			return 0; // or return 1 to replace standard code
		}
		else
		{
			if (empty($this->errors))
				$this->errors[] = 'Error message';
			return -1;
		}
	}

	function doMassActions($parameters, &$object, &$action, $hookmanager)
	{	
		global $db, $user, $conf;
		
		$error = 0; // Error counter
		$myvalue = 'test'; // A result value
		$print = '';

		if (empty($massaction = $parameters['massaction']))
			return 0;
		//var_dump($parameters);
		
		if ($this->in_context($parameters, 'supplierorderlist')
			&& $massaction=='receive_and_send'
			&& !empty($conf->global->MMISHIPPING_DF) && !empty($conf->global->MMISHIPPING_DF_ENTREPOT)
			&& !empty($user->rights->mmishipping->df->autoliquidation))
		{
			foreach($parameters['toselect'] as $id) {
				$object = new CommandeFournisseur($db);
				$object->fetch($id);
				$commande = mmishipping::order_associated_to_supplier_order($id);
				//var_dump($id, $object, $commande); die();
				if (!empty($commande)) {
					if (mmishipping::autoliquidation($user, $object, $commande) < 0) {
						$error += mmishipping::$error;
						$this->errors = array_merge($this->errors, mmishipping::$errors);
					}
				}
			}
		}
		elseif ($this->in_context($parameters, 'supplierorderlist')
			&& $massaction=='adresse_assign_auto'
			&& getDolGlobalInt('MMISHIPPING_DF')
			&& !empty($user->rights->mmishipping->df->affect))
		{
			foreach($parameters['toselect'] as $id) {
				$object = new CommandeFournisseur($db);
				$object->fetch($id);
				$commande = mmishipping::order_associated_to_supplier_order($id);
				//var_dump($id, $object, $commande); die();
				if (!empty($commande)) {
					$r = mmishipping::supplier_order_shipping_address_assign($user, $object, $commande);
					var_dump($r);
				}
			}
			//die('adresse_assign_auto');
		}
		elseif ($this->in_context($parameters, 'shipmentlist')
			&& $massaction=='preparationslip'
			&& getDolGlobalInt('MMI_SHIPPING_PREPA_MULTI'))
		{
			mmishipping::preparationslipmulti($parameters['toselect']);
		}

		if (! $error)
		{
			$this->resprints = $print;
			return 0; // or return 1 to replace standard code
		}
		else
		{
			if (empty($this->errors))
				$this->errors[] = 'Error message';
			return -1;
		}
	}

	function doPreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $db, $conf;

		$error = 0; // Error counter
		$myvalue = 'test'; // A result value
		$print = '';
		
		if ($this->in_context($parameters, 'supplierorderlist') && $action=='receive_and_send' && !empty($conf->global->MMISHIPPING_DF))
		{
		}
		elseif ($this->in_context($parameters, 'supplierorderlist') && $action=='adresse_assign_auto' && !empty($conf->global->MMISHIPPING_DF))
		{
		}

		if (! $error)
		{
			$this->results = array('myreturn' => $myvalue);
			$this->resprints = $print;
			return 0; // or return 1 to replace standard code
		}
		else
		{
			$this->errors[] = 'Error message';
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

    /**
     * Semble servir à afficher des filtrer globaux
     */
    function printFieldPreListTitle($parameters, &$object, &$action, $hookmanager)
    {
        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'supplierorderlist')) {
            //var_dump($parameters);
            $print .= '<div class="inline-block">';
			$search_fk_adresse_notnull = GETPOST('search_fk_adresse_notnull');
			$print .= '&nbsp;Adresse client :';
			$print .= '<input type="checkbox" value="1" id="search_fk_adresse_notnull" name="search_fk_adresse_notnull" '.($search_fk_adresse_notnull ?' checked="checked"' :'').' /><label for="search_fk_adresse_notnull"> renseignée</label>';
			$search_fk_adresse_null = GETPOST('search_fk_adresse_null');
            $print .= '<input type="checkbox" value="1" id="search_fk_adresse_null" name="search_fk_adresse_null" '.($search_fk_adresse_null ?' checked="checked"' :'').' /><label for="search_fk_adresse_null">non renseignée</label>';
			$print .= '</div>';

            $print .= '<div class="inline-block">';
			$search_fk_entrepot_notnull = GETPOST('search_fk_entrepot_notnull');
			$print .= '&nbsp;Entrepot :';
			$print .= '<input type="checkbox" value="1" id="search_fk_entrepot_notnull" name="search_fk_entrepot_notnull" '.($search_fk_entrepot_notnull ?' checked="checked"' :'').' /><label for="search_fk_entrepot_notnull">renseigné</label>';
			$search_fk_entrepot_null = GETPOST('search_fk_entrepot_null');
            $print .= '<input type="checkbox" value="1" id="search_fk_entrepot_null" name="search_fk_entrepot_null" '.($search_fk_entrepot_null ?' checked="checked"' :'').' /><label for="search_fk_entrepot_null">non renseigné</label>';
			$print .= '</div>';
        }
    
        if (! $error)
        {
            $this->resprints = $print;
            return 0; // or return 1 to replace standard code
        }
        else
        {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    function printFieldListSearchParam($parameters, &$object, &$action, $hookmanager)
    {
        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'supplierorderlist')) {
            //var_dump($parameters);
            foreach(['search_fk_adresse_notnull', 'search_fk_adresse_null', 'search_fk_entrepot_notnull', 'search_fk_entrepot_null'] as $i)
				if (GETPOST($i))
					$print .= '&'.$i.'=1';
        }
		//die($print);
    
        if (! $error)
        {
            $this->resprints = $print;
            return 0; // or return 1 to replace standard code
        }
        else
        {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    function printFieldListWhere($parameters, &$object, &$action, $hookmanager)
    {
        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'supplierorderlist')) {
			if (GETPOST('search_fk_adresse_notnull'))
	            $print .= ' AND ef.fk_adresse IS NOT NULL';
			elseif (GETPOST('search_fk_adresse_null'))
				$print .= ' AND ef.fk_adresse IS NULL';
			if (GETPOST('search_fk_entrepot_notnull'))
				$print .= ' AND ef.fk_entrepot IS NOT NULL';
			elseif (GETPOST('search_fk_entrepot_null'))
				$print .= ' AND ef.fk_entrepot IS NULL';
            //die('coucou');
        }
    
        if (! $error)
        {
            $this->resprints = $print;
            return 0; // or return 1 to replace standard code
        }
        else
        {
            $this->errors[] = 'Error message';
            return -1;
        }
    }
}

ActionsMMIShipping::__init();
