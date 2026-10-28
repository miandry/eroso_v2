<?php

namespace Drupal\mz_api_integration\Service;

/**
 * Réduit la taille des images avant envoi à l'IA vision (latence + coût).
 */
class ImageUploadOptimizer {

  /**
   * @return array{0: string, 1: string}
   */
  public function optimizeForVision(string $binary, string $mime, int $max_dim = 1280, int $jpeg_quality = 82): array {
    $mime = $this->normalizeMime($mime);

    if (!function_exists('imagecreatefromstring') || !function_exists('imagescale')) {
      return [$binary, $mime];
    }

    // Déjà petite : éviter un re-encodage inutile.
    if (strlen($binary) <= 450000 && $mime === 'image/jpeg') {
      $size = @getimagesizefromstring($binary);
      if (is_array($size) && max((int) $size[0], (int) $size[1]) <= $max_dim) {
        return [$binary, $mime];
      }
    }

    $image = @imagecreatefromstring($binary);
    if ($image === FALSE) {
      return [$binary, $mime];
    }

    $width = imagesx($image);
    $height = imagesy($image);
    if ($width < 1 || $height < 1) {
      imagedestroy($image);
      return [$binary, $mime];
    }

    $longest = max($width, $height);
    if ($longest > $max_dim) {
      $ratio = $max_dim / $longest;
      $new_w = max(1, (int) round($width * $ratio));
      $new_h = max(1, (int) round($height * $ratio));
      $scaled = imagescale($image, $new_w, $new_h, IMG_BILINEAR_FIXED);
      imagedestroy($image);
      if ($scaled === FALSE) {
        return [$binary, $mime];
      }
      $image = $scaled;
    }

    $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
    if ($canvas === FALSE) {
      imagedestroy($image);
      return [$binary, $mime];
    }

    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
    imagedestroy($image);

    ob_start();
    $ok = imagejpeg($canvas, NULL, max(60, min(92, $jpeg_quality)));
    $optimized = ob_get_clean();
    imagedestroy($canvas);

    if (!$ok || !is_string($optimized) || $optimized === '') {
      return [$binary, $mime];
    }

    return [$optimized, 'image/jpeg'];
  }

  protected function normalizeMime(string $mime): string {
    $mime = strtolower(trim($mime));
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (in_array($mime, $allowed, TRUE)) {
      return $mime;
    }
    return 'image/jpeg';
  }

}
