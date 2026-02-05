<?php

namespace Drupal\mz_generator_theme;

use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;
/**
 * Class GenerateService.
 */
class GenerateThemeService extends AbstractGenerateThemeService{

  /**
   * Constructs a new GenerateService object.
   */
  public function __construct() {

  }
 
 
  public function process($node){
       if($node->bundle() == "theme"){
        $this->generateThemeSite($node);
       }
  }
  
  public function buildLibrariesOfTheme($path_libraires,$node){
    $content_lib = $this->getYamlToArray($path_libraires) ;
    $field_library = $node->get('field_library')->getValue();
    if (!empty($field_library)) {
        $theme_name = $node->label();
        foreach ($field_library as $key => $field_item) {
            $nid = $field_item['target_id'];
            $library_paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->load($nid);  
            $library_node = $library_paragraph->field_library->entity ;
            if(is_object($library_node)){
                $library_name = $library_node->label(); 
                $content_lib = $this->position($content_lib ,$library_name,$library_paragraph);
                $content_lib = $this->dependencies($content_lib,$theme_name,$library_name,$library_paragraph);
                // css
                $content_lib = $this->lib_css($content_lib,$library_name,$library_paragraph);
                // js
                $content_lib = $this->lib_js($content_lib,$library_name,$library_paragraph);
            }

        }      
    }
    $content_lib = $this->getArrayToYaml($content_lib ,$path_libraires) ;
  }
  public function updateThemeSite($node)
  {
    $site_name = $node->label();
    $directory =  DRUPAL_ROOT . "/themes/custom/" . $site_name;
    $shop_new_lib = $directory . "/" . $site_name . ".libraries.yml";
    $this->buildLibrariesOfTheme($shop_new_lib,$node);
  }

  public function generateThemeSite($node)
  {
      $site_name = $node->label();
      $module_handler = \Drupal::service('module_handler');
      $path = $module_handler->getModule('mz_generator_theme')->getPath();
      $theme_default = DRUPAL_ROOT . "/" . $path . "/data/theme";
      $directory =  DRUPAL_ROOT . "/themes/custom/" . $site_name;
      $fileSystem = \Drupal::service('file_system');
      if (!is_dir($directory)) {
          if ($fileSystem->mkdir($directory, 0777, true) === false) {
              \Drupal::messenger()->addMessage(t('Failed to create directory ' . $directory), 'error');
              return false;
          }
      } else {
          @chmod($directory, 0777);
      }
      $this->recurse_copy($theme_default, $directory);

      //***** GENERATE theme.info.yml */
      $shop_info = $directory . "/theme.info.yml.txt";

      //replace token
      $content_settings = file_get_contents($shop_info, FILE_USE_INCLUDE_PATH);
      $content_settings = str_replace("{{site_name}}", $site_name, $content_settings);
  
      //base theme
      $base_theme = "claro";
      if($this->is_field_ready($node,'field_base')){$base_theme = $node->field_base->value ;}
      $content_settings = $this->replaceToken($content_settings,"{{base_theme}}",$base_theme);


      if ( file_put_contents($shop_info, $content_settings) === false) {
         \Drupal::messenger()->addMessage('Failed to write file ' . $shop_info);
          return false;
      }
      $shop_new_info = $directory . "/" . $site_name . ".info.yml";
      if (!rename($shop_info, $shop_new_info)) {
          \Drupal::messenger()->addMessage(t('Failed to create file ' . $directory), 'error');
      }


       //***** GENERATE theme.libraries.yml */

      $shop_lib = $directory . "/theme.libraries.yml.txt";
      $shop_new_lib = $directory . "/" . $site_name . ".libraries.yml";
      if (!rename($shop_lib, $shop_new_lib)) {
          \Drupal::messenger()->addMessage(t('Failed to create file ' . $directory), 'error');
      }


      $this->buildLibrariesOfTheme($shop_new_lib,$node);
  }




    // generate in libraires folder in insert theme_library
    public function generateFolderLibraries($node){
        $field_library_css_content = $node->get('field_library_css_content')->getValue();
        $field_library_css = $node->get('field_library_css')->getValue();
        $lib_name = $node->label();
        if (!empty($field_library_css)) {
            foreach ($field_library_css as $key => $field_item) {        
                $file_name = basename($field_item['value']);
                $content_lib = $field_library_css_content[$key]['value'];
                $lib_directory =  DRUPAL_ROOT . "/libraries/" .$lib_name.'/css' ;
                $fileSystem = \Drupal::service('file_system');
                if (!is_dir($lib_directory)) {
                    if ($fileSystem->mkdir($lib_directory, 0777, true) === false) {
                        \Drupal::messenger()->addMessage(t('Failed to create directory ' . $lib_directory), 'error');
                        return false;
                    }
                }
                @chmod($lib_directory, 0777);
                $theme_lib_file =  $lib_directory."/".$file_name;
  
                if (file_put_contents($theme_lib_file,   $content_lib) === false) {
                    \Drupal::messenger()->addMessage(t('Failed to create file js' . $theme_lib_file), 'error');
                }  
                //$results_css[] =   $content ;          
            } 
        }
        $field_library_js_content = $node->get('field_library_js_content')->getValue();
        $field_library_js = $node->get('field_library_js')->getValue();
        if (!empty($field_library_js)) {
            foreach ($field_library_js as $key => $field_item) {        
                $file_name = basename($field_item['value']);
                $content_lib = $field_library_js_content[$key]['value'];
                $lib_directory =  DRUPAL_ROOT . "/libraries/" .$lib_name.'/js' ;
                $fileSystem = \Drupal::service('file_system');
                if (!is_dir($lib_directory)) {
                    if ($fileSystem->mkdir($lib_directory, 0777, true) === false) {
                        \Drupal::messenger()->addMessage(t('Failed to create directory ' . $lib_directory), 'error');
                        return false;
                    }
                }
                @chmod($lib_directory, 0777);
                $theme_lib_file =  $lib_directory."/".$file_name;
  
                if (file_put_contents($theme_lib_file,   $content_lib) === false) {
                    \Drupal::messenger()->addMessage(t('Failed to create file js' . $theme_lib_file), 'error');
                }  
                //$results_css[] =   $content ;          
            } 
        }

     
        //   $field_library_js_content = $node->get('field_library_js_content')->getValue();
   }

   // add library theme_library
  public function saveLibraryInText($node){
    $library_name = $node->label();
    $field_library_css =  $node->get('field_library_css')->getValue();
    $results_css = [] ;
    $results_js = [];
    if (!empty($field_library_css)) {
        foreach ($field_library_css as $key => $field_item) {
            $content = file_get_contents($field_item['value'], FILE_USE_INCLUDE_PATH);
            $results_css[] =   $content ;          
        }

        $node->field_library_css_content = $results_css ;
   
    }
    $field_library_js = $node->get('field_library_js')->getValue();
    if (!empty($field_library_js)) {
        foreach ($field_library_js as $key => $field_item) {
            $content = file_get_contents($field_item['value'], FILE_USE_INCLUDE_PATH);
            $results_js[] =   $content ;          
        }
        $node->field_library_js_content = $results_js ;

    }
   // $node->field_library_js_content->value = 'sdadsds';
    return  $node ;

  }
}
