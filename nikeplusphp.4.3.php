<?php

/**
 * A PHP class that makes it easy to get your data from the Nike+ service 
 * 
 * Nike+PHP v4.x requires PHP 5 with SimpleXML and cURL.
 * Nike+PHP also requires your Nike log in credentials.
 * 
 * @author Charanjit Chana - http://charanj.it
 * @link http://nikeplusphp.org
 * @version 4.3
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
    public $loginCookies, $userId, $activities = false, $allTime = false;
    
    /**
     * Private variables
     */
    private $cookie, $userAgent = 'Mozilla/5.0';

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
		$allHeaders = array();
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
            $allHeaders[] = @str_replace(array("\n\r", "\r\n", "\r", "\n\n", "\r\r"), "", $headerSections[$i]);
        }
        foreach($allHeaders as $h) {
            $exploded[] = explode('; ', $h);
        }
        foreach($exploded as $e) {
            $string[] = $e[0];
        }
        $header = implode(';', $string);
        $this->cookie = $header;
        $this->loginCookies = json_decode($body);
        $this->userId = $this->loginCookies->serviceResponse->body->User->screenName;
        $this->allTime();
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
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
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
    public function activities($limit = 0, $checkTotal = false) {
        $start = 0;
        if($limit == 0) {
    		$limit = 1000000;
    	} else {
    		--$limit;
    	}
    	if($limit != count($this->activities) ||($checkTotal && ($this->activities && count($this->activities) < $this->allTime->lifetimeTotals->run))) {
    		$this->activities = false;
    	}
		if(!$this->activities) {
			$results = $this->getNikePlusFile('http://nikeplus.nike.com/plus/activity/running/'.rawurlencode($this->userId).'/lifetime/activities?indexStart='.$start.'&indexEnd='.$limit);
			foreach($results->activities as $activity) {
				$this->activities[$activity->activity->activityId] = $activity->activity;
			}
			krsort($this->activities);
		}
		return $this->activities;
    }
    
    /**
     * allTime()
     * a list of your all time stats 
     *
     * @return object
     */
     public function allTime() {
		if(!$this->allTime) {
			$this->allTime = $this->getNikePlusFile('http://nikeplus.nike.com/plus/activity/running/'.rawurlencode($this->userId).'/lifetime/activities?indexStart=999999&indexEnd=1000000');
		}
		return $this->allTime;
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
	 * mostRecentRun()
	 * collects the data of the latest run
	 * 
	 * @return object
	 */
	public function mostRecentRun() {
		$activities = $this->activities();
		return reset($activities);
	}
	
	/**
	 * firstRun()
	 * collects the data of the first run
	 * 
	 * @return object
	 */
	public function firstRun() {
		$activities = $this->activities(0, true);
		return end($activities);
	}
    
    /**
     * toMiles()
     * Convert a value from Km in to miles
     * 
     * @param float|string $distance
     * @param int $decimalPlaces optional - set the number of decimal places (default is 2), use to improve granularity
     * 
     * @return int
     */
    public function toMiles($distance, $decimalPlaces = 2) {
        return $this->toTwoDecimalPlaces((float) $distance * 0.6213711922, $decimalPlaces);
    }
    
    /**
     * toHours()
     * convert a value to hours
     *
     * @param float $time
     *
     * @return string
     */
    public function toHours($time) {
    	return intval($time / 3600000) % 60;
    }
    
    /**
     * toMinutes()
     * convert a value to minutes
     *
     * @param float $time
     *
     * @return string
     */
    public function toMinutes($time) {
    	return intval($time / 60000) % 60;
    }
    
    /**
     * toSeconds()
     * convert a value to seconds
     *
     * @param float $time
     *
     * @return string
     */
    public function toSeconds($time) {
    	return intval($time / 1000) % 60;
    }
    
    /**
     * padNumber()
     * pad numbers less than 10 to have a leading 0
     * 
     * @param int $number
     * 
     * @return string
     */
    public function padNumber($number){
        if($number < 10 && $number >= 0) {
            return '0'.$number;
        }
        return $number;
    }
    
    /**
     * formatDuration()
     * convert a duration into minutes and seconds, or
     * hours, minutes and seconds if hours are available
     *
     * @param float $time
     * @param boolean $hideZeroHours - hide the hour figure if it is zero
     *
     * @return string
     */
    public function formatDuration($time, $hideZeroHours = true, $hideSeconds = false) {
    	$hours = $this->toHours($time);
    	$minutes = $this->toMinutes($time);
    	$seconds = $this->toSeconds($time);
    	$formattedTime = $this->padNumber($minutes);
    	if(!$hideSeconds) {
    		$formattedTime .= ':'.$this->padNumber($seconds);
    	}
    	if($hours > 0 || !$hideZeroHours) {
    		$formattedTime = $hours.':'.$formattedTime;
    	}
    	return $formattedTime;
    }
    
    /**
     * toDecimalPlaces()
     * convert a value to minutes
     *
     * @param float $time
     * @param int $decimalPlaces optional - set the number of decimal places (default is 2), use to improve granularity
     *
     * @return string
     */    
    public function toTwoDecimalPlaces($number, $decimalPlaces = 2) {
    	return number_format((float) $number, $decimalPlaces, '.', ',');
    }
}