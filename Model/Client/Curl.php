<?php
/**
 * @author Convert Team
 * @copyright Copyright (c) 2018 Convert (http://www.convert.no/)
 */
namespace Porterbuddy\Porterbuddy\Model\Client;

class Curl extends \Magento\Framework\HTTP\Client\Curl
{
    /**
     * Makes request with JSON params support
     *
     * Magento Curl HTTP client cannot send JSON encoded requests until 2.1.14
     * @see https://github.com/magento/magento2/issues/3489
     *
     * {@inheritdoc}
     */
    protected function makeRequest($method, $uri, $params = [])
    {
        $this->_ch = curl_init();
        $this->curlOption(CURLOPT_URL, $uri);
        if ($method == 'POST') {
            $this->curlOption(CURLOPT_POST, 1);
            $this->curlOption(CURLOPT_POSTFIELDS, is_array($params) ? http_build_query($params) : $params);
        } elseif ($method == "GET") {
            $this->curlOption(CURLOPT_HTTPGET, 1);
        } else {
            $this->curlOption(CURLOPT_CUSTOMREQUEST, $method);
        }

        if (count($this->_headers)) {
            $heads = [];
            foreach ($this->_headers as $k => $v) {
                $heads[] = $k . ': ' . $v;
            }
            $this->curlOption(CURLOPT_HTTPHEADER, $heads);
        }

        if (count($this->_cookies)) {
            $cookies = [];
            foreach ($this->_cookies as $k => $v) {
                $cookies[] = "{$k}={$v}";
            }
            $this->curlOption(CURLOPT_COOKIE, implode(";", $cookies));
        }

        if ($this->_timeout) {
            $this->curlOption(CURLOPT_TIMEOUT, $this->_timeout);
        }

        if ($this->_port != 80) {
            $this->curlOption(CURLOPT_PORT, $this->_port);
        }

        $this->curlOption(CURLOPT_RETURNTRANSFER, 1);
        $this->curlOption(CURLOPT_HEADERFUNCTION, [$this, 'parseHeaders']);
        if (isset($this->sslVersion)) {
            $this->curlOption(CURLOPT_SSLVERSION, $this->sslVersion);
        }

        if (count($this->_curlUserOptions)) {
            foreach ($this->_curlUserOptions as $k => $v) {
                $this->curlOption($k, $v);
            }
        }

        $this->_headerCount = 0;
        $this->_responseHeaders = [];
        $this->_responseBody = curl_exec($this->_ch);
        $err = curl_errno($this->_ch);
        if ($err) {
            $this->doError(curl_error($this->_ch));
        }
        curl_close($this->_ch);
    }
}
