<?php

/**
 * -------------------------------------------------------------------------
 * DynamicFields plugin for GLPI — AJAX: get fields for category
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

$itemtype    = $_GET['itemtype']    ?? 'Ticket';
$category_id = (int) ($_GET['category_id'] ?? 0);
$item_id     = (int) ($_GET['item_id']     ?? 0);
$is_existing = (bool) ($_GET['is_existing'] ?? false);

if (!in_array($itemtype, ['Ticket', 'Problem', 'Change'], true)) {
    $itemtype = 'Ticket';
}

$fields = PluginDynamicfieldsField::getFieldsForItemAndCategory($itemtype, $category_id);

if (empty($fields)) {
    echo '';
    exit;
}

$saved = [];
if ($item_id > 0) {
    $item = new $itemtype();
    if ($item->can($item_id, READ)) {
        $saved = PluginDynamicfieldsValue::getValuesForItem($item_id, $itemtype);
    } else {
        Html::displayRightError();
    }
}
$url_subs = Plugin::getWebDir('dynamicfields') . '/ajax/get_subunidades.php';

PluginDynamicfieldsField::renderFields($fields, $saved, $url_subs, $is_existing);
