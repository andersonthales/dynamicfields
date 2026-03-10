<?php

/**
 * -------------------------------------------------------------------------
 * DynamicFields plugin — AJAX: retorna HTML dos campos para uma categoria
 * -------------------------------------------------------------------------
 * GET params:
 *   itemtype      Ticket | Problem | Change
 *   category_id   int
 *   item_id       int (opcional — carrega valores salvos)
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

header('Content-Type: text/html; charset=utf-8');

include_once(PLUGINDYNAMICFIELDS_DIR . '/inc/field.class.php');
include_once(PLUGINDYNAMICFIELDS_DIR . '/inc/value.class.php');

$itemtype    = in_array($_GET['itemtype'] ?? '', ['Ticket', 'Problem', 'Change'])
               ? $_GET['itemtype']
               : 'Ticket';
$category_id = (int) ($_GET['category_id'] ?? 0);
$item_id     = (int) ($_GET['item_id']     ?? 0);

$fields = PluginDynamicfieldsField::getFieldsForItemAndCategory($itemtype, $category_id);

if (empty($fields)) {
    exit; // container ficará vazio → JS irá escondê-lo
}

$saved    = $item_id > 0
            ? PluginDynamicfieldsValue::getValuesForItem($item_id, $itemtype)
            : [];

$url_subs = PLUGINDYNAMICFIELDS_WEB_DIR . '/ajax/get_subunidades.php';

PluginDynamicfieldsField::renderFields($fields, $saved, $url_subs);
