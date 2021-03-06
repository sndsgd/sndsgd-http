<?php

namespace sndsgd\http\data\decoder;

class MultipartDataDecoder extends DecoderAbstract
{
    /**
     * The number of bytes to read when using `fread()`
     *
     * @var integer
     */
    protected $bytesPerRead;

    /**
     * The max size of an uploaded file in bytes
     *
     * @var int
     */
    protected $maxFileSize;

    /**
     * The max number of uploaded files per request
     *
     * @var int
     */
    protected $maxFileCount;

    /**
     * The number of files processed
     *
     * @var int
     */
    protected $fileCount = 0;

    /**
     * The input stream to read multipart data from
     *
     * @var resource
     */
    protected $fp;

    /**
     * The multipart field boundary
     *
     * @var string
     */
    protected $boundary;

    /**
     * The final field boundary, with the end dashes
     *
     * @var string
     */
    protected $lastBoundary;

    /**
     * The contents of the input stream that have been read, but not processed
     *
     * @var string
     */
    protected $buffer = "";

    /**
     * @param string $path The path to the stream to read from
     * @param string $contentType The content type of the request body
     * @param int $contentLength The size of the request body
     * @param \sndsgd\http\data\decoder\DecoderOptions
     * @param int $bytesPerRead The number of bytes to read at a time
     */
    public function __construct(
        string $path,
        string $contentType,
        int $contentLength,
        DecoderOptions $options = null,
        int $bytesPerRead = 8192
    )
    {
        parent::__construct($path, $contentType, $contentLength, $options);
        $this->maxFileSize = $this->options->getMaxFileSize();
        $this->maxFileCount = $this->options->getMaxFileCount();
        $this->bytesPerRead = $bytesPerRead;
    }

    /**
     * @inheritDoc
     */
    public function decode(): array
    {
        $this->boundary = $this->getBoundary();
        $this->lastBoundary = "{$this->boundary}--";

        $this->fp = fopen($this->path, "r");
        if ($this->fp === false) {
            throw new \RuntimeException(
                "failed to open '{$this->path}' for reading"
            );
        }

        while ($this->fieldsRemain() === true) {
            list($name, $filename, $contentType) = $this->getFieldHeader();

            # if a filename wasn't provided, assume the field is not a file
            if ($filename === "") {
                $value = $this->getValueFromField();
                $this->values->addValue($name, $value);
            } else {
                $value = $this->getFileFromField($name, $filename, $contentType);
                if ($this->fileCount < $this->maxFileCount) {
                    $this->values->addValue($name, $value);
                }
                $this->fileCount++;
            }
        }

        fclose($this->fp);
        return $this->values->getValues();
    }

    /**
     * Retrieve the parameter boundary from the content type header
     *
     * @return string
     * @throws \sndsgd\http\data\DecodeException If a boundary is not present
     */
    protected function getBoundary()
    {
        $pos = strpos($this->contentType, "boundary=");
        if ($pos === false) {
            throw new \sndsgd\http\data\DecodeException(
                "missing value for 'boundary' in content-type header"
            );
        }
        return "--".substr($this->contentType, $pos + 9);
    }

    /**
     * Determine if any more fields remain in the stream
     *
     * @return bool
     */
    protected function fieldsRemain()
    {
        $bufferlen = strlen($this->buffer);
        $minlen = strlen($this->lastBoundary);

        # if the buffer is too short to contain the last boundary
        # read enough bytes into the buffer to allow for a strpos test
        if ($bufferlen < $minlen) {
            if (feof($this->fp)) {
                fclose($this->fp);
                throw new \sndsgd\http\data\DecodeException(
                    "Invalid multipart data encountered; ".
                    "end of content was reached before expected"
                );
            }

            $bytes = fread($this->fp, $this->bytesPerRead);
            if ($bytes === false) {
                fclose($this->fp);
                throw new \RuntimeException(
                    "failed to read $minlen bytes from input stream"
                );
            }

            $this->buffer .= $bytes;
        }

        # if the buffer starts with the last boundary, there are no more fields
        return (strpos($this->buffer, $this->lastBoundary) !== 0);
    }

    /**
     * Read the input stream into the buffer until a string is encountered
     *
     * @param string $search The string to read until
     * @return integer The position of the string in the buffer
     */
    protected function readUntil($search)
    {
        while (($position = strpos($this->buffer, $search)) === false) {
            if (feof($this->fp)) {
                fclose($this->fp);
                throw new \sndsgd\http\data\DecodeException(
                    "Invalid multipart data encountered; ".
                    "end of content was reached before expected"
                );
            }
            $this->buffer .= fread($this->fp, $this->bytesPerRead);
        }
        return $position;
    }

