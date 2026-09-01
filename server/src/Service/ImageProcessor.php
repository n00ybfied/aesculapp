<?php
declare(strict_types=1);
namespace App\Service;
use Symfony\Component\HttpFoundation\File\UploadedFile;
final class ImageProcessor {
 public function saveProfileJpeg(UploadedFile $image,string $target):bool { return $this->resize($image,$target,400,400,'image/jpeg'); }
 public function saveAdminImage(UploadedFile $image,string $target):bool { return $this->resize($image,$target,1400,100000,'image/jpeg'); }
 private function resize(UploadedFile $image,string $target,int $maxWidth,int $maxHeight,string $output):bool {
  $source=match($image->getMimeType()){'image/jpeg'=>@imagecreatefromjpeg($image->getPathname()),'image/png'=>@imagecreatefrompng($image->getPathname()),'image/webp'=>@imagecreatefromwebp($image->getPathname()),default=>false}; if($source===false)return false;
  $width=imagesx($source);$height=imagesy($source);$scale=min(1,$maxWidth/$width,$maxHeight/$height);$targetWidth=max(1,(int)round($width*$scale));$targetHeight=max(1,(int)round($height*$scale));$canvas=imagecreatetruecolor($targetWidth,$targetHeight);imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));imagecopyresampled($canvas,$source,0,0,0,0,$targetWidth,$targetHeight,$width,$height);$saved=imagejpeg($canvas,$target,82);imagedestroy($canvas);imagedestroy($source);return $saved;
 }
}
