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
use MSCL\Exceptions\InvalidOperationException;

/**
 * Represents information about a file.
 */
abstract class AbstractFileInfo
{
    /**
     * Array containing information about all remote files that have been inspected during the current request.
     * @var array
     */
    private static $s_cachedRemoteFileInfo = array();

    /**
     * The path/URL of this file.
     * @var string
     */
    private $m_filePath;

    /**
     * Whether this file is a remote file (true) or a local file (false).
     * @var bool
     */
    private $m_isRemoteFile;

    /**
     * The file's size in bytes. May be "0" for remote files when the server doesn't report the file's size.
     *
     * @var int
     */
    private $m_fileSize = 0;

    /**
     * Last modification date of the file (seconds since Linux epoch). May be "null" for remote files when the server
     * doesn't report this information.
     *
     * @var int|null
     */
    private $m_lastModifiedDate;

    private $data = '';

    /**
     * The number of bytes downloaded/read to determine this image's info. Just for information purposes.
     *
     * @var int
     */
    private $m_readDataSize = 0;

    /**
     * The HTTP status code of the file, if this is a remote file. 200 means "ok".
     * @var int
     */
    private $m_httpStatusCode = 0;
    private $done = false;

    /**
     * Constructor.
     *
     * @param string   $filePath The path/URL of this file.
     * @param int|null $cacheDate
     *
     * @throws FileInfoIOException
     * @throws FileNotFoundException  if the specified file couldn't be found.
     * @throws NotModifiedNotification
     */
    protected function __construct($filePath, $cacheDate)
    {
        $this->m_filePath     = $filePath;
        $this->m_isRemoteFile = self::isRemoteFileStatic($filePath);

        if ($this->m_isRemoteFile)
        {
            // remote file
            if (!self::isRemoteFileSupportAvailable())
            {
                throw new FileInfoIOException('Remote file support is unavailable (CURL is not installed)', $filePath, true);
            }

            $this->readFileInfoFromRemoteFile($cacheDate);
        }
        else
        {
            if (!file_exists($filePath))
            {
                throw new FileNotFoundException($filePath, false);
            }

            $this->readFileInfoFromLocalFile($cacheDate);
        }

        $this->finishInitialization();

        // Everything worked out. Store this information.
        // IMPORTANT: We need to pass "$this" as otherwise the name will always be 'MSCL_AbstractFileInfo'.
        $className = get_class($this);
        if (array_key_exists($filePath, self::$s_cachedRemoteFileInfo))
        {
            self::$s_cachedRemoteFileInfo[$filePath][$className] =& $this;
        }
        else
        {
            self::$s_cachedRemoteFileInfo[$filePath] = array($className => &$this);
        }
    }

    // TODO: Remove and replace with private constructor and static factory method
    protected abstract function finishInitialization();

    /**
     * Checks whether remote file info is supported. This requires cURL being installed.
     * @return bool
     */
    public static function isRemoteFileSupportAvailable()
    {
        static $is_supported = null;
        if ($is_supported === null)
        {
            $is_supported = function_exists('curl_init');
        }

        return $is_supported;
    }

    /**
     * Checks whether the specified URL protocol (eg. "ftp", "http", "https", ...) is supported. Note: This method
     * will always return "true" for protocol "file".
     *
     * @param string $url the url for which the protocol to be checked. Alternatively the protocol can be passed
     *                    directly (ie. the part before "://").
     *
     * @return bool
     *
     * @throws Exception  if {@link isRemoteFileSupportAvailable} returns "false" (if protocol is not "file") or the
     *                    supported remote protocols couldn't be determined.
     */
    public static function isUrlProtocolSupported($url)
    {
        static $supportedRemoteProtocols = null;

        $url_parts = explode('://', $url, 2);
        if (count($url_parts) == 2)
        {
            $protocol = $url_parts[0];
        }
        else
        {
            $protocol = $url;
        }

        if ($protocol == 'file')
        {
            return true;
        }

        if ($supportedRemoteProtocols === null)
        {
            if (!self::isRemoteFileSupportAvailable())
            {
                throw new Exception("Remote file info is not supported on this system.");
            }

            $info = curl_version();
            if (isset($info['protocols']))
            {
                $supportedRemoteProtocols = array_flip($info['protocols']);
            }
            else
            {
                // Should never happen
                throw new Exception("Could not determine supported cURL protocols.");
            }
        }

        return isset($supportedRemoteProtocols[$protocol]);
    }

