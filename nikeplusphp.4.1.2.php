<?php

/**
 * A PHP class that makes it easy to get your data from the Nike+ service 
 * 
 * NikePlusPHP v4.x requires PHP 5 with SimpleXML and cURL.
 * To get started you will need your Nike account information.
 * 
 * @author Charanjit Chana - http://charanj.it
 * @link http://nikeplusphp.org
 * @version 4.1.1
 * 
 * Usage:
 * $n = new NikePlusPHP('email@address.com', 'password');
 * $runs = $n->activities();
 * $run = $n->run('1234567890');
 */
 
class NikePlusPHP {

    /**
     * Public variables
     */
    public $loginCookies, $userId;
    
    /**
     * Private variables
     */
    private $cookie;

    /**
     * __construct()
     * Called when you initiate the class and keeps a cookie that allows you to keep authenticating
     * against the Nike+ website.
     * 
     * @param string $username your Nike username, should be an email address
     * @param string $password your Nike password 
     */
    public function __construct($username, $password) {
        $this->login($username, $password);
    }
    
    /**
     * login()
     * Called by __construct and performs the actual login action.
     * 
     * @param string $username
     * @param string $password
     * 
     * @return string
     */
    private function login($username, $password) {
        $url = 'https://secure-nikeplus.nike.com/nsl/services/user/login?app=b31990e7-8583-4251-808f-9dc67b40f5d2&format=json&contentType=plaintext';
        $loginDetails = 'app=b31990e7-8583-4251-808f-9dc67b40f5d2&format=json&contentType=plaintext&email='.urlencode($username).'&password='.$password;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //Set curl to return the data instead of printing it to the browser.
        curl_setopt($ch, CURLOPT_POSTFIELDS, $loginDetails);
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = curl_exec($ch);
        curl_close($ch);
        $noDoubleBreaks = str_replace(array("\n\r\n\r", "\r\n\r\n", "\n\n", "\r\r", "\n\n\n\n", "\r\r\r\r"), '||', $data);
        $sections = explode('||', $noDoubleBreaks);
        $headerSections = explode('Set-Cookie: ', $sections[0]);
        $body = $sections[1];
        for($i=1; $i<=count($headerSections); $i++) {
            $allheaders[] = @str_replace(array("\n\r", "\r\n", "\r", "\n\n", "\r\r"), "", $headerSections[$i]);
        }
        foreach($allheaders as $h) {
            $exploded[] = explode('; ', $h);
        }
        foreach($exploded as $e) {
            $string[] = $e[0];
        }
        $header = implode(';', $string);
        $this->cookie = $header;
        $this->loginCookies = json_decode($body);
        $this->userId = $this->loginCookies->serviceResponse->body->User->screenName;
    }

    /**
     * cookieValue()
     * returns the cookie value that has been set 
     */
    public function cookieValue() {
        return $this->cookie;
    }

    /**
     * getNikePlusFile()
     * collects the contents of the specified file from Nike+
     * 
     * @param string $path the file you wish to fetch
     */
    private function getNikePlusFile($path) {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
        curl_setopt($ch, CURLOPT_URL, $path);
        $data = curl_exec($ch);
        curl_close($ch);
        return json_decode(utf8_decode($data));
    }
    
    /**
     * activities()
     * a list of your runs/activities
     *
     * @param boolean $allTime option - returns all time aggregate data if true, individual run data if false (default) 
     *
     * @return object
     */
    public function activities($allTime = false) {
        $loop = true;
        $start = 0;
        $increment = 30;
        $activities = new stdClass;
        if(!$allTime) {
            while($loop == true) {
                $results = $this->getNikePlusFile('http://nikeplus.nike.com/plus/activity/running/'.rawurlencode($this->userId).'/lifetime/activities?indexStart='.$start.'&indexEnd='.($start+($increment - 1)));
                if(!isset($results->activities)) {
                    $loop = false;
                    break;
                }
                foreach($results->activities as $activity) {
                    $activities->activities[] = $activity->activity;
                }
                $start += $increment;
            }
        } else {
            $activities = $this->getNikePlusFile('http://nikeplus.nike.com/plus/activity/running/'.rawurlencode($this->userId).'/lifetime/activities?indexStart=999999&indexEnd=1000000');
        }
        return $activities;
    }
    
    /**
     * run()
     * collects the data of a specific run
     * 
     * @param string $id the id of the run you wish to get the data for
     *
     * @return object
     */
    public function run($id) {
        return $this->getNikePlusFile('http://nikeplus.nike.com/plus/running/ajax/'.$id);
    }
    
    /**
     * toMiles()
     * Convert a value from Km in to miles
     * 
     * @param float|string $distance
     * 
     * @return int
     */
    public function toMiles($distance) {
        return number_format(((float) $distance * 0.6213727366498068), 2, '.', ',');
    }
    
    
}