<?php
/**
 * @author andy.bezbozhny <andy.bezbozhny@gmail.com>
 * Поиск и проверка рабочих прокси-серверов
 */
class ProximaHttp
{
    /**
     * @const PROXIES список URL со списками IP прокси
     **/
    const PROXIES = [
        # https://github.com/proxifly/free-proxy-list
        # http://84.17.47.150:9002
        'https://cdn.jsdelivr.net/gh/proxifly/free-proxy-list@main/proxies/protocols/https/data.txt',

        # https://github.com/zloi-user/hideip.me
        # 84.17.47.150:9002:The Netherlands
        'https://github.com/zloi-user/hideip.me/raw/refs/heads/main/https.txt',

        # https://github.com/databay-labs/free-proxy-list
        # 36.112.125.156:3226
        'https://cdn.jsdelivr.net/gh/databay-labs/free-proxy-list/http.txt',

        # https://github.com/iplocate/free-proxy-list
        # 121.138.61.193:8887
        'https://raw.githubusercontent.com/iplocate/free-proxy-list/refs/heads/main/protocols/https.txt',
    ];

    const ERRLOG_FLAG = true;
    const ERRLOG_FILE = 'Proxima.err';

    /**
     * @var mixed $error        сообщение об ошибке
     * @var array $proxies      список адресов прокси-серверов
     * @var int   $index        указатель на текущий индекс в self::$proxies
     * @var int   $count        кол-во записей в self::$proxies
     * @var mixed $workingProxy рабочий прокси
     */
    static $error        = null;
    static $proxies      = [];
    static $index        = 0;
    static $count        = 0;
    static $workingProxy = null;

    /**
     * @var array $curlOptions параметры CURL
     */
    static $curlOptions = [
        CURLOPT_URL             => self::URL,
        CURLOPT_PROXY           => null,
        CURLOPT_PROXYTYPE       => CURLPROXY_HTTP,
        CURLOPT_HEADER          => false,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_COOKIESESSION   => true,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_CONNECTTIMEOUT  => 5,
        CURLOPT_TIMEOUT         => 30,
    ];

    const URL = 'https://last.fm';

    const IPV4_REGEXP = '/([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\:?([0-9]{1,5})?/';
    const IPV6_REGEXP = '/(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/';

    /**
     * Получение содержимого страницы с использованием рабочего прокси
     * @param string $url URL страницы
     * @return mixed содержимое либо false
     */
    public static function _get(string $url = '')
    {
        self::$error = null;

        if (empty(self::$workingProxy) or empty($url)) return false;

        $curl = curl_init();

        self::$curlOptions[CURLOPT_URL]   = $url;
        self::$curlOptions[CURLOPT_PROXY] = self::$workingProxy;

        curl_setopt_array($curl, self::$curlOptions);

        $content = curl_exec($curl);

        if ($errno = curl_errno($curl)) {
            self::$error = new stdClass;
            self::$error->method  = __METHOD__ . '(' . $url . ')';
            self::$error->message = 'Error ' . $errno . ': ' . curl_error($curl);
        }

        curl_close($curl);

        return self::$error ? false : $content;
    }

    /**
     * Загрузка в self::$proxies адресов прокси-серверов
     */
    public static function _lista()
    {
        self::$error   = null;
        self::$proxies = [];

        foreach (self::PROXIES as $source) {

            if (false !== ($proxies = file($source, FILE_SKIP_EMPTY_LINES))) {
                $proxies = array_map(function($proxy) {
                    return preg_match(self::IPV4_REGEXP, $proxy, $matches) ? $matches[0] : null;
                }, $proxies);

                $proxies = array_filter($proxies);
                $proxies = array_values($proxies);

                self::$proxies = array_merge(self::$proxies, $proxies);
            } else if (self::ERRLOG_FLAG) {
                self::$error = new stdClass;
                self::$error->method  = __METHOD__;
                self::$error->message = 'Invalid Response (' . $source . ')';

                self::_log();
            }

        }

        self::$index = 0;
        self::$count = count(self::$proxies);
        self::$workingProxy = null;

        return;
    }

    public static function _log(string $what = '')
    {
        if (!self::ERRLOG_FLAG) return;

        if (empty($what)) {
            $what = is_scalar(self::$error) ? self::$error : json_encode(self::$error, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
        }

        error_log(date('d.m.Y H:i:s') . ' : ' . $what . PHP_EOL, 3, self::ERRLOG_FILE);
    }

    /**
     * Проверка работоспособности прокси-сервера из списка
     * @return boolean результат проверки
     */
    public static function _pinga()
    {
        self::$error        = null;
        self::$workingProxy = null;

        if (self::$index >= self::$count or !isset(self::$proxies[self::$index])) return false;

        $proxy = self::$proxies[self::$index];

        $curl = curl_init();

        self::$curlOptions[CURLOPT_URL]   = self::URL;
        self::$curlOptions[CURLOPT_PROXY] = $proxy;

        curl_setopt_array($curl, self::$curlOptions);

	    $content = curl_exec($curl);

        if ($content) {
            self::$workingProxy = $proxy;
        } else {
            self::$error = new stdClass;
            self::$error->method  = __METHOD__ . '(' . $proxy . ')';
            self::$error->message = curl_error($curl);
        }

	    curl_close($curl);

        return empty(self::$error);
    }

}