    /**
     * Returns the installed cURL version. Note that @see is_remote_support_enabled() must return "true" for
     * this method to work.
     *
     * @param bool $asString if "true", the version will be returned as string (eg. "7.20.0"); if "false", the
     *                       version will be returned as 24-bit integer (eg. for 7.20.0 this is 463872).
     *
     * @return int|string
     *
     * @throws Exception  if {@link isRemoteFileSupportAvailable} returns "false" or the version information is not
     *                    available.
     */
    public static function getCurlVersion($asString = true)
    {
        static $versionAsInt = null;
        static $versionAsStr = null;

        if ($versionAsInt === null)
        {
            if (!self::isRemoteFileSupportAvailable())
            {
                throw new Exception("Remote file support is not available.");
            }

            $info = curl_version();

            if (isset($info['version_number']) && isset($info['version']))
            {
                $versionAsInt = $info['version_number'];
                $versionAsStr = $info['version'];
            }
            else
            {
                // Should never happen
                throw new Exception("Could not determine cURL version.");
            }
        }

        return $asString ? $versionAsStr : $versionAsInt;
    }

    /**
     * Returns the cached file information about the specified remote file, if available. Returns null otherwise.
     *
     * @param string $filePath
     * @param string $className
     *
     * @return AbstractFileInfo|null
     */
    public static function getCachedRemoteFileInfo($filePath, $className)
    {
        if (!array_key_exists($filePath, self::$s_cachedRemoteFileInfo))
        {
            return null;
        }

        $fileInfo = &self::$s_cachedRemoteFileInfo[$filePath];

        if (!array_key_exists($className, $fileInfo))
        {
            return null;
        }

        return $fileInfo[$className];
    }

    /**
     * Returns the path to the image (either an url or a file path).
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->m_filePath;
    }

    /**
     * Returns whether the file is a remote file.
     *
     * @return bool
     */
    public function isRemoteFile()
    {
        return $this->m_isRemoteFile;
    }

    /**
     * Checks whether the specified file path represents a remote file.
     *
     * @param string $filePath the path to check
     *
     * @return bool
     */
    public static function isRemoteFileStatic($filePath)
    {
        $found = preg_match('/^([a-zA-Z0-9\+\.\-]+)\:\/\/.+/', $filePath, $matches);
        if (!$found)
        {
            return false;
        }

        return ($matches[1] != 'file');
    }

    /**
     * Returns the "last modification date" of the file (seconds since Linux epoch). May be "null" for remote files when
     * the server doesn't report this information.
     *
     * @return int|null
     */
    public function getLastModifiedDate()
    {
        return $this->m_lastModifiedDate;
    }

    /**
     * Returns the file's size in bytes. May be "null" for remote files when the server doesn't report the
     * file's size.
     *
     * @return int|null
     */
    public function getFileSize()
    {
        return $this->m_fileSize;
    }

    /**
     * Returns the number of bytes downloaded/read to determine this image's info. Just for information purposes.
     *
     * @return int
     */
    public function getReadDataSize()
    {
        return $this->m_readDataSize;
    }

    /**
     * Returns the content of the specified file. If it's a remote file, the file is being downloaded.
     *
     * @param string $filePath the file to load
     *
     * @return string
     *
     * @throws InvalidOperationException  if remote file support isn't available
     * @throws FileNotFoundException  if the specified file couldn't be found
     * @throws FileInfoIOException  if downloading the remote file failed with some unexpected HTTP status code
     */
    public static function getFileContents($filePath)
    {
        $is_remote = self::isRemoteFileStatic($filePath);
        if ($is_remote)
        {
            if (!self::isRemoteFileSupportAvailable())
            {
                throw new InvalidOperationException('Remote support is unavailable (CURL is not installed)');
            }

            $ch = self::createCurlHandle($filePath, null);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $result = curl_exec($ch);
            if ($result === false)
            {
                self::processCurlError($ch, $filePath);
            }

            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            switch ($status_code)
            {
                case 200: // OK
                    break;
                case 404:
                    throw new FileNotFoundException($filePath, true);
                default:
                    throw new FileInfoIOException("Invalid HTTP status code: " . $status_code, $filePath, true);
            }

            curl_close($ch);
        }
        else
        {
            if (!is_file($filePath))
            {
                throw new FileNotFoundException($filePath, false);
            }

            $result = file_get_contents($filePath);
        }

        return $result;
    }

