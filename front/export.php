<?php

/**
 * -------------------------------------------------------------------------
 * DynamicFields plugin for GLPI — CSV Export
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$itemtype = $_GET['itemtype'] ?? 'Ticket';
if (!in_array($itemtype, ['Ticket', 'Problem', 'Change'])) {
    $itemtype = 'Ticket';
}

PluginDynamicfieldsValue::exportCSV($itemtype);
