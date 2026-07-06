<?php

/**
 * Maho
 *
 * @package    MageAustralia_CustomForms
 * @copyright  Copyright (c) 2026 Mage Australia
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Declarative equivalent of sql/customforms_setup/install-1.0.0.php.
 * Legacy install stays for BC; declarative reconciles on ./maho migrate
 * and is idempotent on installs where the tables already exist.
 *
 * IMPORTANT: unique constraints declared as UNIQUE INDEXES (not addUniqueConstraint)
 * because DBAL's diff engine treats UniqueConstraint objects and unique
 * indexes as distinct metadata - when the DB was created by a legacy CREATE
 * TABLE statement, MySQL records UNIQUE as a "unique index" (Non_unique=0)
 * and DBAL comparing that against an addUniqueConstraint declaration
 * decides they don't match and tries to drop the index. Declaring as a
 * unique index instead matches what the DB actually holds.
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;

return function (Schema $schema): void {
    $form = $schema->createTable('customform');
    $form->addColumn('form_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $form->addColumn('code', Types::STRING, ['length' => 64, 'notnull' => true, 'comment' => 'Stable machine key']);
    $form->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true, 'comment' => 'Admin-facing form name']);
    $form->addColumn('is_active', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
    $form->addColumn('store_ids', Types::STRING, ['length' => 255, 'notnull' => false, 'comment' => 'Comma-separated store ids; empty = all stores']);
    $form->addColumn('schema', Types::TEXT, ['notnull' => true, 'comment' => 'Form schema JSON: fields/types/validation/layout/logic/steps']);
    $form->addColumn('settings', Types::TEXT, ['notnull' => false, 'comment' => 'Form settings JSON: captcha/notify/success behaviour']);
    $form->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $form->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $form->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('form_id')->create(),
    );
    $form->addUniqueIndex(['code'], 'UNQ_CUSTOMFORM_CODE');
    $form->setComment('Custom Forms - form definitions');

    $sub = $schema->createTable('customform_submission');
    $sub->addColumn('submission_id', Types::INTEGER, ['unsigned' => true, 'autoincrement' => true]);
    $sub->addColumn('form_id', Types::INTEGER, ['unsigned' => true, 'notnull' => true]);
    $sub->addColumn('store_id', Types::SMALLINT, ['unsigned' => true, 'notnull' => false]);
    $sub->addColumn('customer_id', Types::INTEGER, ['unsigned' => true, 'notnull' => false, 'comment' => 'Customer id if submitted while logged in']);
    $sub->addColumn('payload', Types::TEXT, ['notnull' => true, 'comment' => 'Submitted values JSON, keyed by field key']);
    $sub->addColumn('files', Types::TEXT, ['notnull' => false, 'comment' => 'Uploaded file metadata JSON']);
    $sub->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'new']);
    $sub->addColumn('ip', Types::STRING, ['length' => 45, 'notnull' => false]);
    $sub->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
    $sub->addPrimaryKeyConstraint(
        PrimaryKeyConstraint::editor()->setUnquotedColumnNames('submission_id')->create(),
    );
    $sub->addIndex(['form_id'], 'IDX_CUSTOMFORM_SUBMISSION_FORM_ID');
    $sub->addIndex(['customer_id'], 'IDX_CUSTOMFORM_SUBMISSION_CUSTOMER_ID');
    $sub->addForeignKeyConstraint(
        'customform',
        ['form_id'],
        ['form_id'],
        ['onDelete' => 'CASCADE'],
        'FK_CUSTOMFORM_SUBMISSION_FORM_ID_CUSTOMFORM_FORM_ID',
    );
    $sub->setComment('Custom Forms - submissions');
};
