<?php
/*
|--------------------------------------------------------------------------
| SkyCaiji (蓝天采集器)
|--------------------------------------------------------------------------
| Copyright (c) 2018 https://www.skycaiji.com All rights reserved.
|--------------------------------------------------------------------------
| 使用协议  https://www.skycaiji.com/licenses
|--------------------------------------------------------------------------
 */

/*curl*/
namespace util;

class Curl
{
    protected static $instance = null;
    public $code = 0;
    public $ok = false;
    public $header = null;
    public $body = null;

    /*实例*/
    private static function init()
    {
        if (!isset(self::$instance)) {
            self::$instance = new static;
        }

        self::$instance->code = 0;
        self::$instance->ok = false;
        self::$instance->header = null;
        self::$instance->body = null;
        //异常码
        self::$instance->error_no = 0;
        //异常提示信息
        self::$instance->error_msg = null;
        return self::$instance;
    }
    /**
     * 请求
     * @param string $url
     * @param array $headers header格式必须为 “键: 值”
     * @param array $options
     * @param string $postData
     * @return \util\Curl
     */
    public static function request($url, $headers = array(), $options = array(), $postData = null)
    {
        $instance = self::init();

        $isPost = false;
        if (isset($postData) && $postData !== false) {

            $isPost = true;
        }

        $headers = is_array($headers) ? $headers : array();
        $options = is_array($options) ? $options : array();

        $options['timeout'] = intval($options['timeout']);
        $options['timeout'] = $options['timeout'] > 0 ? $options['timeout'] : 20;

        if ($isPost && $options['content_type']) {

            $options['content_type'] = strtolower($options['content_type']);
            $headers[] = 'content-type: ' . $options['content_type'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        if ($options['nobody']) {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        if ($options['useragent']) {
            curl_setopt($ch, CURLOPT_USERAGENT, $options['useragent']);
        }
        //设置refere
        if (!empty($options['referer'])) {
            curl_setopt($ch, CURLOPT_REFERER, $options['referer']);
        }
        //设置cookie
        if (!empty($options['cookie'])) {
            curl_setopt($ch, CURLOPT_COOKIE, $options['cookie']);
        }
        //只解析为IPv4地址
        if ($options['ipv4']) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        //curl解压gzip页面内容
        if ($options['gzip']) {
            curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        }
        //设置跳转location次数,默认3次
        if (empty($options['maxredirs'])) {
            $options['maxredirs'] = 3;
        }
        curl_setopt($ch, CURLOPT_MAXREDIRS, $options['maxredirs']);
        //https时才忽略
        if (stripos($url, 'https') === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        if (!empty($headers) && count($headers) > 0) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($options['range_size']) {
            curl_setopt($ch, CURLOPT_RANGE, $options['range_size']);
        }

        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, 1);
            if (empty($options['content_type']) || $options['content_type'] == 'application/x-www-form-urlencoded') {
                if (is_array($postData)) {
                    $postData = http_build_query($postData);
                }
            } elseif ($options['content_type'] == 'application/json') {
                $postData = json_encode($postData);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        if (!empty($options['proxy']) && !empty($options['proxy']['ip'])) {

            $proxyType = null;
            switch ($options['proxy']['type']) {
                case 'socks4':$proxyType = CURLPROXY_SOCKS4;
                    break;
                case 'socks5':$proxyType = CURLPROXY_SOCKS5;
                    break;
                default:$proxyType = CURLPROXY_HTTP;
                    break;
            }

            curl_setopt($ch, CURLOPT_PROXYTYPE, $proxyType);

            curl_setopt($ch, CURLOPT_PROXY, $options['proxy']['ip']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $options['proxy']['port']);
            if (!empty($options['proxy']['user'])) {
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $options['proxy']['user'] . ':' . $options['proxy']['pwd']);
            }
        }

        if ($options['return_curl']) {

            return $ch;
        }

        $instance->header = null;
        $instance->body = null;

        $body = curl_exec($ch);
        if ($body) {
            $headerPos = strpos($body, "\r\n\r\n");
            if ($headerPos !== false) {
                $headerPos = intval($headerPos) + strlen("\r\n\r\n");
            }
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerSize = intval($headerSize);
            if ($headerSize < $headerPos) {
                $headerSize = $headerPos;
            }
            $instance->header = substr($body, 0, $headerSize);
            $instance->body = substr($body, $headerSize);
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $code = intval($code);
        $instance->code = $code;
        $instance->ok = ($code >= 200 && $code < 300) ? true : false;
        //异常时记录异常信息
        if (!$instance->ok) {
            $instance->error_no = curl_errno($ch);
            $instance->error_msg = curl_error($ch);
        }
        if (!isset($instance->header)) {
            $instance->header = '';
        }
        if (!isset($instance->body)) {
            $instance->body = '';
        }
        curl_close($ch);
        return $instance;
    }

    public static function head($url, $headers = array(), $options = array())
    {
        $options = is_array($options) ? $options : array();
        $options['nobody'] = 1;
        return self::request($url, $headers, $options);
    }
    public static function get($url, $headers = array(), $options = array())
    {
        return self::request($url, $headers, $options);
    }
    public static function post($url, $headers = array(), $options = array(), $data = null)
    {
        return self::request($url, $headers, $options, empty($data) ? '' : $data);
    }
}
