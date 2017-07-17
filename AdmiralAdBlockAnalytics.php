<?php

class AdmiralAdBlockAnalytics
{

    /**
     * Suffix to append to the user-agent when proxying requests
     * @var string
     */
    public static $UASuffix = "ADMIRALWP/1.6.1";

    /**
     * Number of previuos days we should generate random strings for
     * This should be large enough to support someone being on the site for a
     * long time before making a request for a file. In this case we're saying
     * that long is 21 days.
     */
    const RANDOM_FILE_PREVIOUS_DAYS = 21;

    /**
     * Number of seconds to offset the $now time when calling strtotime in
     * getRandomFilenames. This is used for testing only and should never be
     * changed in production
     * @var int
     */
    public static $randomFileTimeOffset = 0;

    /**
     * Prefix to append on the front of the script as the full URI
     * @var string
     */
    public static $scriptURIPrefix = "/wp-includes/js/";

    /**
     * Admiral PropertyID you get when signing up for Admiral
     * @var string
     */
    private static $propertyID = "";

    /**
     * Public endpoint to source the JS from
     * @var string
     */
    public static $publicSourceScriptURI = "//owlsr.us/js?p={PROPERTYID}&e={ENDPOINT}&f={SCRIPTNAME}";

    /**
     * Public endpoint to source the test JS from
     * @var string
     */
    public static $publicTestSourceScriptURI = "//staging.owlsr.us/js?p={PROPERTYID}&e={ENDPOINT}&f={SCRIPTNAME}";

    /**
     * Public endpoint to proxy the record to
     * @var string
     */
    public static $publicRecordEndpoint = "//owlsr.us/record";


    /**
     * List of headers to copy over when proxying the script
     * @var array
     */
    public static $headersToCopy = array("Vary",
                                         "Content-Encoding",
                                         "Expires",
                                         "Last-Modified",
                                         "Content-Type",
                                         "X-Hostname",
                                         "Date",
                                         "Cache-Control",
                                         );

    /**
     * Check to make sure we don't generate a name of a file that already exists
     *  in the wp-includes directory
     * @var bool
     */
    public static $preventExistingScriptOverwrite = true;

    /**
     * Whether we should always append php to all filenames
     * @var bool
     */
    public static $alwaysAppendPHP = false;

    public static function setPropertyID($propertyID)
    {
        if (empty($propertyID)) {
            throw new Exception("PropertyID cannot be empty");
        }
        self::$propertyID = $propertyID;
    }

    public static function getPropertyID()
    {
        return self::$propertyID;
    }

    public static function enabled()
    {
        return !empty(self::$propertyID);
    }

    /**
     * @param $seeds array(string) list of string seeds that must be constant
     * across all requests for the same content across the last 7 days
     */
    public static function getRandomFilenames($seeds)
    {
        $fileNames = array();
        $seed = 0;
        // calculate a "seed" by just adding up all the ascii values of all the
        // seeds they passed in
        foreach ($seeds as $s) {
            for ($i = 0; $i < strlen($s); $i++) {
                $seed += ord($s[$i]);
            }
        }
        // make sure that doesURIContainRandomFilename matches these chars
        $allChars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_";
        $allCharsLen = strlen($allChars);
        $now = time() + self::$randomFileTimeOffset;
        for ($i = 0; $i >= (int)(-1 * self::RANDOM_FILE_PREVIOUS_DAYS); $i--) {
            $time = strtotime("$i days 00:00:00 UTC", $now);
            // trim off all the zeros from the end of the time, this is so we
            // have a random assortment of numbers we can use for the mod later
            $timeMinusZeros = (int)rtrim($time, "0");
            // calculate the length of the filename as a function of the time
            // (minus zeros)
            $len = ($timeMinusZeros % 7 === 0 ? 6 :
                    ($timeMinusZeros % 13 === 0 ? 7 :
                     ($timeMinusZeros % 11 === 0 ? 8 : 9)
                    )
            );

            // copy the seed so we don't alter it for the next
            $timeSeed = $seed;
            // add the time to the seed by just taking each character as a
            // number
            // we can use the $timeMinusZeros since zeros don't add anything
            $timeMinusZerosStr = (string)$timeMinusZeros;
            for ($j = 0; $j < strlen($timeMinusZerosStr); $j += 2) {
                $a = $timeMinusZerosStr[$j];
                // if there isn't a second character then just duplicate $a
                $b = isset($timeMinusZerosStr[$j + 1]) ? $timeMinusZerosStr[$j + 1] : $a;
                $timeSeed += (int)($a . $b);
            }

            // now we're actually going to generate a name and base it off the
            // $timeSeed
            $name = "";
            for ($j = 0; $j < $len; $j++) {
                $idx = $timeSeed % $allCharsLen;
                $name .= $allChars[$idx];
                if (isset($name[$j - 2])) {
                    $timeSeed += ord($name[$j - 2]) - ord($name[$j - 1]) + $j;
                } else {
                    $timeSeed += $idx + pow($j + 2, 2);
                }
            }
            // make sure we don't end or start with a non-alphanumeric character
            $name = trim($name, "-.");
            $fileNames[] = $name;
        }
        return $fileNames;
    }

