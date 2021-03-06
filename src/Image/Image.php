<?php

namespace Utopia\Image;

use Exception;
use Imagick;
use ImagickDraw;
use ImagickPixel;

class Image
{
    private Imagick $image;

    private int $width;

    private int $height;

    private int $cornerRadius = 0;

    private int $borderWidth = 0;

    private String $borderColor = '';

    /**
     * @param string $data
     *
     * @throws Exception
     */
    public function __construct($data)
    {
        $this->image = new Imagick();

        $this->image->readImageBlob($data);

        $this->width = $this->image->getImageWidth();
        $this->height = $this->image->getImageHeight();
    }

    /**
     * @param int $width
     * @param int $height
     *
     * @return Image
     *
     * @throws \Throwable
     */
    public function crop(int $width, int $height)
    {
        $originalAspect = $this->width / $this->height;

        if (empty($width)) {
            $width = intval($height * $originalAspect);
        }

        if (empty($height)) {
            $height = intval($width / $originalAspect);
        }

        if (empty($height) && empty($width)) {
            $height = $this->height;
            $width = $this->width;
        }

        if ($this->image->getImageFormat() == 'GIF') {
            $this->image = $this->image->coalesceImages();

            foreach ($this->image as $frame) {
                $frame->cropThumbnailImage($width, $height);
            }

            $this->image->deconstructImages();
        } else {
            $this->image->cropThumbnailImage($width, $height);
        }
        $this->height = $height;
        $this->width = $width;
        return $this;
    }

    /**
     * @param integer $borderWidth The size of the border in pixels
     * @param string $borderColor The color of the border in hex format
     * 
     * @return Image
     *
     * @throws \ImagickException
     */
    public function setBorder(int $borderWidth, string $borderColor): self
    {
        $this->borderWidth = $borderWidth;
        $this->borderColor = $borderColor;
        
        if(!empty($this->cornerRadius)) return $this;
        $this->image->borderImage($borderColor, $borderWidth, $borderWidth);

        return $this;
    }

    /**
      * Applies rounded corners, background to an image
      * @param integer $cornerRadius: The radius for the corners
      * @param string $background: A valid HEX string representing the background color
      * @return Image $image: The processed image
      *
      * @throws \ImagickException
      */
      public function setBorderRadius(int $cornerRadius): self
      {
        $mask = new Imagick();
        $mask->newImage($this->width, $this->height, new ImagickPixel('transparent'), 'png');

        $rectwidth = ($this->borderWidth>0?($this->width-($this->borderWidth+1)):$this->width-1);
		$rectheight = ($this->borderWidth>0?($this->height-($this->borderWidth+1)):$this->height-1);
 

        $shape = new ImagickDraw();
        $shape->setFillColor(new ImagickPixel('black'));
        $shape->roundRectangle($this->borderWidth, $this->borderWidth, $rectwidth, $rectheight, $cornerRadius, $cornerRadius);
        
        $mask->drawImage($shape);
        $this->image->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);

