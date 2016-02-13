<?php

namespace sndsgd\http;

class HeaderCollection
{
    /**
     * @var array<string,string>
     */
    protected $headers = [];

    /**
     * A map of `lowercase key` => `key`
     *
     * @var array<string,string>
     */
    protected $keyMap = [];

    /**
     * @param string $key
     * @param bool $register Whether to register a new key
     * @return string
     */
    protected function getKey(string $key, bool $register = false): string
    {
        $lowercaseKey = strtolower($key);
        if (isset($this->keyMap[$lowercaseKey])) {
            $key = $this->keyMap[$lowercaseKey];
        }
        elseif ($register) {
            $this->keyMap[$lowercaseKey] = $key;
        }
        return $key;
    }

    /**
     * Set a header
     *
     * @param string $key
     * @param string $value
     */
    public function set(string $key, string $value): HeaderCollection
    {
        $key = $this->getKey($key, true);
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Set multiple headers
     *
     * @param array<string,string> $headers
     */
    public function setMultiple(array $headers): HeaderCollection
    {
        foreach ($headers as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    /**
     * Add a request header
     *
     * @param string $key
     * @param string|integer $value
     */
    public function add(string $key, string $value): HeaderCollection
    {
        $key = $this->getKey($key, true);
        \sndsgd\Arr::addValue($this->headers, $key, $value);
        return $this;
    }

    /**
     * Add multiple headers
     *
     * @param array<string,string> $headers
     */
    public function addMultiple(array $headers): HeaderCollection
    {
        foreach ($headers as $key => $value) {
            $this->add($key, $value);
        }
        return $this;
    }

    /**
     * Get a header value
     *
     * @param string $key
     * @return string
     */
    public function get(string $key): string
    {
        $key = $this->getKey($key);
        $value = $this->headers[$key] ?? "";
        return is_array($value) ? implode(", ", $value) : $value;
    }

    /**
     * Get multiple header values
     *
     * @param string $key
     * @return array<string>
     */
    public function getMultiple(string ...$keys): array
    {
        $ret = [];
        foreach ($keys as $key) {
            $ret[] = $this->get($key);
        }
        return $ret;
    }

    /**
     * Retrieve an array of headers as strings
     *
     * @return array<string>
     */
    public function getStringifiedArray(): array
    {
        $ret = [];
        foreach ($this->headers as $header => $value) {
            if (is_array($value)) {
                $value = implode(", ", $value);
            }
            $ret[] = "$header: $value";
        }
        return $ret;
    }

    /**
     * Stringify the headers
     *
     * @return string
     */
    public function __toString(): string
    {
        return implode("\r\n", $this->getStringifiedArray());
    }
}