    public static function getSeedsForScript($scriptName = "", $host = "")
    {
        // we have to do the concatination without anything between for backwards compat
        $seeds = self::getSeedsWithHost("hazadblock" . $scriptName, $host);
        return $seeds;
    }

    public static function getSeedsForRecord($host = "")
    {
        // we have to do the concatination without anything between for backwards compat
        $seeds = self::getSeedsWithHost("hoothoot", $host);
        return $seeds;
    }

    public static function getSeedsForDebug($host = "")
    {
        // we have to do the concatination without anything between for backwards compat
        $seeds = self::getSeedsWithHost("debug", $host);
        return $seeds;
    }

    public static function getPublicScriptURL($host = "", $scriptName = "")
    {
        $seeds = self::getSeedsForScript($scriptName, $host);
        // reverse the result so the newest one is on top
        $filenames = self::getRandomFilenames($seeds);
        $filename = reset($filenames);
        while (!empty($filename) && self::doesWPContentFileExist($filename)) {
            array_shift($filenames);
            $filename = reset($filenames);
        }
        if (empty($filenames)) {
            return '';
        }
        $suffix = ".js";
        if (self::$alwaysAppendPHP) {
            $suffix = ".php";
        }
        return AdmiralAdBlockAnalytics::$scriptURIPrefix . end($filenames) . $suffix;
    }

    //todo: move this out of this library
    public static function doesWPContentFileExist($filename)
    {
        if (defined('WP_CONTENT_DIR')) {
            $root = preg_replace("/\\/wp-content.*$/", "", WP_CONTENT_DIR);
        } elseif (defined('ABSPATH')) {
            $root = ABSPATH;
        } else {
            //todo: error log
            return false;
        }
        return file_exists($root . AdmiralAdBlockAnalytics::$scriptURIPrefix . $filename);
    }

    public static function getRecordEndpoint($host = "")
    {
        $seeds = self::getSeedsForRecord($host);
        // reverse the result so the newest one is on top
        $filenames = self::getRandomFilenames($seeds);
        if (empty($filenames)) {
            return "";
        }
        $suffix = "";
        if (self::$alwaysAppendPHP) {
            $suffix = ".php";
        }
        return "/" . end($filenames) . $suffix;
    }

    public static function getDebugEndpoint($host = "")
    {
        $seeds = self::getSeedsForDebug($host);
        // reverse the result so the newest one is on top
        $filenames = self::getRandomFilenames($seeds);
        if (empty($filenames)) {
            return "";
        }
        return "/" . end($filenames);
    }

