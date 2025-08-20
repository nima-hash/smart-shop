<?php

namespace App\Controller\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class UploaderHelper
{
    private $uploadsPath;
    private $slugger;

    public function __construct(string $uploadsPath, SluggerInterface $slugger)
    {
        $this->uploadsPath = $uploadsPath;
        $this->slugger = $slugger;
    }

    public function uploadProductImage(UploadedFile $uploadedFile, $fileSavePath , bool $isThumbnail = false): string
    {
        $destination = $this->uploadsPath . '/' . $fileSavePath;
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($uploadedFile->getMimeType(), $allowedMimeTypes, true)) {
          throw new \InvalidArgumentException('Invalid file type.');
        }
        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);

        $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

        if (!is_dir($destination)) {
            mkdir($destination, 0775, true);
        }

        $uploadedFile->move(
            $destination,
            $newFilename
        );

        // If thumbnail, create resized version
        if ($isThumbnail) {
            $this->createThumbnail(
                $destination.'/'.$newFilename,
                $destination.'/thumb_'.$newFilename,
                300, // width
                300  // height
            );
        }



        return $newFilename;
    }

    private function createThumbnail(string $sourcePath, string $targetPath, int $maxWidth, int $maxHeight): void
    {
        [$width, $height, $type] = getimagesize($sourcePath);

        // Calculate new size keeping aspect ratio
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

        // Create image from source depending on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                throw new \Exception('Unsupported image type.');
        }

        // Resize
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumbnail, $targetPath, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumbnail, $targetPath);
                break;
            case IMAGETYPE_GIF:
                imagegif($thumbnail, $targetPath);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($thumbnail, $targetPath);
                break;
        }

        // Cleanup
        imagedestroy($thumbnail);
        imagedestroy($source);
    }

    public function getTargetDirectory(): string
    {
        return $this->uploadsPath.'/product_images';
    }

    public function deleteProductImage(string $filename): void
    {
        $filePath = $this->getTargetDirectory().'/'.$filename;
        if(file_exists($filePath))
        {
            unlink($filePath);
        }
    }
}