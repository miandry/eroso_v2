<?php

namespace Drupal\mz_email\TwigExtension;


/**
 * Class DefaultTwigExtension.
 */
class DefaultTwigExtension extends \Twig_Extension {

        
   /**
    * {@inheritdoc}
    */
    public function getTokenParsers() {
      return [];
    }

   /**
    * {@inheritdoc}
    */
    public function getNodeVisitors() {
      return [];
    }

   /**
    * {@inheritdoc}
    */
    public function getFilters() {
      return [];
    }

   /**
    * {@inheritdoc}
    */
    public function getTests() {
      return [];
    }

   /**
    * {@inheritdoc}
    */
    public function getFunctions() {
      return [
        new \Twig_SimpleFunction('mz_email',['Drupal\mz_email\TwigExtension\DefaultTwigExtension', 'mz_email_twig']),
       
      ];
    }
    // mz_email('miandrilala9@yahoo.fr','test contact','hello body <h1>GOOD </h1>');
    public static function mz_email_twig($sentTo, $subject,  $body){
       return \Drupal::service('mz_email.default')->sendMail($sentTo , $subject , $body );
    }
   /**
    * {@inheritdoc}
    */
    public function getOperators() {
      return [];
    }

   /**
    * {@inheritdoc}
    */
    public function getName() {
      return 'mz_crud.twig.extension';
    }
   
}
