<?php

namespace Drupal\mz_sms_notification\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mz_sms_notification\Service\SmsDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure the active gateway and Orange Madagascar credentials.
 */
class SettingsForm extends ConfigFormBase {

  const SETTINGS = 'mz_sms_notification.settings';

  public function __construct(protected SmsDispatcher $dispatcher) {}

  public static function create(ContainerInterface $container) {
    $instance = new static($container->get('mz_sms_notification.dispatcher'));
    $instance->setConfigFactory($container->get('config.factory'));
    return $instance;
  }

  public function getFormId() {
    return 'mz_sms_notification_settings';
  }

  protected function getEditableConfigNames() {
    return [self::SETTINGS];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::SETTINGS);

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Actually deliver SMS'),
      '#description' => $this->t('Uncheck to disable delivery globally — dispatches resolve to a no-op.'),
      '#default_value' => (bool) $config->get('enabled'),
    ];

    $form['auto_dispatch_on_insert'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-dispatch when an sms node is inserted'),
      '#default_value' => (bool) $config->get('auto_dispatch_on_insert'),
    ];

    $form['default_gateway'] = [
      '#type' => 'select',
      '#title' => $this->t('Active gateway'),
      '#options' => $this->dispatcher->listGateways(),
      '#default_value' => (string) ($config->get('default_gateway') ?: 'log'),
    ];

    $form['force_country_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default country prefix'),
      '#description' => $this->t('Used to normalise numbers without a leading "+" (e.g. <code>+261</code> for Madagascar).'),
      '#default_value' => (string) $config->get('force_country_code'),
      '#size' => 8,
    ];

    $form['log_payloads'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log payloads and responses'),
      '#description' => $this->t('Disable on production if messages contain PII.'),
      '#default_value' => (bool) $config->get('log_payloads'),
    ];

    $form['orange_madagascar'] = [
      '#type' => 'details',
      '#title' => $this->t('Orange Madagascar gateway'),
      '#open' => TRUE,
      '#description' => $this->t('Register an application on <a href="@url" target="_blank">Orange Developer</a> to obtain the client id / secret, and activate the SMS API product. The sender address must be an MSISDN owned by your Orange app, in <em>tel:+261XXXXXXXXX</em> format.', [
        '@url' => 'https://developer.orange.com/',
      ]),
    ];

    $orange = (array) ($config->get('orange_madagascar') ?: []);
    $form['orange_madagascar']['base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API base URL'),
      '#default_value' => (string) ($orange['base_url'] ?? 'https://api.orange.com'),
    ];
    $form['orange_madagascar']['token_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OAuth2 token path'),
      '#default_value' => (string) ($orange['token_path'] ?? '/oauth/v3/token'),
    ];
    $form['orange_madagascar']['sms_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SMS endpoint path'),
      '#description' => $this->t('Use <code>{senderAddress}</code> to substitute the URL-encoded sender MSISDN.'),
      '#default_value' => (string) ($orange['sms_path'] ?? '/smsmessaging/v1/outbound/{senderAddress}/requests'),
    ];
    $form['orange_madagascar']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client id'),
      '#default_value' => (string) ($orange['client_id'] ?? ''),
      '#size' => 80,
    ];
    $form['orange_madagascar']['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client secret'),
      '#default_value' => (string) ($orange['client_secret'] ?? ''),
      '#size' => 80,
    ];
    $form['orange_madagascar']['sender_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sender address'),
      '#description' => $this->t('E.g. <code>tel:+261340000000</code>. A bare <code>+261340000000</code> is also accepted.'),
      '#default_value' => (string) ($orange['sender_address'] ?? ''),
    ];
    $form['orange_madagascar']['sender_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sender name (optional)'),
      '#description' => $this->t('Short alphanumeric alias, subject to approval by Orange.'),
      '#default_value' => (string) ($orange['sender_name'] ?? ''),
    ];
    $form['orange_madagascar']['request_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('HTTP request timeout (seconds)'),
      '#min' => 1,
      '#max' => 120,
      '#default_value' => (int) ($orange['request_timeout'] ?? 15),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(self::SETTINGS)
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('auto_dispatch_on_insert', (bool) $form_state->getValue('auto_dispatch_on_insert'))
      ->set('default_gateway', (string) $form_state->getValue('default_gateway'))
      ->set('force_country_code', trim((string) $form_state->getValue('force_country_code')))
      ->set('log_payloads', (bool) $form_state->getValue('log_payloads'))
      ->set('orange_madagascar', [
        'base_url'        => trim((string) $form_state->getValue(['orange_madagascar', 'base_url'])),
        'token_path'      => trim((string) $form_state->getValue(['orange_madagascar', 'token_path'])),
        'sms_path'        => trim((string) $form_state->getValue(['orange_madagascar', 'sms_path'])),
        'client_id'       => trim((string) $form_state->getValue(['orange_madagascar', 'client_id'])),
        'client_secret'   => trim((string) $form_state->getValue(['orange_madagascar', 'client_secret'])),
        'sender_address'  => trim((string) $form_state->getValue(['orange_madagascar', 'sender_address'])),
        'sender_name'     => trim((string) $form_state->getValue(['orange_madagascar', 'sender_name'])),
        'request_timeout' => (int)  $form_state->getValue(['orange_madagascar', 'request_timeout']),
      ])
      ->save();

    // Force-refresh the cached OAuth2 token on next send.
    \Drupal::state()->delete('mz_sms_notification.orange_madagascar.token');
    \Drupal::state()->delete('mz_sms_notification.orange_madagascar.token_expires_at');

    parent::submitForm($form, $form_state);
  }

}
