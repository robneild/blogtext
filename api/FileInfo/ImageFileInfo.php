<?php
#########################################################################################
#
# Copyright 2010-2015  Maya Studios (http://www.mayastudios.com)
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#########################################################################################

namespace MSCL\FileInfo;

use Exception;

/**
 * This class determines width, height and type of images. Works both on local and remote images. Only
 * necessary information are read from the images. Thus using this class is usually a lot faster than calls
 * to "getimagesize()" or "imagecreatefrom...()", as those need to download the whole image file.
 */
class ImageFileInfo extends AbstractFileInfo
{
    const CLASS_NAME = 'ImageFileInfo';

    const TYPE_JPEG = 0;
    const TYPE_PNG = 1;
    const TYPE_GIF = 2;

    private static $SUPPORTED_FORMAT_COUNT = 3;

    private static $JPEG_HEADER = "\xFF\xD8";
    private static $JPEG_HEADER_LEN = 18; // FFD8 + see http://en.wikipedia.org/wiki/JPEG_File_Interchange_Format

    private static $PNG_HEADER = "\x89PNG\r\n\x1A\n";
    private static $PNG_HEADER_LEN = 24; // 8 bytes header + 4 bytes chunk length + 4 bytes "IHDR" + 2 * 4 byte for width and height

    private static $GIF_HEADER_v89 = "GIF89a";
    private static $GIF_HEADER_v87 = "GIF87a";
    private static $GIF_HEADER_LEN = 10; // GIF89a + 2 bytes width + 2 bytes height

    private $type = null;
    private $width = null;
    private $height = null;

    protected function __construct($filePath, $cacheDate = null)
    {
        // determines file info - see "handle_data()" and "finish_initialization()"
        parent::__construct($filePath, $cacheDate);
    }

    protected function finishInitialization()
    {
        if ($this->type === null)
        {
            throw new FileInfoFormatException("Could not determine image type.", $this->getFilePath(), $this->isRemoteFile());
        }

        if ($this->width === null)
        {
            throw new FileInfoFormatException("Could not determine image size.", $this->getFilePath(), $this->isRemoteFile());
        }
    }

    /**
     * Returns information about the specified file. Throws MSCL_MediaInfoException if the information isn't available
     * (for example when CURL isn't installed but it's a remote image).
     *
     * @param string $file_path the file path/url to the file to be inspected
     * @param        $cache_date
     *
     * @return ImageFileInfo|null
     */
    public static function get_instance($file_path, $cache_date = null)
    {
        $info = self::getCachedRemoteFileInfo($file_path, self::CLASS_NAME);
        if ($info === null)
        {
            $info = new ImageFileInfo($file_path, $cache_date);
        }

        return $info;
    }

    public function get_type()
    {
        return $this->type;
    }

    public function get_mime_type()
    {
        return self::convert_to_mime_type($this->type);
    }

    public static function convert_to_mime_type($img_type)
    {
        switch ($img_type)
        {
            case self::TYPE_JPEG:
                return 'image/jpeg';
            case self::TYPE_PNG:
                return 'image/png';
            case self::TYPE_GIF:
                return 'image/gif';
            default:
                throw new Exception("Unsupported image type: " . $img_type);
        }
    }

    public function get_width()
    {
        return $this->width;
    }

    public function get_height()
    {
        return $this->height;
    }

    protected function processHeaderData($data)
    {
        if ($this->type === null)
        {
            // image type not yet determined
            if (!$this->find_data_type($data))
            {
                return false;
            }
        }

        // image type determined
        switch ($this->type)
        {
            case self::TYPE_JPEG:
                $info = $this->check_jpg_data($data);
                break;

            case self::TYPE_PNG:
                $info = $this->check_png_data($data);
                break;

            case self::TYPE_GIF:
                $info = $this->check_gif_data($data);
                break;

            default:
                throw new Exception("Programming error: " . $this->type);
        }

        if ($info === false)
        {
            return false;
        }

        list($this->width, $this->height) = $info;

        return true;
    }

