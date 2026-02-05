<?php

namespace Drupal\mz_email\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SMTPForm.
 */
class SMTPForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 's_m_t_p_form';
  }
    /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      's_m_t_p_form.config',
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config("mz_email.config");
    $form['intro'] = [
      '#markup' => '<pre>\Drupal::service("mz_email.default")->sendMail($sentTo , $subject , $body ); </pre>',
    ];
    $form['host'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Host'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' =>  ($config->get('host')) ? $config->get('host') : null ,
      '#description' => $this->t('Example : smtp.gmail.com'),     
      '#weight' => '0',
      '#required' => TRUE,
    ];
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' =>  ($config->get('username')) ? $config->get('username') : null ,
      '#description' => $this->t('Example : boutamiandrilala@gmail.com'),  
      '#weight' => '0',
      '#required' => TRUE
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => ($config->get('password')) ? $config->get('password') : null ,
      '#weight' => '0',
      '#required' => FALSE 
    ];
    $form['sender_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mail Sender Label'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => ($config->get('sender_label')) ? $config->get('sender_label') : "Test mail form mz_email" ,
      '#weight' => '0',
      '#required' => TRUE,
    ];
    $form['sender'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mail Sender'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => ($config->get('sender')) ? $config->get('sender') : null ,
      '#weight' => '0',
      '#required' => TRUE,
    ];
    $form['secure'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SMTPSecure'),
      '#maxlength' => 20,
      '#size' => 20,
      '#default_value' => ($config->get('secure')) ? $config->get('secure') : 'tls',
      '#weight' => '0',
      '#required' => TRUE,
    ];
    $form['port'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Port'),
      '#maxlength' => 20,
      '#size' => 20,
      '#default_value' => ($config->get('port')) ? $config->get('port') : '587',
      '#weight' => '0',
      '#required' => TRUE,

    ];
    $form['is_not_smtp'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable SMTP'),
      '#maxlength' => 20,
      '#size' => 20,
      '#default_value' => ($config->get('is_not_smtp')) ? $config->get('is_not_smtp') : false ,
      '#weight' => '0'

    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    $form['submit1'] = [
      '#type' => 'submit',
      '#value' => $this->t('Test email'),
      '#submit' => ['::submitTestEmail']
    ];
  
    return $form;
  }
  public function submitTestEmail(array &$form, FormStateInterface $form_state) {
     $body = "<p>Body Test MZ Email </p>";
     $sentTo = "miandrilala9@yahoo.fr";
     $subject = "Test Mz Email";
    \Drupal::service('mz_email.default')->sendMail($sentTo , $subject , $body );
  } 
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValues() as $key => $value) {
      // @TODO: Validate fields.
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->configFactory()->getEditable("mz_email.config")
      ->set('host', $form_state->getValue('host'))
      ->set('username', $form_state->getValue('username'))
      ->set('password', $form_state->getValue('password'))
      ->set('sender', $form_state->getValue('sender'))
      ->set('sender_label', $form_state->getValue('sender_label'))
      ->set('secure', $form_state->getValue('secure'))
      ->set('port', $form_state->getValue('port'))
      ->set('is_not_smtp', $form_state->getValue('is_not_smtp'))
      ->save();
  }

}