    /**
     * Creates a CURL handle for downloading the specified file.
     *
     * @param string $filePath the file to download
     * @param        $cacheDate
     *
     * @return resource
     * @throws FileInfoException  if the CURL handle couldn't be created
     */
    private static function createCurlHandle($filePath, $cacheDate)
    {
        $ch = curl_init($filePath);
        if (!$ch)
        {
            throw new FileInfoException("curl_init() failed", $filePath, true);
        }

        if ($cacheDate !== null)
        {
            // The date needs to be formatted as RFC 1123, eg. "Sun, 06 Nov 1994 08:49:37 GMT"
            // See: http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.3.1
            // NOTE: We can't use "DATE_RFC1123" here, as this won't produce the correct timezone (will produce
            //   "+0000" instead of "GMT").
            curl_setopt($ch, CURLOPT_HTTPHEADER,
                array('If-Modified-Since: ' . gmdate('D, d M Y H:i:s \G\M\T', $cacheDate))
            );
        }
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        // NOTE: This option can't be enabled in safe mode. But that's not a big problem, since most files will probably
        //  have no redirect anyways.
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);

        // Fix some problems with some broken IPv6 installations and self-signed SSL certs
        // See: http://bugs.php.net/47739
        if (defined('CURLOPT_IPRESOLVE'))
        {
            // Only in PHP 5.3
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        return $ch;
    }

    /**
     * Converts the last error that occurred on the specified CURL handle into an appropriate exception.
     *
     * @param resource $ch        the CURL handle
     * @param string   $file_path the file that was attempted to be downloaded
     *
     * @throws FileNotFoundException  if the specified domain couldn't be found or reached
     * @throws FileInfoException  for any other error
     */
    private static function processCurlError($ch, $file_path)
    {
        $error_number = curl_errno($ch);
        if ($error_number == 60 || $error_number == 6)
        {
            // Treat "domain not found" (6) and "no route to host" (60) as file not found
            throw new FileNotFoundException($file_path, true);
        }

        throw new FileInfoException("Could not execute cURL request. Reason: " . curl_error($ch) . ' [' . $error_number . ']',
            $file_path, true);
    }

