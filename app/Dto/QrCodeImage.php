<?php

namespace App\Dto;

use GdImage;

/**
 * QrCodeImage Class
 *
 * This class represents a QR Code image and encapsulates its properties such as width, height,
 * and the image resource itself. It provides methods to set and get these properties.
 */
class QrCodeImage
{

    private int $width;
    private int $height;
    private GdImage|false $image;

    /**
     * Destructor for the QrCodeImage class.
     * Ensures that the image resource is properly destroyed when the instance is disposed of.
     */
    public function __destruct()
    {
        if ($this->image) {
            imagedestroy($this->image);
        }
    }

    /**
     * Gets the width of the QR Code image.
     *
     * @return int The width of the image.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Sets the width of the QR Code image.
     *
     * @param int $width The width to set for the image.
     */
    public function setWidth(int $width): void
    {
        $this->width = $width;
    }

    /**
     * Gets the height of the QR Code image.
     *
     * @return int The height of the image.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Sets the height of the QR Code image.
     *
     * @param int $height The height to set for the image.
     */
    public function setHeight(int $height): void
    {
        $this->height = $height;
    }

    /**
     * Gets the GD image resource or false if it's not available.
     *
     * @return GdImage|false The GD image resource or false.
     */
    public function getImage(): GdImage|false
    {
        return $this->image;
    }

    /**
     * Sets the GD image resource for the QR Code image.
     *
     * @param GdImage|false $image The GD image resource to set, or false.
     */
    public function setImage(GdImage|false $image): void
    {
        $this->image = $image;
    }

}