    private static function httpCall($url, $headers = array(), $postBody = "")
    {
        $res = array("source" => "",
                     "error" => null,
                     "headers" => array(),
                     );
        $urlWithScheme = "https:" . $url;
        $ua = self::$UASuffix;
        $foundUA = false;
        foreach ($headers as $key => $header) {
            if (stripos($header, "user-agent") !== false) {
                $parts = explode(":", $header, 2);
                $ua = (isset($parts[1]) ? trim($parts[1]) . " " : "") . $ua;
                $headers[$key] = "User-Agent: $ua";
                $foundUA = true;
            }
        }
        if (!$foundUA) {
            $headers[] = "User-Agent: $ua";
        }
        if (function_exists("curl_init")) {
            if (!empty($postBody) && !defined("CURLOPT_SAFE_UPLOAD") && stripos($postBody, "@") === 0) {
                $res["error"] = array("code" => -99999999,
                                      "str" => "Unallowed postBody starting with @",
                                      "type" => "self",
                                      );
                return $res;
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $urlWithScheme);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            // we cannot use FOLLOWLOCATION is open_basedir is set
            //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
            // try to prevent caching
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
            $verbose = null;
            if (function_exists("stream_get_contents")) {
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                $verbose = fopen("php://temp", "w+");
                curl_setopt($ch, CURLOPT_STDERR, $verbose);
            }
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            if (!empty($postBody)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
                if (defined("CURLOPT_SAFE_UPLOAD")) {
                    curl_setopt($ch, CURLOPT_SAFE_UPLOAD, 1);
                }
            }
            curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . "/caroots.crt");
            $src = curl_exec($ch);
            if ($src === false) {
                $err = curl_errno($ch);
                $str = "";
                if (function_exists("curl_strerror")) {
                    $str = curl_strerror($err);
                }
                $verboseLog = "";

                if (!empty($verbose) && function_exists("rewind")) {
                    rewind($verbose);
                    $verboseLog = stream_get_contents($verbose);
                }
                $res["error"] = array("code" => $err,
                                      "str" => $str,
                                      "type" => "curl",
                                      "debug" => $verboseLog,
                                      );
            } else {
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                if (function_exists("mb_substr")) {
                    $header = mb_substr($src, 0, $headerSize);
                } else {
                    $header = substr($src, 0, $headerSize);
                }
                $recvHeaders = explode("\r\n", $header);
                foreach ($recvHeaders as $h) {
                    foreach (self::$headersToCopy as $hc) {
                        if (stripos($h, $hc) === 0) {
                            $res["headers"][] = $h;
                            break;
                        }
                    }
                }

                //todo: look for "Location" header

                if (function_exists("mb_substr")) {
                    $res["source"] = mb_substr($src, $headerSize);
                } else {
                    $res["source"] = substr($src, $headerSize);
                }
            }
            curl_close($ch);

        } elseif (function_exists("wp_remote_get")) {
            $resp = wp_remote_get($urlWithScheme, array(
                'headers' => $headers,
                'user-agent' => $ua,
            ));
            if (function_exists('is_wp_error') && is_wp_error($resp)) {
                $res["error"] = array("code" => $resp->get_error_code(),
                                      "str" => $resp->get_error_message(),
                                      "type" => "wp",
                                      );
            } else {
                $body = wp_remote_retrieve_body($resp);
                if (empty($body)) {
                    $res["error"] = array("code" => 0,
                                          "str" => "Unknown error but empty body",
                                          "type" => "wp",
                                          );
                } else {
                    $res["source"] = $body;
                    $headers = wp_remote_retrieve_headers($resp);
                    foreach ($headers as $key => $val) {
                        $src["headers"][] = "$key: $val";
                    }
                }
            }
        }
        return $res;
    }

    public static function fetchScript($headers = array(), $endpointHost = "", $scriptName = "", $testVersion = false)
    {
        $scriptURI = self::$publicSourceScriptURI;
        if ($testVersion) {
            $scriptURI = self::$publicTestSourceScriptURI;
        }
        $url = str_replace("{PROPERTYID}", urlencode(self::$propertyID), $scriptURI);
        $endpoint = "";
        if (!empty($endpointHost)) {
            $endpoint = self::getRecordEndpoint($endpointHost);
        }
        $url = str_replace("{ENDPOINT}", urlencode($endpoint), $url);
        if (empty($scriptName)) {
            $scriptName = "default";
        }
        $url = str_replace("{SCRIPTNAME}", urlencode($scriptName), $url);
        $res = self::httpCall($url, $headers);
        $res["success"] = empty($res["error"]) && !empty($res["source"]);
        return $res;
    }

    // todo: this should be private
    public static function getSeedsWithHost($name, $host = "")
    {
        $seeds = array($name);
        if (!empty($host)) {
            $seeds[] = $host;
        }
        return $seeds;
    }

    public static function doesURIContainRandomFilename($uri, $seeds)
    {
        $scriptFilenamesKeyed = array_flip(AdmiralAdBlockAnalytics::getRandomFilenames($seeds));
        $filename = basename($uri);
        if (empty($filename)) {
            return false;
        }
        if (function_exists('preg_match')) {
            if (preg_match("/^([a-zA-Z0-9\-_]+)/", $filename, $matches)) {
                $filename = $matches[1];
            }
        } else {
            // strip off the suffixes
            $endIndex = strpos($filename, "?");
            if ($endIndex !== false) {
                $filename = substr($filename, 0, $endIndex);
            }
            $endIndex = strpos($filename, ".");
            if ($endIndex !== false) {
                $filename = substr($filename, 0, $endIndex);
            }
        }
        if (!isset($scriptFilenamesKeyed[$filename])) {
            return false;
        }
        return true;
    }

    public static function proxyRecord($body, $headers = array())
    {
        $res = self::httpCall(self::$publicRecordEndpoint, $headers, $body);
        return $res;
    }

}

/* EOF */
