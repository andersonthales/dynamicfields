<?php

/**
 * -------------------------------------------------------------------------
 * DynamicFields plugin for GLPI — Field add / edit form
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

// Force-load classes (safety net in case autoloader hasn't fired yet)
include_once(PLUGINDYNAMICFIELDS_DIR . '/inc/field.class.php');
include_once(PLUGINDYNAMICFIELDS_DIR . '/inc/category.class.php');
include_once(PLUGINDYNAMICFIELDS_DIR . '/inc/value.class.php');
include_once(PLUGINDYNAMICFIELDS_DIR . '/inc/menu.class.php');

$field = new PluginDynamicfieldsField();

// ── POST actions ────────────────────────────────────────────────────────────

if (isset($_POST['purge'])) {
    $field->check((int) $_POST['id'], UPDATE);
    $field->delete($_POST, true);
    Html::redirect(PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.php');
}

if (isset($_POST['add'])) {
    $field->check(-1, UPDATE);
    $newID = $field->add($_POST);
    Html::redirect(PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.form.php?id=' . (int) $newID);
}

if (isset($_POST['update'])) {
    $field->check((int) $_POST['id'], UPDATE);
    $field->update($_POST);
    Html::redirect(PLUGINDYNAMICFIELDS_WEB_DIR . '/front/field.form.php?id=' . (int) $_POST['id']);
}

// ── GET: display form ───────────────────────────────────────────────────────

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $field->getFromDB($id);
}

Html::header(
    PluginDynamicfieldsField::getTypeName(1),
    $_SERVER['PHP_SELF'],
    'config',
    'PluginDynamicfieldsMenu',
    'field'
);

$field->showForm($id);

Html::footer();
