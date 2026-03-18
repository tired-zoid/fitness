<?php

namespace App\Requests;

use App\Core\HttpRequestBase;

class SimpleHttpRequest extends HttpRequestBase
{
    public function get(string $url): ?string
    {
        $this->clearOptions();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->header,
            CURLOPT_ENCODING => 'gzip,deflate,br',
            CURLOPT_TIMEOUT => 15,
        ];

        if (parse_url($url, PHP_URL_SCHEME) === 'https') {
            $options[CURLOPT_SSL_VERIFYPEER] = 0;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        $this->addOptions($options);
        return $this->exec();
    }


    public function post(string $url, array|string $data): ?string
    {
        $this->clearOptions();

        // Если data - массив, преобразуем в строку
        if (is_array($data)) {
            $data = http_build_query($data);
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $this->header,
            CURLOPT_ENCODING => 'gzip,deflate,br',
            CURLOPT_TIMEOUT => 15,
        ];


        if (parse_url($url, PHP_URL_SCHEME) === 'https') {
            $options[CURLOPT_SSL_VERIFYPEER] = 0;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        $this->addOptions($options);
        return $this->exec();
    }


}