    private function find_data_type($data)
    {
        $len           = strlen($data);
        $types_checked = 0;

        // JPEG
        if ($len >= self::$JPEG_HEADER_LEN)
        {
            if ($this->is_jpg($data))
            {
                $this->type = self::TYPE_JPEG;

                return true;
            }
            $types_checked ++;
        }

        // PNG
        if ($len >= self::$PNG_HEADER_LEN)
        {
            if ($this->is_png($data))
            {
                $this->type = self::TYPE_PNG;

                return true;
            }
            $types_checked ++;
        }

        // GIF
        if ($len >= self::$GIF_HEADER_LEN)
        {
            if ($this->is_gif($data))
            {
                $this->type = self::TYPE_GIF;

                return true;
            }
            $types_checked ++;
        }

        if ($types_checked >= self::$SUPPORTED_FORMAT_COUNT)
        {
            throw new FileInfoFormatException("Could not determine image type.", $this->getFilePath(), $this->isRemoteFile());
        }

        return false;
    }

    private function is_jpg($data)
    {
        return self::startsWith($data, self::$JPEG_HEADER);
    }

    private function check_jpg_data($data)
    {
        //
        // See:
        // * Regular JPEG: http://www.videotechnology.com/jpeg/j1.html
        // * EXIF: http://www.media.mit.edu/pia/Research/deepview/exif.html
        //
        if (self::startsWith($data, "\xFF\xE0", 2) && self::startsWith($data, "JFIF\0", 6))
        {
            // Regular JPEG
        }
        else if (self::startsWith($data, "\xFF\xE1", 2) && self::startsWith($data, "Exif\0", 6))
        {
            // Exif JPEG
        }
        else
        {
            throw new FileInfoFormatException(sprintf("Unsupported jpeg image (with marker: %X%X).", ord($data[2]), ord($data[3])),
                $this->getFilePath(), $this->isRemoteFile());
        }

        // NOTE: We can't use the density (bytes 14-17) as even for units "0" the Xdensity and Ydensity
        //   may not be correct (eg. may be 100x100 while the image is actually 128x128).

        //
        // Dig a little deeper to find the image's size. Note that we need to skip almost all header data which
        // might be a lot (26kb in our test image).
        // See: http://www.64lines.com/jpeg-width-height
        //
        $pos = 4;
        $len = strlen($data);
        while ($pos + 2 < $len)
        {
            $block_size = self::deserializeInt16($data, $pos);
            $pos += $block_size;
            if ($pos + 2 >= $len)
            {
                // reached end of currently available data
                break;
            }
            // NOTE: (int)$data parses the character while ord($data) converts it.
            if (ord($data[$pos]) != 0xFF)
            {
                throw new FileInfoFormatException("Malformed jpeg image [2].", $this->getFilePath(), $this->isRemoteFile());
            }
            $header_byte = ord($data[$pos + 1]);
            if ($header_byte >= 0xC0 && $header_byte <= 0xC3 && $pos + 9 < $len)
            {
                // 0xFFC0 to 0xFFC3 is the "Start of frame" marker which contains the image size.
                // The header byte defines the JPEG encoding type:
                //  * C0 : Baseline DCT (common)
                //  * C1 : Extended Sequential DCT
                //  * C2 : Progressive DCT (common)
                //  * C3 : Lossless
                // Note that height and width are "exchanged" (ie. they don't come as "width", "height")
                $height = self::deserializeInt16($data, $pos + 5);
                $width  = self::deserializeInt16($data, $pos + 7);

                return array($width, $height);
            }
            else if ($header_byte == 0xFA)
            {
                // We've reached the image data. No more headers will follow; so, also no
                // image dimension information can't be found anymore.
                throw new FileInfoFormatException("Malformed jpeg image [3].", $this->getFilePath(), $this->isRemoteFile());
            }
            $pos += 2;
        }

        return false;
    }

    private function is_png($data)
    {
        return self::startsWith($data, self::$PNG_HEADER);
    }

    private function check_png_data($data)
    {
        if (!self::startsWith($data, 'IHDR', 12))
        {
            throw new FileInfoFormatException("Malformed png image.", $this->getFilePath(), $this->isRemoteFile());
        }
        $width  = self::deserializeInt32($data, 16);
        $height = self::deserializeInt32($data, 20);

        return array($width, $height);
    }

    private function is_gif($data)
    {
        return self::startsWith($data, self::$GIF_HEADER_v89) || self::startsWith($data, self::$GIF_HEADER_v87);
    }

    private function check_gif_data($data)
    {
        $width  = self::deserializeInt16($data, 6, false);
        $height = self::deserializeInt16($data, 8, false);

        return array($width, $height);
    }
}

?>
