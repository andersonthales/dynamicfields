<?php

/**
 * -------------------------------------------------------------------------
 * DynamicFields plugin — AJAX: retorna subunidades filtradas por centro
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

// Requer sessão autenticada
Session::checkLoginUser();

header('Content-Type: application/json; charset=utf-8');

/** @var DBmysql $DB */
global $DB;

$centro = trim($_GET['centro'] ?? '');

if (empty($centro)) {
    echo json_encode([]);
    exit;
}

// Buscar o mapa centro → subunidades da tabela de config
$iterator = $DB->request([
    'SELECT' => ['cfg_value'],
    'FROM'   => 'glpi_plugin_dynamicfields_config',
    'WHERE'  => ['cfg_key' => 'centro_subunidade_map'],
    'LIMIT'  => 1,
]);

if ($iterator->count() === 0) {
    // Fallback: retornar todas as subunidades se o mapa não existir
    $field_iter = $DB->request([
        'SELECT' => ['dropdown_values'],
        'FROM'   => 'glpi_plugin_dynamicfields_fields',
        'WHERE'  => ['name' => 'subunidadeacadmicafield'],
    ]);
    if ($field_iter->count() > 0) {
        $row  = $field_iter->current();
        $all  = json_decode($row['dropdown_values'], true) ?? [];
        echo json_encode($all);
    } else {
        echo json_encode([]);
    }
    exit;
}

$row  = $iterator->current();
$mapa = json_decode($row['cfg_value'], true) ?? [];

// Buscar subunidades do centro solicitado
$subunidades = $mapa[$centro] ?? [];

// Se não encontrou exato, tentar match parcial (segurança)
if (empty($subunidades)) {
    foreach ($mapa as $key => $subs) {
        if (stripos($key, $centro) !== false || stripos($centro, $key) !== false) {
            $subunidades = $subs;
            break;
        }
    }
}

echo json_encode(array_values($subunidades), JSON_UNESCAPED_UNICODE);
exit;
