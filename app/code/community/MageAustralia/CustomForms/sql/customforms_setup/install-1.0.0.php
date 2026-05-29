<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Schema-first form builder: two JSON-backed tables.
 *   - customform            one row per form; the `schema` JSON is the single
 *                           source of truth (fields, types, validation, layout,
 *                           widths, conditional logic, steps).
 *   - customform_submission one row per submission; `payload` JSON keyed by
 *                           field key. No EAV-style fields/options/lines tables.
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */

use Maho\Db\Ddl\Table;

$installer = $this;
$installer->startSetup();

$conn = $installer->getConnection();

// --- customform -----------------------------------------------------------
$formTable = $installer->getTable('customforms/form');
if (!$conn->isTableExists($formTable)) {
    $table = $conn->newTable($formTable)
        ->addColumn('form_id', Table::TYPE_INTEGER, null, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ], 'Form Id')
        ->addColumn('code', Table::TYPE_TEXT, 64, [
            'nullable' => false,
        ], 'Form Code (stable machine key)')
        ->addColumn('name', Table::TYPE_TEXT, 255, [
            'nullable' => false,
        ], 'Admin-facing form name')
        ->addColumn('is_active', Table::TYPE_SMALLINT, null, [
            'nullable' => false,
            'default'  => 1,
        ], 'Is Active')
        ->addColumn('store_ids', Table::TYPE_TEXT, 255, [
            'nullable' => true,
        ], 'Comma-separated store ids (empty = all stores)')
        ->addColumn('schema', Table::TYPE_TEXT, '2M', [
            'nullable' => false,
        ], 'Form schema (JSON): fields/types/validation/layout/logic/steps')
        ->addColumn('settings', Table::TYPE_TEXT, '64K', [
            'nullable' => true,
        ], 'Form settings (JSON): captcha/notify/success behaviour')
        ->addColumn('created_at', Table::TYPE_DATETIME, null, [
            'nullable' => true,
        ], 'Created At')
        ->addColumn('updated_at', Table::TYPE_DATETIME, null, [
            'nullable' => true,
        ], 'Updated At')
        ->addIndex(
            $installer->getIdxName('customforms/form', ['code'], Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE),
            ['code'],
            ['type' => Maho\Db\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE],
        )
        ->setComment('Custom Forms - form definitions');
    $conn->createTable($table);
}

// --- customform_submission -------------------------------------------------
$subTable = $installer->getTable('customforms/submission');
if (!$conn->isTableExists($subTable)) {
    $table = $conn->newTable($subTable)
        ->addColumn('submission_id', Table::TYPE_INTEGER, null, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary'  => true,
        ], 'Submission Id')
        ->addColumn('form_id', Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => false,
        ], 'Form Id')
        ->addColumn('store_id', Table::TYPE_SMALLINT, null, [
            'unsigned' => true,
            'nullable' => true,
        ], 'Store Id')
        ->addColumn('customer_id', Table::TYPE_INTEGER, null, [
            'unsigned' => true,
            'nullable' => true,
        ], 'Customer Id (if submitted while logged in)')
        ->addColumn('payload', Table::TYPE_TEXT, '2M', [
            'nullable' => false,
        ], 'Submitted values (JSON, keyed by field key)')
        ->addColumn('files', Table::TYPE_TEXT, '64K', [
            'nullable' => true,
        ], 'Uploaded file metadata (JSON)')
        ->addColumn('status', Table::TYPE_TEXT, 32, [
            'nullable' => false,
            'default'  => 'new',
        ], 'Submission status')
        ->addColumn('ip', Table::TYPE_TEXT, 45, [
            'nullable' => true,
        ], 'Submitter IP')
        ->addColumn('created_at', Table::TYPE_DATETIME, null, [
            'nullable' => true,
        ], 'Created At')
        ->addIndex(
            $installer->getIdxName('customforms/submission', ['form_id']),
            ['form_id'],
        )
        ->addIndex(
            $installer->getIdxName('customforms/submission', ['customer_id']),
            ['customer_id'],
        )
        ->addForeignKey(
            $installer->getFkName('customforms/submission', 'form_id', 'customforms/form', 'form_id'),
            'form_id',
            $formTable,
            'form_id',
            Table::ACTION_CASCADE,
        )
        ->setComment('Custom Forms - submissions');
    $conn->createTable($table);
}

$installer->endSetup();
