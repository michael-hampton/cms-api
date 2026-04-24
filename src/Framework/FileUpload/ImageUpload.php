<?php

namespace App\Framework\FileUpload;

use Exception;

class ImageUpload extends FileUpload
{
    public function __construct(array $file, string $uploadPath = 'uploads/images')
    {
        parent::__construct($file, $uploadPath);
        $this->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    public function resize(int $width, ?int $height = null, string $directory = 'thumbnails'): ?string
    {
        $originalPath = $this->store();
        if (!$originalPath) {
            return null;
        }

        $height = $height ?: $width; // Square if height not provided

        $info = getimagesize($originalPath);
        $mime = $info['mime'];

        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($originalPath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($originalPath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($originalPath);
                break;
            default:
                throw new Exception("Unsupported image type: {$mime}");
        }

        $thumb = imagecreatetruecolor($width, $height);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        $thumbPath = $this->uploadPath . '/' . $directory . '/' . basename($originalPath);
        $thumbDir = dirname($thumbPath);

        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($thumb, $thumbPath, 90);
                break;
            case 'image/png':
                imagepng($thumb, $thumbPath);
                break;
            case 'image/gif':
                imagegif($thumb, $thumbPath);
                break;
        }

        imagedestroy($source);
        imagedestroy($thumb);

        return $thumbPath;
    }
}