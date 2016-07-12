<?php

namespace sndsgd\http\data\decoder;

/**
 * A urlencoded content decoder
 */
class UrlDecoder extends DecoderAbstract
{
    /**
     * {@inheritdoc}
     */
    public function decode(): array
    {
        $querystring = @file_get_contents($this->path, true);
        if ($querystring === false) {
            $message = "failed to read input stream";
            $err = error_get_last();
            if ($err !== null) {
                $message .= "; ".$err["message"];
            }
            throw new \RuntimeException($message);
        }

        $decoder = new QueryStringDecoder($this->contentLength, $this->values);
        return $decoder->decode($querystring);
    }
}