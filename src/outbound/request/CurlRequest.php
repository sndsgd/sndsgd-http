<?php

namespace sndsgd\http\outbound\request;

use \CurlFile;
use \Exception;
use \InvalidArgumentException;
use \sndsgd\Arr;
use \sndsgd\data\Manager as DataTrait;
use \sndsgd\http\inbound\response\CurlResponse;
use \sndsgd\Mime;
use \sndsgd\Url;


/**
 * A cURL request
 */
class CurlRequest extends \sndsgd\http\outbound\Request
{
   use DataTrait;

   /**
    * A curl resource
    *
    * @var resource
    */
   protected $curl;

   /**
    * Custom cURL options
    *
    * @var array<integer,boolean|integer|string>
    */
   protected $curlOptions = [];

   /**
    * Close the curl object if it is still open
    */
   public function __destruct()
   {
      if ($this->curl) {
         curl_close($this->curl);
      }
   }

   /**
    * Set a cURL option
    *
    * @param string $opt
    * @param mixed $value
    */
   public function setCurlOption($opt, $value)
   {
      $this->curlOptions[$opt] = $value;
   }

   /**
    * Set curl options for the request
    *
    * @param array<integer,boolean|integer|string> $opts
    */
   public function setCurlOptions(array $opts)
   {
      $this->curlOptions = $opts;
   }

   /**
    * Combine curl options for the request
    *
    * @param array $opts Base curl options, generally provided by the crawler
    * @return array
    */
   public function getCurlOptions(array $opts = [])
   {
      foreach ($this->curlOptions as $key => $value) {
         $opts[$key] = $value;
      }
      $opts[CURLOPT_HTTPHEADER] = $this->stringifyHeaders();
      return $opts;
   }

   /**
    * Get a curl resource for the request
    *
    * @return resource
    */
   public function getCurl()
   {
      if ($this->curl !== null) {
         return $this->curl;
      }

      $this->curl = curl_init();
      $this->processData();
      curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($this->curl, CURLOPT_HEADER, true);
      curl_setopt($this->curl, CURLOPT_ENCODING, "");
      curl_setopt_array($this->curl, $this->getCurlOptions());
      return $this->curl;
   }

   /**
    * Process data for the request
    */
   protected function processData()
   {
      if ($this->method === "GET") {
         $queryParams = ($this->data)
            ? "?".Url::encodeQueryString($this->data)
            : "";
         curl_setopt($ch, CURLOPT_URL, $this->url.$queryParams);
      }
      else {
         curl_setopt($ch, CURLOPT_URL, $this->url);
         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
         if ($this->hasUploadFiles()) {
            $this->setHeader("Expect", "");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->data);
         }
         else {
            $this->setHeader("Content-Type", "application/json; charset=UTF-8");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->data));
         }
      }
   }

   protected function hasUploadFiles()
   {
      $count = 0;
      $tmp = [];
      foreach ($this->data as $key => $values) {
         foreach ((array) $values as $value) {
            if (strpos($value, "@/") === 0) {
               $count++;
               $path = substr($value, 1);
               $ext = pathinfo($path, PATHINFO_EXTENSION);
               $mime = Mime::getTypeFromExtension($ext);
               $file = new CurlFile($path, $mime, basename($path));
               Arr::addValue($tmp, $key, $value);
            }
            else {
               Arr::addValue($tmp, $key, $value);
            }
         }
      }

      $this->data = $tmp;
      return ($count > 0);
   }

   /**
    * {@inheritdoc}
    */
   public function getResponse($class = null)
   {
      $validclass = "sndsgd\\http\\inbound\\response\\CurlResponse";
      if ($class === null) {
         $class = $validclass;
      }
      else if (!is_a($class, $validclass, true)) {
         throw new InvalidArgumentException(
            "invalid value provided for 'classname'; ".
            "expecting an subclass of '$validclass'"
         );
      }

      $ch = $this->getCurl();
      $response = new $classname;
      $response->setBody(curl_exec($ch));
      $response->setCurlInfo(curl_getinfo($ch));
      $response->parseHeaders();
      return $response;
   }
}