    /**
     * Read the header values for the current field from the input stream
     *
     * @return array
     */
    protected function getFieldHeader()
    {
        # read the input stream until the empty line after the header
        $position = $this->readUntil("\r\n\r\n");

        # separate the header from the field content
        # remove the header content from the buffer
        $header = substr($this->buffer, 0, $position);
        $this->buffer = substr($this->buffer, $position + 4);

        $regex =
            "/content-disposition:[\t ]+?form-data;".
            "[\t ]+(?:name=\"(.*?)\")?".
            "(?:;[\t ]+?filename=\"(.*?)\")?/i";

        if (preg_match($regex, $header, $matches) !== 1) {
            fclose($this->fp);
            throw new \sndsgd\http\data\DecodeException(
                "Invalid multipart data; 'Content-Disposition' ".
                "malformed or missing in file field header"
            );
        }

        # we have no need for the entire match, so we drop it here
        list( , $name, $filename) = array_pad($matches, 3, "");

        # if a filename was in the content disposition
        # attempt to find its content type in the field header
        if ($filename !== "") {
            $regex = "/content-type:[\t ]+?(.*)(?:;|$)/mi";
            if (preg_match($regex, $header, $matches) === 1) {
                $contentType = strtolower($matches[1]);
            } else {
                $contentType = "";
            }
        } else {
            $contentType = "";
        }

        return [$name, $filename, $contentType];
    }

    /**
     * Get the value of the current field in the input stream
     *
     * @return string
     */
    private function getValueFromField()
    {
        $position = $this->readUntil($this->boundary);

        # there is always a newline after the value and before the boundary
        # exclude that newline from the value
        $value = substr($this->buffer, 0, $position - 2);

        # update the buffer to exclude the value and the pre boundary newline
        $this->buffer = substr($this->buffer, $position);

        return $value;
    }

    /**
     * Allow for stubbing the result of tempnam using reflection
     *
     * @return string
     */
    protected function getTempFilePath()
    {
        return tempnam(sys_get_temp_dir(), "uploaded-file-");
    }

    /**
     * Copy file contents from the input stream to a temp file
     *
     * @param string $name The field name
     * @param string $filename The name of the uploaded file
     * @param string $unverifiedContentType The user provided content type
     * @return \sndsgd\http\UploadedFile
     */
    protected function getFileFromField(
        string $name,
        string $filename,
        string $unverifiedContentType
    )
    {
        # create and open a temp file to write the contents to
        $tempPath = $this->getTempFilePath();
        $tempHandle = fopen($tempPath, "w");
        if ($tempHandle === false) {
            fclose($this->fp);
            $message = \sndsgd\Error::createMessage(
                "failed to open '$tempPath' for writing"
            );
            throw new \RuntimeException($message);
        }

        # number of bytes read from the input stream in the last loop cycle
        $bytesRead = 0;
        # the total number of bytes written to the temp file
        $bytesWritten = 0;

        # if anything is left over from the previous field, add it to the file
        if ($this->buffer !== "") {
            $bytesRead = fwrite($tempHandle, $this->buffer);
            if ($bytesRead === false) {
                fclose($this->fp);
                fclose($tempHandle);
                throw new \RuntimeException(
                    "fwrite() failed to write to '$tempPath'"
                );
            }
            $bytesWritten += $bytesRead;
        }

        while (($pos = strpos($this->buffer, $this->boundary)) === false) {
            $this->buffer = fread($this->fp, $this->bytesPerRead);
            $bytesRead = fwrite($tempHandle, $this->buffer);
            if ($bytesRead === false) {
                fclose($this->fp);
                fclose($tempHandle);
                throw new \RuntimeException(
                    "fwrite() failed to write to '$tempPath'"
                );
            }
            $bytesWritten += $bytesRead;
        }

        # determine the size of the file based on the boundary position
        $size = $bytesWritten - $bytesRead + $pos - 2;

        # trim the excess contents of the local buffer to the object buffer
        $this->buffer = substr($this->buffer, $pos);

        # if the uploaded file was empty
        if ($size < 1) {
            return $this->fileUploadError(
                UPLOAD_ERR_NO_FILE,
                $tempPath,
                $tempHandle,
                $filename,
                $unverifiedContentType,
                0
            );
        }

        # if the file exceeded the max upload size
        elseif ($size > $this->maxFileSize) {
            return $this->fileUploadError(
                UPLOAD_ERR_INI_SIZE,
                $tempPath,
                $tempHandle,
                $filename,
                $unverifiedContentType,
                0
            );
        }

        ftruncate($tempHandle, $size);
        fclose($tempHandle);

        return new \sndsgd\http\UploadedFile(
            $filename,
            $unverifiedContentType,
            $size,
            $tempPath
        );
    }

    /**
     * Handle an invalid file upload
     *
     * @param int $errorCode The relevant PHP file upload error
     * @param string $tempPath The absolute path to the temp file
     * @param resource $tempHandle The handle for the temp file
     * @param string $filename The file name as provided by the client
     * @param string $contentType The mime type of the uploaded file
     * @param int $size The bytesize of the uploaded file
     * @return \sndsgd\http\UploadedFile
     */
    protected function fileUploadError(
        int $errorCode,
        string $tempPath,
        $tempHandle,
        string $filename,
        string $contentType,
        int $size
    ): \sndsgd\http\UploadedFile
    {
        fclose($tempHandle);
        if ($tempPath && file_exists($tempPath)) {
            unlink($tempPath);
        }

        return new \sndsgd\http\UploadedFile(
            $filename,
            $contentType,
            $size,
            "",
            $errorCode
        );
    }
}
