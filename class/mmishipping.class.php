<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.dispatch.class.php';
require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';

class mmishipping
{
	protected $error;
	
	public static function supplier_order_shipping_address($user, $commande_fourn, $commande)
	{
		global $db;
		
		// Contact livraison défini dans la commande
		if (!empty($contacts=$commande->liste_contact(-1, 'external', 0, 'SHIPPING'))) {
			return $contacts[0];
		}

		// Recherche parmi les contacts du client
		$customer = new Societe($db);
		$customer->fetch($commande->socid);
		$contacts = $customer->contact_array_objects();
		//var_dump($contacts); die();
		// Un seul contact => on prend
		if (count($contacts)==1) {
			foreach($contacts as $contact)
				return $contact;
		}
		// Aucun contact => on créé
		elseif(count($contacts) == 0) {
			if (!empty($contactId = $customer->create_individual($user))) {
				$contact = new Contact($db);
				$contact->fetch($contactId);
				$name = !empty($customer->name_alias) ?$customer->name_alias :$customer->nom;
				$namee = explode(' ', $name);
				$contact->lastname = array_shift($namee);
				$contact->firstname = implode(' ', $namee);
				$contact->update($contactId, $user);
				return $contact;
			}
		}
	}
	
	public static function supplier_order_shipping_address_assign($user, $commande_fourn, $commande)
	{
		if (!empty($contact = static::supplier_order_shipping_address($user, $commande_fourn, $commande))) {
			//var_dump($contact); die();
			// Assignation contact livraison commande à la commande fournisseur
			$commande_fourn->array_options['options_fk_adresse'] = is_array($contact) ?$contact['id'] :(is_object($contact) ?$contact->id :'');
			//var_dump($object->array_options);
			//var_dump($object);
			$commande_fourn->update($user);
		}
	}

	public static function order_associated_to_supplier_order($id)
	{
		global $db;
		
		$sql = "SELECT e.fk_source id
			FROM ".MAIN_DB_PREFIX."element_element e
			WHERE e.`targettype` LIKE 'order_supplier' AND e.`fk_target`='".$id."' AND e.sourcetype='commande'";
		//echo '<p>'.$sql.'</p>';
		$resql = $db->query($sql);
		//var_dump($resql);
		if ($obj = $db->fetch_object($resql)) {
			$commande = new Commande($db);
			$commande->fetch($obj->id);
			//var_dump($obj->id);
			return $commande;
		}
	}

	// expédition auto depuis réception
	public static function commande_fourn_to_reception(User $user, CommandeFournisseur $commande_fourn, $validate=true)
	{
		//var_dump($order); die();
		//var_dump($user); die();
		global $conf, $db, $langs;

		if (! $user->rights->reception->creer)
			return;
		if (empty($user->rights->mmishipping->df->autoliquidation))
			return;
		
		if (empty($commande_fourn))
			return;
		if (!empty($conf->global->MMISHIPPING_DF_ENTREPOT))
			$warehouse_id = $conf->global->MMISHIPPING_DF_ENTREPOT;
		
		// Code récupéré depuis fourn/commande/dispatch.php
		$error = 0;
		$notrigger = 0;

		$db->begin();

		$objectsrc = $commande_fourn;
		$origin = 'supplierorder';
		$origin_id = $commande_fourn->id;
		$date_delivery = '';

		$object = new Reception($db);

		$object->origin = $origin;
		$object->origin_id = $origin_id;
		$object->fk_project = $commande_fourn->fk_project;
		$object->weight = NULL;
		$object->trueHeight = NULL;
		$object->trueWidth = NULL;
		$object->trueDepth = NULL;
		$object->size_units = NULL;
		$object->weight_units = NULL;

		// On va boucler sur chaque ligne du document d'origine pour completer objet reception
		// avec info diverses + qte a livrer

		$object->socid = $objectsrc->socid;
		$object->ref_supplier = 'contremarque';
		$object->model_pdf = 'rouget';
		$object->date_delivery = $date_delivery; // Date delivery planed
		$object->fk_delivery_address = $objectsrc->fk_delivery_address;
		$object->shipping_method_id = '';
		$object->tracking_number = '';
		$object->note_private = '';
		$object->note_public = '';
		$object->fk_incoterms = $commande_fourn->fk_incoterms;
		$object->location_incoterms = $commande_fourn->location_incoterms;

		foreach ($objectsrc->lines as $line) {
			// Uniquement produit bien spécifié
			if (empty($line->fk_product))
				continue;
			// Uniquement produit "physique" (pas service)
			if ($line->product_type != 0)
				continue;

			if (!empty($conf->global->STOCK_CALCULATE_ON_RECEPTION) || !empty($conf->global->STOCK_CALCULATE_ON_RECEPTION_CLOSE)) {
				$ret = $object->addline($warehouse_id, $line->id, $line->qty, NULL, '', '', '', '', $line->subprice, 'MU');
			} else {
				$ret = $object->addline($warehouse_id, $line->id, $line->qty, NULL, '', '', '', '');
			}
			if ($ret < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
				$error++;
			}
		}

		if (!$error) {
			$ret = $object->create($user); // This create reception (like Odoo picking) and line of receptions. Stock movement will when validating reception.

			if ($ret <= 0) {
				setEventMessages($object->error, $object->errors, 'errors');
				$error++;
			}
		}

		if (!$error) {
			$ret = $object->valid($user);
			$object->setClosed();

			if ($ret <= 0) {
				setEventMessages($object->error, $object->errors, 'errors');
				$error++;
			}
		}

		if (!$error) {
			$ret = $commande_fourn->setStatus($user, CommandeFournisseur::STATUS_RECEIVED_COMPLETELY);

			if ($ret <= 0) {
				setEventMessages($commande_fourn->error, $commande_fourn->errors, 'errors');
				$error++;
			}
		}

		if (!$error) {
			$db->commit();
			return $object;
		} else {
			$db->rollback();
		}
	}

