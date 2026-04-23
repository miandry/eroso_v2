<?php

namespace Drupal\mz_sms_notification\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mz_sms_notification\Service\SmsDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Quick ad-hoc SMS sender, useful for validating credentials.
 *
 * Bypasses the sms-node outbox and calls the active gateway directly.
 */
class TestSendForm extends FormBase {

  public function __construct(protected SmsDispatcher $dispatcher) {}

  public static function create(ContainerInterface $container) {
    return new static($container->get('mz_sms_notification.dispatcher'));
  }

  public function getFormId() {
    return 'mz_sms_notification_test_send';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $gw = $this->dispatcher->getActiveGateway();
    $form['info'] = [
      '#markup' => $this->t('<p>Active gateway: <strong>@label</strong> (<code>@id</code>). Global delivery is <strong>@state</strong>.</p>', [
        '@label' => $gw ? $gw->getLabel() : '—',
        '@id'    => $gw ? $gw->getId() : '—',
        '@state' => $this->dispatcher->isEnabled() ? 'enabled' : 'disabled',
      ]),
    ];

    $form['to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipient phone'),
      '#description' => $this->t('E.164 format (e.g. +261340000000).'),
      '#required' => TRUE,
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      '#rows' => 3,
      '#default_value' => 'Test SMS from MZ SMS Notification.',
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send now'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $result = $this->dispatcher->sendRaw(
      (string) $form_state->getValue('to'),
      (string) $form_state->getValue('message'),
    );
    if ($result->success) {
      $this->messenger()->addStatus($this->t('Message accepted: @m (provider ref: @r)', [
        '@m' => $result->message,
        '@r' => $result->providerMessageId ?: '—',
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Send failed: @m', ['@m' => $result->message]));
    }
  }

}