    private function readFileInfoFromRemoteFile($cache_date)
    {
        $ch = self::createCurlHandle($this->m_filePath, $cache_date);
        // attempt to retrieve the modification date
        curl_setopt($ch, CURLOPT_FILETIME, true);

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this, 'onRemoteFileOpened'));

        // NOTE: We need to check "$this->done" here, because when returning "-1" in "onRemoteFileOpened()",
        //   "curl_exec()" will return "false".
        if (@curl_exec($ch) === false && $this->done === false)
        {
            self::processCurlError($ch, $this->m_filePath);
        }

        if ($this->m_httpStatusCode === null)
        {
            // For certain status codes the write callback isn't used but "curl_exec()" returns directly. In this
            // case the status code hasn't been checked. The code 304 NOT MODIFIED is such an example.
            $this->m_httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($this->m_httpStatusCode == 304 && $cache_date !== null)
            {
                throw new NotModifiedNotification();
            }
        }

        $this->m_lastModifiedDate = curl_getinfo($ch, CURLINFO_FILETIME);
        if ($this->m_lastModifiedDate == - 1)
        {
            $this->m_lastModifiedDate = null;
        }

        $this->m_fileSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        if ($this->m_fileSize == 0)
        {
            $this->m_fileSize = null;
        }

        curl_close($ch);
        // NOTE: Handling failure of check_data is done in the constructor.
    }

    /**
     * CURL callback for {@link openRemoteFile()}.
     *
     * @param resource $ch        the CURL handle
     * @param string   $dataChunk the data chunk that has been read
     *
     * @return int
     * @throws FileInfoIOException
     * @throws FileNotFoundException
     * @throws NotModifiedNotification
     */
    private function onRemoteFileOpened($ch, $dataChunk)
    {
        if ($this->m_httpStatusCode === null)
        {
            $this->m_httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // see: http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
            switch ($this->m_httpStatusCode)
            {
                case 200: // OK
                    break;

                case 304: // NOT MODIFIED
                    // NOTE: We usually don't get here as this method isn't called if the 304 status is returned.
                    //  But to be on the safe side ...
                    throw new NotModifiedNotification();

                case 404:
                    throw new FileNotFoundException($this->m_filePath, true);

                default:
                    throw new FileInfoIOException("Invalid HTTP status code: " . $this->m_httpStatusCode, $this->m_filePath, true);
            }
        }

        if ($this->accumulateAndProcessHeaderData($dataChunk))
        {
            // we're done; break curl connection
            $this->done = true;

            return - 1;
        }

        return strlen($dataChunk);
    }

    /**
     * @param int $cache_date
     *
     * @throws FileInfoIOException
     * @throws NotModifiedNotification
     */
    private function readFileInfoFromLocalFile($cache_date)
    {
        $mod_date = @filemtime($this->m_filePath);
        if ($mod_date === false)
        {
            throw new FileInfoIOException("Could not determine file modification date.", $this->m_filePath, false);
        }
        if ($cache_date !== null && $cache_date >= $mod_date)
        {
            throw new NotModifiedNotification();
        }
        $this->m_lastModifiedDate = $mod_date;

        $this->m_fileSize = filesize($this->m_filePath);
        if ($this->m_fileSize === false)
        {
            throw new FileInfoIOException("Could not determine file size.", $this->m_filePath, false);
        }

        $file_handle = @fopen($this->m_filePath, 'rb');
        if ($file_handle === false)
        {
            throw new FileInfoIOException("Could not open file.", $this->m_filePath, false);
        }
        while (!feof($file_handle))
        {
            $dataChunk = fread($file_handle, 2048);
            if ($dataChunk === false)
            {
                throw new FileInfoIOException("Could not read file.", $this->m_filePath, false);
            }
            if ($this->accumulateAndProcessHeaderData($dataChunk))
            {
                break;
            }
        }
        fclose($file_handle);
        // NOTE: Handling failure of check_data is done in the constructor.
    }

    // TODO: Move this to its own class
    /**
     * Accumulates and processes the header data of this file to obtain more information about the file.
     *
     * @param string $dataChunk the data which has already been downloaded/read
     *
     * @return bool returns "true" if enough data has been processed and no more data needs to be
     *   read/downloaded. If this returns "false", more data will be read/downloaded.
     */
    private function accumulateAndProcessHeaderData($dataChunk)
    {
        $this->data .= $dataChunk;

        if (!$this->processHeaderData($this->data))
        {
            return false;
        }

        $this->m_readDataSize = strlen($this->data);
        // free data
        $this->data = null;

        return true;
    }

    /**
     * Processes the header data of this file to obtain more information about the file.
     *
     * @param string $data the data which has already been downloaded/read
     *
     * @return bool returns "true" if enough data has been processed and no more data needs to be
     *   read/downloaded. If this returns "false", more data will be read/downloaded.
     */
    protected abstract function processHeaderData($data);

    ////////////////////////////////////////////////////////////////////////////////////////
    //
    // Helper functions
    //

    // TODO: Replace with "string_starts_with()"
    /**
     * Whether $str starts with $with.
     *
     * @param string $str    the string to check
     * @param string $with   the start string
     * @param int    $offset offset within the string to start the comparison at
     *
     * @return bool
     */
    protected static function startsWith($str, $with, $offset = 0)
    {
        return substr($str, $offset, strlen($with)) == $with;
    }

    /**
     * Deserializes a 16 bit int (short) at the specified location.
     *
     * NOTE: {@code (int)$data} can't be used here as it's the same as {@code ord($data)}.
     *
     * @param string $data           the data to parse
     * @param int    $pos            the position in $data to read from
     * @param bool   $use_big_endian whether the int is serialized as big-endian (true) or little-endian (false)
     *
     * @return int
     */
    protected static function deserializeInt16($data, $pos, $use_big_endian = true)
    {
        // NOTE: (int)$data parses the character while ord($data) converts it.
        if ($use_big_endian)
        {
            return ord($data[$pos]) * 0x100 + ord($data[$pos + 1]);
        }
        else
        {
            return ord($data[$pos]) + ord($data[$pos + 1]) * 0x100;
        }
    }

    /**
     * Deserializes a 32 bit int at the specified location.
     *
     * NOTE: {@code (int)$data} can't be used here as it's the same as {@code ord($data)}.
     *
     * @param string $data           the data to parse
     * @param int    $pos            the position in $data to read from
     * @param bool   $use_big_endian whether the int is serialized as big-endian (true) or little-endian (false)
     *
     * @return int
     */
    protected static function deserializeInt32($data, $pos, $use_big_endian = true)
    {
        // NOTE: (int)$data parses the character while ord($data) converts it.
        if ($use_big_endian)
        {
            return ord($data[$pos]) * 0x1000000 + ord($data[$pos + 1]) * 0x10000
                   + ord($data[$pos + 2]) * 0x100 + ord($data[$pos + 3]);
        }
        else
        {
            return ord($data[$pos]) + ord($data[$pos + 1]) * 0x100
                   + ord($data[$pos + 2]) * 0x10000 + ord($data[$pos + 3]) * 0x10000;
        }
    }
}
