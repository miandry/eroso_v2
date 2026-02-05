<?php

namespace Drupal\mz_generator_theme;

use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;
/**
 * Class GenerateService.
 */
class AbstractGenerateThemeService {

  /**
   * Constructs a new GenerateService object.
   */
  public function __construct() {

  }
  public function replaceToken($content,$token,$value){
    return str_replace($token, $value, $content);
  }

   /** run in devel \Drupal::service('mz_generator_theme.default')->setStorageThemeList(); **/
   public function setStorageThemeList(){
    $allowed_values = FieldStorageConfig::loadByName('node', 'field_base');
    $allowed_values->setSetting('allowed_values_function','mz_generator_theme_allowed_values_field_base_function' );
    $allowed_values->save();
  }
  public function getYamlToArray($filePath){
    try {
        // Specify the YAML file path
       // $filePath = $path.'/'.$file_name;
    
        // Parse the YAML file to an array
        $array = Yaml::parseFile($filePath);
    
        // Display the array
        return $array ;
    } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
        \Drupal::messenger()->addMessage("Unable to parse the YAML string: %s", $e->getMessage());
        return false ;
    }
    return false ;
    
  }
  public function getArrayToYaml($array,$filePath){
            // Create a new Dumper instance
            $dumper = new Dumper();
            // Convert array to YAML
            $yamlContent = $dumper->dump($array, 2);

            // Write YAML content to file
            if (file_put_contents($filePath,  $yamlContent) === false) {
                \Drupal::messenger()->addMessage('Failed to write file yaml ' .  $filePath);
               return false;
            }
            return true;

  }
  public function recurse_copy($src, $dst)
  {
      $fileSystem = \Drupal::service("file_system");
      if (!is_dir($dst)) {
          if ($fileSystem->mkdir($dst, 0777, true) === false) {
              \Drupal::logger('mz_generator_site')->error("Failed to create directory " . $dst);
           //   $this->is_not_ready = true;
              return false;
          }
      }
      $dir = opendir($src);
      @mkdir($dst);
      while (false !== ($file = readdir($dir))) {
          if ($file != "." && $file != "..") {
              if (is_dir($src . "/" . $file)) {
                  if (@chmod($src . "/" . $file, 0777) === false) {
                      \Drupal::logger('mz_generator_site')->error(
                          "Failed to change permission of  folder " .
                              $src .
                              "/" .
                              $file
                      );
                  }
                  if (
                      $fileSystem->mkdir($dst . "/" . $file, 0777, true) ===
                      false
                  ) {
                      \Drupal::logger('mz_generator_site')->error(
                          "Failed to create directory " . $src . "/" . $file
                      );
                      return false;
                  }
                  $this->recurse_copy($src . "/" . $file, $dst . "/" . $file);
              } else {
                  copy($src . "/" . $file, $dst . "/" . $file);
                  if (@chmod($dst . "/" . $file, 0777) === false) {
                      $file_path = $dst . "/" . $file;
                      \Drupal::logger('mz_generator_site')->error(
                          "Failed to change permission file " . $file_path
                      );
                  }
              }
          }
      }
      closedir($dir);
      return true;
  }
  public function is_field_ready($entity, $field) {
    $bool = FALSE;
    if (is_object($entity) && $entity->hasField($field)) {
      $field_value = $entity->get($field)->getValue();
      if (!empty($field_value)) {
        $bool = TRUE;
      }
    }
    return $bool;
  }
  public function position($content_lib,$library_name,$library_paragraph){
    $field_position = $library_paragraph->field_position->value; 
    if( $field_position == 1){
        $content_lib[$library_name]['header'] = true ;
    }
    return $content_lib ;
  }
  public function dependencies($content_lib,$theme_name,$library_name,$library_paragraph){
    $field_dependencies = $library_paragraph->field_dependencies->getValue(); 
    if (!empty($field_dependencies)) {
        foreach ($field_dependencies as $field) {
            $id = $field['target_id'] ;
            $library_dep = \Drupal::entityTypeManager()->getStorage('node')->load($id);  
            $lib_name_dep = $library_dep->label();
            if (!in_array($theme_name.'/'.$lib_name_dep,$content_lib[$library_name]['dependencies'])) {
              $content_lib[$library_name]['dependencies'][] = $theme_name.'/'.$lib_name_dep;
            }
        }
    }
    return   $content_lib ;
  }
  public function lib_css($content_lib,$library_name,$library_paragraph){
    $library_node = $library_paragraph->field_library->entity ;
    $field_library_css =  $library_node->get('field_library_css')->getValue();
    if (!empty($field_library_css)) {
        foreach ($field_library_css as $key =>  $lib_item) {
            $path = parse_url($lib_item['value'], PHP_URL_PATH);
            $file_name  = basename($path);
                $content_lib[$library_name]['css'] = [
                        'theme' => [
                            "/libraries/{$library_name}/css/{$file_name}" => []
                        ]
                     ];

        }
    }
    return $content_lib ;
  }
  public function lib_js($content_lib,$library_name,$library_paragraph){
    $library_node = $library_paragraph->field_library->entity ;
    $field_library_js =  $library_node->get('field_library_js')->getValue();
    if (!empty($field_library_js)) {
        foreach ($field_library_js as $key =>  $lib_item) {
            $path = parse_url($lib_item['value'], PHP_URL_PATH);
            $file_name  = basename($path);
            $content_lib[$library_name]['js'] = [
                        "/libraries/{$library_name}/js/{$file_name}" => []
                ];
        }
    }
    return $content_lib ;
  }
 
}
