<?php
/**
 * Test CLI RM2381 — mmishipping::order_shipping_line_set / invoice_shipping_line_set
 *
 * ⚠ Modifie réellement la commande ciblée : ENV DE DEV UNIQUEMENT.
 *
 * Usage (php7.4, depuis ce dossier) :
 *   php7.4 port_edit_test.php order <order_id> <sell_ht|-> <buy_ht|->
 *   php7.4 port_edit_test.php invoice_check <invoice_id>
 *   php7.4 port_edit_test.php invoice <invoice_id> <sell_ht|-> <buy_ht|->
 *
 * "-" = null (valeur inchangée). Affiche l'état avant/après et vérifie que le
 * statut de l'objet n'a pas bougé.
 */
if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}
if (php_sapi_name() !== 'cli') {
	die("CLI only\n");
}

require __DIR__.'/../../../../master.inc.php';
dol_include_once('custom/mmishipping/class/mmishipping.class.php');

global $db, $user, $langs;
$user = new User($db);
$user->fetch(1);
$user->getrights();

function num($v)
{
	return ($v === '-' || $v === null || $v === '') ?null :(float) $v;
}

function order_state(Commande $order)
{
	list($line, $count) = mmishipping::object_shipping_line($order);
	return sprintf(
		"  %s : statut=%d total_ht=%s | port: n=%d%s",
		$order->ref, $order->statut, price($order->total_ht), $count,
		$line ?sprintf(" line=%d subprice=%s pa_ht=%s qty=%s", $line->id, price($line->subprice), price($line->pa_ht), $line->qty) :''
	);
}

$mode = isset($argv[1]) ?$argv[1] :'';
$id = isset($argv[2]) ?(int) $argv[2] :0;

if ($mode == 'order' && $id) {
	$sell = num(isset($argv[3]) ?$argv[3] :'-');
	$buy = num(isset($argv[4]) ?$argv[4] :'-');

	$order = new Commande($db);
	if ($order->fetch($id) <= 0) {
		die("Commande $id introuvable\n");
	}
	$order->fetch_lines();
	$statut_before = (int) $order->statut;
	echo "AVANT\n".order_state($order)."\n";

	$ret = mmishipping::order_shipping_line_set($user, $order, $sell, $buy);
	echo "order_shipping_line_set() = $ret".($ret < 0 ?' | '.implode(', ', mmishipping::$errors) :'')."\n";

	$order2 = new Commande($db);
	$order2->fetch($id);
	$order2->fetch_lines();
	echo "APRES\n".order_state($order2)."\n";

	echo ((int) $order2->statut === $statut_before) ?"OK statut inchangé ($statut_before)\n" :"ERREUR statut modifié : $statut_before → ".$order2->statut."\n";
	exit($ret > 0 && (int) $order2->statut === $statut_before ?0 :1);
}

if (($mode == 'invoice' || $mode == 'invoice_check') && $id) {
	$invoice = new Facture($db);
	if ($invoice->fetch($id) <= 0) {
		die("Facture $id introuvable\n");
	}
	$invoice->fetch_lines();
	$adj = mmishipping::invoice_adjustable($invoice);
	printf("  %s : statut=%d paye=%s total_ht=%s | adjustable=%s\n", $invoice->ref, $invoice->statut, $invoice->paye, price($invoice->total_ht), $adj ?'OUI' :'NON');
	if ($mode == 'invoice_check') {
		exit(0);
	}

	if (!$adj) {
		die("Facture non ajustable, on ne touche pas\n");
	}
	$sell = num(isset($argv[3]) ?$argv[3] :'-');
	$buy = num(isset($argv[4]) ?$argv[4] :'-');
	$statut_before = (int) $invoice->statut;

	$ret = mmishipping::invoice_shipping_line_set($user, $invoice, $sell, $buy);
	echo "invoice_shipping_line_set() = $ret".($ret < 0 ?' | '.implode(', ', mmishipping::$errors) :'')."\n";

	$invoice2 = new Facture($db);
	$invoice2->fetch($id);
	$invoice2->fetch_lines();
	list($line, $count) = mmishipping::object_shipping_line($invoice2);
	printf("APRES  %s : statut=%d total_ht=%s | port: n=%d%s\n", $invoice2->ref, $invoice2->statut, price($invoice2->total_ht), $count,
		$line ?sprintf(" line=%d subprice=%s pa_ht=%s", $line->id, price($line->subprice), price($line->pa_ht)) :'');
	echo ((int) $invoice2->statut === $statut_before) ?"OK statut inchangé ($statut_before)\n" :"ERREUR statut modifié : $statut_before → ".$invoice2->statut."\n";
	exit($ret > 0 && (int) $invoice2->statut === $statut_before ?0 :1);
}

die("Usage : port_edit_test.php order|invoice|invoice_check <id> [sell|-] [buy|-]\n");
