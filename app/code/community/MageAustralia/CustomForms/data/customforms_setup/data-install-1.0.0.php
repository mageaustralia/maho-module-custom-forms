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
 * Allowlist the frontend form block for the {{block type="customforms/form"}}
 * CMS directive. Without this, Maho's block-directive allowlist silently
 * blocks the snippet (renders empty). Equivalent to adding it under
 * System > Permissions > Blocks.
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */

$installer = $this;
$installer->startSetup();

$conn  = $installer->getConnection();
$table = $installer->getTable('admin/permission_block');

if ($conn->isTableExists($table)) {
    $exists = $conn->fetchOne(
        $conn->select()->from($table, 'block_id')->where('block_name = ?', 'customforms/form'),
    );
    if (!$exists) {
        $conn->insert($table, ['block_name' => 'customforms/form', 'is_allowed' => 1]);
    }
}

$installer->endSetup();