	// Création expédition
	public static function commande_fourn_to_shipping(User $user, CommandeFournisseur $commande_fourn, $validate=true)
	{
		//var_dump($order); die();
		//var_dump($user); die();
		global $conf, $db, $langs;

		if (! $user->rights->expedition->creer)
			return;
		if (empty($user->rights->mmishipping->df->autoliquidation))
			return;
		
		if (empty($commande_fourn))
			return;
		if (!empty($conf->global->MMISHIPPING_DF_ENTREPOT))
			$warehouse_id = $conf->global->MMISHIPPING_DF_ENTREPOT;
		
		// Recherche commande liée
		$sql = "SELECT e.fk_source id
			FROM ".MAIN_DB_PREFIX."element_element e
			WHERE e.`targettype` LIKE 'order_supplier' AND e.`fk_target`='".$commande_fourn->id."' AND e.sourcetype='commande'";
		//echo '<p>'.$sql.'</p>';
		$resql = $db->query($sql);
		//var_dump($resql);
		if ($obj = $db->fetch_object($resql)) {
			$order = new Commande($db);
			$order->fetch($obj->id);
		}
		else {
			return;
		}

		$error = 0;

		$db->begin();

		$object = new Expedition($db);
		
		$origin = 'commande';
		$origin_id = $order->id;
		$objectsrc = $order;
		$date_delivery = date('Y-m-d');
		$mode_pdf = '';

		$object->origin = $origin;
		$object->origin_id = $origin_id;
		$object->fk_project = $objectsrc->fk_project;

		// $object->weight				= 0;
		// $object->sizeH				= 0;
		// $object->sizeW				= 0;
		// $object->sizeS				= 0;
		// $object->size_units = 0;
		// $object->weight_units = 0;

		$object->socid = $objectsrc->socid;
		$object->ref_customer = $objectsrc->ref_client;
		$object->model_pdf = $mode_pdf;
		$object->date_delivery = $date_delivery; // Date delivery planed
		$object->shipping_method_id	= $objectsrc->shipping_method_id;
		$object->tracking_number = '';
		$object->note_private = $objectsrc->note_private;
		$object->note_public = $objectsrc->note_public;
		$object->fk_incoterms = $objectsrc->fk_incoterms;
		$object->location_incoterms = $objectsrc->location_incoterms;

		// Parcours produits commande
		foreach ($commande_fourn->lines as $line) {
			//var_dump($conf->productbatch->enabled, $line);
			if (! $line->fk_product)
				continue;

			$product = new Product($db);
			$product->fetch($line->fk_product);
			if (! $product->id)
				continue;
			// Kit alimentaire => ne pas expédier ça bug
			//if (!empty($product->array_options['options_compose']))
			//	continue;

			// Product shippable (not service, etc.)
			if ($product->type != 0)
				continue;

			foreach($order->lines as $cline) {
				if($cline->fk_product != $line->fk_product)
					continue;
				$ret = $object->addline($warehouse_id, $cline->id, $cline->qty);
				if ($ret < 0) {
					setEventMessages($object->error, $object->errors, 'errors');
					$error++;
				}
			}
		}

		if (!$error) {
			$ret = $object->create($user); // This create shipment (like Odoo picking) and lines of shipments. Stock movement will be done when validating shipment.
			if ($ret <= 0) {
				setEventMessages($object->error, $object->errors, 'errors');
				$error++;
			}
		}

		// Validation
		if (!$error) {
			if ($validate) {
				$result = $object->valid($user);
				if ($result<0) {
					setEventMessages($object->error, $object->errors, 'errors');
					dol_syslog(get_class().' ::' .$object->error.implode(',',$object->errors), LOG_ERR);
					$error++;
				}
				$result = $object->setClosed();
				if ($result<0) {
					setEventMessages($object->error, $object->errors, 'errors');
					dol_syslog(get_class().' ::' .$object->error.implode(',',$object->errors), LOG_ERR);
					$error++;
				}
			}
		}

		// Génération PDF
		if (!$error) {
			// Retrieve everything
			$object->fetch($object->id);

			$docmodel = 'rouget';
			$object->generateDocument($docmodel, $langs);
		}

		// OK ou rollback
		if (!$error) {
			$db->commit();
			
			return $object;
			//var_dump($expe);
		} else {
			$db->rollback();
		}
	}
}
