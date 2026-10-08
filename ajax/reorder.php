<?php
include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$order = $input['order'] ?? [];
if (empty($order) || !is_array($order)) {
    echo json_encode(['success' => false]);
    exit;
}
global $DB;
foreach ($order as $rank => $id) {
    $id = (int) $id;
    if ($id <= 0) continue;
    $DB->update('glpi_plugin_dynamicfields_fields', ['ranking' => $rank], ['id' => $id]);
}
echo json_encode(['success' => true]);
