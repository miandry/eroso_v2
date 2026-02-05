<?php

namespace Drupal\mz_email;

use Drupal\user\Entity\User;
/**
 * Class DefaultService.
 */
//require composer require phpmailer/phpmailer
class DefaultService {

  /**
   * Constructs a new DefaultService object.
   */
  public function __construct() {

  }
  public function sendMail($sentTo , $subject , $body ){
    $config = \Drupal::config('mz_email.config');
    
    if(is_string($sentTo) && $config->get('is_not_smtp')){
      $headers = 'From: sender@example.com' . "\r\n" .
                 'Reply-To: sender@example.com' . "\r\n" .
                 'X-Mailer: PHP/' . phpversion();
  
      if (mail($sentTo, $subject, $body, $headers)) {
        \Drupal::messenger()->addMessage(t('Email simple sent successfully.'));
      } else {
        \Drupal::messenger()->addMessage(t('There was a problem sending your email simple.'), 'error');
        return false;
      }
      return true ;
    }


    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
       
        $mail = new \PHPMailer\PHPMailer\PHPMailer();       
        // Set mailer to use SMTP
        $mail->isSMTP();
        
        // SMTP settings for Gmail
      
       // $mail->Host = 'smtp.gmail.com';
        //$mail->Username = 'boutamiandrilala@gmail.com';
        //$mail->Password = 'Bota@#009856';
        
        $mail->Host = $config->get('host');
        $mail->Username = $config->get('username'); // Your Gmail address
        $mail->Password = $config->get('password'); // Your Gmail password

        $mail->SMTPAuth = true;
        $mail->SMTPSecure = $config->get('secure');
        $mail->Port = $config->get('port');
        //$to = \Drupal::config('system.site')->get('mail');
        // Sender and recipient details
        $mail->setFrom($config->get('sender'), $config->get('sender_label'));
        $mail->addAddress($config->get('sender'), $config->get('sender_label'));
        if(is_string($sentTo)){
            if(!$this->verificationMail($sentTo)){
                \Drupal::logger('mz_email')->error("E-mail is not valid ".$sentTo);
                return false ;
            }
            $mail->addAddress($sentTo, '');
        }
        if(is_array($sentTo)){
            foreach($sentTo as $to){
                if(!$this->verificationMail($to)){
                    \Drupal::logger('mz_email')->error("E-mail is not valid ".$sentTo);
                    return false ;
                }
                $mail->addAddress($to, 'Recipient');
            }
        }
        // Email subject and body
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Send the email
        if(!$mail->send()) {
            $message = 'Message could not be sent.';
            \Drupal::logger('mz_email')->error( $message);
            $message =  'Mailer Error: ' . $mail->ErrorInfo;
            \Drupal::logger('mz_email')->error( $message);
            return false ;
        } else {
            \Drupal::logger('mz_email')->info('Message has been sent');
            \Drupal::messenger()->addMessage('Email Message has been sent');  
            return true ;
        } 

    } else {
        // PHPMailer library is not available
        // Handle the situation accordingly
        $message =  'PHPMailer library is not available.';
        \Drupal::logger('mz_email')->error($message);
        return false ;
    }
    return false ;
}
function verificationMail($email){
    list($user, $domain) = explode('@', $email);
    if (!checkdnsrr($domain, 'MX')) {
        \Drupal::logger('mz_email')->error("Email Domain does not have an MX record.");
        return false ;
    }
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        \Drupal::logger('mz_email')->error("E-mail is not valid");
        return false ;
    
    }
    return true ;

}

public function txtRegister($account){
    $config = \Drupal::config('user.mail');
    $body = $config->get('register_no_approval_required.body');
    $subject = $config->get('register_no_approval_required.subject');
    
    $token_service = \Drupal::token();
     // Prepare the replacements for the tokens.
    $data = ['user' => $account];
    $options = ['clear' => TRUE];
     // Replace the tokens in the email template.
    $email_body = $token_service->replace($body, $data, $options);
    $email_subject = $token_service->replace($subject, $data, $options);
    return  [ 'subject' =>   $email_subject,'body' => $email_body]  ;
}
public function txtForgetPass($email){
    $account = $this->findUserByEmail($email);
    if(!is_object($account)){
        \Drupal::messenger()->addError(t('The user %email does not exist.', ['%email' => $to]));
         return false ;
    }
    $config = \Drupal::config('user.mail');
    $body = $config->get('password_reset.body');
    $subject = $config->get('password_reset.subject');
    
    $token_service = \Drupal::token();
     // Prepare the replacements for the tokens.
    $data = ['user' => $account];
    $options = ['clear' => TRUE];
     // Replace the tokens in the email template.
    $email_body = $token_service->replace($body, $data, $options);
    $email_subject = $token_service->replace($subject, $data, $options);
    return  [ 'subject' =>   $email_subject,'body' => $email_body]  ;
}
function findUserByEmail($email) {
    // Query for the user by email.
    $user_query = \Drupal::entityQuery('user')
      ->condition('mail', $email)
      ->range(0, 1); // Limit to 1 result
    
    // Execute the query and get the user IDs.
    $uids = $user_query->execute();
  
    // If a user is found, load and return the user entity.
    if (!empty($uids)) {
      $uid = reset($uids); // Get the first (and only) UID.
      return User::load($uid); // Load and return the user entity.
    }
  
    // Return FALSE if no user was found.
    return FALSE;
  }

}
