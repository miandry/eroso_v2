<?php

namespace Drupal\accounting_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\Display\EntityFormDisplay;
use Drupal\Core\Entity\Display\EntityViewDisplay;

class AccountingModuleController extends ControllerBase {

  /**
   * Setup page for the Accounting Module.
   */
  public function setupPage() {
    $output = '<h2>Setup Accounting Module</h2>';
    $output .= '<p>Click the button below to create the Invoice, Expense, Payment, and Customer content types and their fields.</p>';
    $output .= '<a href="/admin/config/accounting_module/setup/batch" class="button">Run Setup</a>';
    return [
      '#markup' => $output,
    ];
  }

  /**
   * Batch processing setup.
   */
  public function runBatch() {
    $batch = [
      'title' => 'Setting up Accounting Module...',
      'operations' => [
        ['\Drupal\accounting_module\Controller\AccountingModuleController::batchCreateContentTypes', []],
        ['\Drupal\accounting_module\Controller\AccountingModuleController::batchCreateFields', []],
        ['\Drupal\accounting_module\Controller\AccountingModuleController::batchCreateTaxonomies', []],
      ],
      'finished' => '\Drupal\accounting_module\Controller\AccountingModuleController::batchFinished',
    ];

    batch_set($batch);
    return batch_process(Url::fromRoute('<current>')->toString());
  }

    /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    \Drupal::messenger()->addMessage('Accounting Module setup completed.');
    $response = new RedirectResponse(Url::fromRoute('accounting_module.setup')->toString());
    $response->send();
  }


  /**
   * Batch operation: Create Content Types.
   */
  public static function batchCreateContentTypes(&$context) {
    self::createContentType('invoice', 'Invoice', 'Invoices for clients');
    self::createContentType('expense', 'Expense', 'Business Expenses');
    self::createContentType('payment', 'Payment', 'Payments received or made');
    self::createContentType('customer', 'Customer', 'Client Details');
    $context['message'] = 'Content Types created.';
  }

  /**
   * Batch operation: Create Fields.
   */
  public static function batchCreateFields(&$context) {
    // Invoice Fields
    self::addField('invoice', 'field_invoice_date', 'datetime', 'Invoice Date');
    self::addField('invoice', 'field_total_amount', 'decimal', 'Total Amount');

    // Expense Fields
    self::addField('expense', 'field_expense_date', 'datetime', 'Expense Date');
    self::addField('expense', 'field_expense_amount', 'decimal', 'Expense Amount');

    // Payment Fields
    self::addField('payment', 'field_payment_date', 'datetime', 'Payment Date');
    self::addField('payment', 'field_payment_amount', 'decimal', 'Payment Amount');

    // Customer Fields
    self::addField('customer', 'field_customer_name', 'string', 'Customer Name');
    self::addField('customer', 'field_customer_email', 'email', 'Customer Email');

    $context['message'] = 'Fields created.';
  }

  /**
   * Batch operation: Create Taxonomies.
   */
  public static function batchCreateTaxonomies(&$context) {
    self::createTaxonomy('payment_methods', ['Bank Transfer', 'Cash', 'Credit Card', 'Mobile Payment', 'Cheque']);
    self::createTaxonomy('expense_categories', ['Office Supplies', 'Travel', 'Utilities', 'Salary', 'Maintenance']);
    $context['message'] = 'Taxonomies created.';
  }

  /**
   * Create Content Type Programmatically.
   */
  private static function createContentType($type, $name, $description) {
    if (!NodeType::load($type)) {
      $type = NodeType::create([
        'type' => $type,
        'name' => $name,
        'description' => $description,
        'locked' => FALSE,
        'status' => TRUE,
      ]);
      $type->save();
    }
  }

  /**
   * Add Field to Content Type.
   */
  private static function addField($content_type, $field_name, $field_type, $label) {
    if (!FieldStorageConfig::loadByName('node', $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $field_type,
        'cardinality' => 1,
        'settings' => [],
      ])->save();

      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => $content_type,
        'label' => $label,
        'required' => FALSE,
      ])->save();
    }
  }

  /**
   * Create Taxonomy Vocabulary Programmatically.
   */
  private static function createTaxonomy($vocab_name, $terms) {
    if (!Vocabulary::load($vocab_name)) {
      $vocab = Vocabulary::create([
        'vid' => $vocab_name,
        'name' => ucfirst(str_replace('_', ' ', $vocab_name)),
      ]);
      $vocab->save();

      foreach ($terms as $term) {
        Term::create([
          'vid' => $vocab_name,
          'name' => $term,
        ])->save();
      }
    }
  }

}
