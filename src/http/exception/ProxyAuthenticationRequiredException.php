<?php

namespace sndsgd\http\exception;

class ProxyAuthenticationRequiredException extends ExceptionAbstract
{
    /**
     * @inheritDoc
     */
    public function getStatusCode(): int
    {
        return \sndsgd\http\Status::PROXY_AUTHENTICATION_REQUIRED;
    }
}
