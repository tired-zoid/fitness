<?php

namespace App\Core;

use CurlHandle;

abstract class HttpRequestBase
{
    protected string $link = '';
    protected array $header = [];
    protected array $curl_options = [];
    protected ?string $cookie_path = null;
    protected ?CurlHandle $curl = null;

    public function setLink(string $link): void
    {
        $this->link = $link;
    }

    public function setHeader(array $header): void
    {
        $this->header = $header;
    }

    public function addOptions(array $options): void
    {
        $this->curl_options = array_replace($this->curl_options, $options);
    }

    public function clearOptions(): void
    {
        $this->curl_options = [];
    }

    public function exec(bool $close = true): ?string
    {
        $curl = $this->getCurl();
        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            $error = curl_error($curl);
            $code = curl_errno($curl);
            if ($close) {
                curl_close($curl);
                $this->curl = null;
            }
            throw new \RuntimeException("cURL error ($code): $error");
        }

        if ($close) {
            curl_close($curl);
            $this->curl = null;
        }

        return $response;
    }

    public function getCurl(): CurlHandle
    {
        if (!$this->curl) {
            $this->curl = curl_init();
        }

        curl_setopt_array($this->curl, $this->curl_options);

        return $this->curl;
    }
}