        if($this->borderWidth > 0) {
            $bc = new ImagickPixel();
            $bc->setColor($this->borderColor);

            $strokeCanvas = new Imagick();
            $strokeCanvas->newImage($this->width, $this->height, new ImagickPixel('transparent'),'png');

            $shape2 = new ImagickDraw();
            $shape2->setFillColor(new ImagickPixel('transparent'));
            $shape2->setStrokeWidth($this->borderWidth);
            $shape2->setStrokeColor($bc);
            $shape2->roundRectangle($this->borderWidth, $this->borderWidth, $rectwidth, $rectheight, $cornerRadius, $cornerRadius);
            
            $strokeCanvas->drawImage($shape2);
            $strokeCanvas->compositeImage($this->image, Imagick::COMPOSITE_DEFAULT, 0,0);
            
            $this->image = $strokeCanvas;
        }
        return $this;
      }
 
      /**
     * @param float opacity The opacity of the image
     * 
     * @return Image
     *
     * @throws \ImagickException
     */
    public function setOpacity(float $opacity): self
    {
        if((empty($opacity) && $opacity !== 0) || $opacity == 1) {
            return $this;
        }
        $this->image->setImageAlpha($opacity);
        return $this;
    }

    /**
     * Rotates an image to $degree degree
     * @param integer $degree: The amount to rotate in degrees
     * @return Image $image: The rotated image
     *
     * @throws \ImagickException
     */
    public function setRotation(int $degree): self
    {
        if (empty($degree) || $degree == 0) {
            return $this;
        }
        
        $this->image->rotateImage('transparent', $degree);
        
        return $this;
    }


    /**
     * @param mixed $color
     *
     * @return Image
     *
     * @throws \Throwable
     */
    public function setBackground($color)
    {
        $this->image->setImageBackgroundColor($color);
        $this->image = $this->image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

        return $this;
    }

    /**
     * Output.
     *
     * Prints manipulated image.
     *
     * @param string $type
     * @param int    $quality
     *
     * @return false|null|string
     *
     * @throws Exception
     */
    public function output(string $type, int $quality = 75)
    {
        return $this->save(null, $type, $quality);
    }

    /**
     * @param string $path
     * @param $type
     * @param int $quality
     *
     * @return false|null|string
     *
     * @throws Exception
     */
    public function save(string $path = null, string $type = '', int $quality = 75)
    {
        // Create directory with write permissions
        if (null !== $path && !\file_exists(\dirname($path))) {
            if (!@\mkdir(\dirname($path), 0755, true)) {
                throw new Exception('Can\'t create directory ' . \dirname($path));
            }
        }

        switch ($type) {
            case 'jpg':
            case 'jpeg':
                $this->image->setImageCompressionQuality($quality);

                $this->image->setImageFormat('jpg');
                break;

            case 'gif':
                $this->image->setImageFormat('gif');
                break;

            case 'webp':
                try {
                    $this->image->setImageFormat('webp');
                } catch (\Throwable$th) {
                    $signature = $this->image->getImageSignature();
                    $temp = '/tmp/temp-' . $signature . '.' . \strtolower($this->image->getImageFormat());
                    $output = '/tmp/output-' . $signature . '.webp';

                    // save temp
                    $this->image->writeImages($temp, true);

                    // convert temp
                    \exec("cwebp -quiet -metadata none -q $quality $temp -o $output");

                    $data = \file_get_contents($output);

                    //load webp
                    if (empty($path)) {
                        return $data;
                    } else {
                        \file_put_contents($path, $data, LOCK_EX);
                    }

                    $this->image->clear();
                    $this->image->destroy();

                    //delete webp
                    \unlink($output);
                    \unlink($temp);

                    return;
                }

                break;

            case 'png':
                /* Scale quality from 0-100 to 0-9 */
                $scaleQuality = \round(($quality / 100) * 9);

                /* Invert quality setting as 0 is best, not 9 */
                $invertScaleQuality = intval(9 - $scaleQuality);

                $this->image->setImageCompressionQuality($invertScaleQuality);

                $this->image->setImageFormat('png');
                break;

            default:
                throw new Exception('Invalid output type given');
                break;
        }

        if (empty($path)) {
            return $this->image->getImagesBlob();
        } else {
            $this->image->writeImages($path, true);
        }

        $this->image->clear();
        $this->image->destroy();
    }

    /**
     * @param int $newHeight
     *
     * @return int
     */
    protected function getSizeByFixedHeight(int $newHeight): int
    {
        $ratio = $this->width / $this->height;
        $newWidth = $newHeight * $ratio;

        return intval($newWidth);
    }

    /**
     * @param int $newWidth
     *
     * @return int
     */
    protected function getSizeByFixedWidth(int $newWidth): int
    {
        $ratio = $this->height / $this->width;
        $newHeight = $newWidth * $ratio;

        return intval($newHeight);
    }
}
