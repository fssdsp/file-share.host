<?php

/**
 * MIT License
 * 
 * Copyright (c) 2017 fssdsp
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

class FileShareTop {
	private $Url;
	private $Username;
	private $Password;
	private $HostInfo;
	private $COOKIE_JAR = '/tmp/fileshare.cookie';
	private $LOGIN_URL = "http://file-share.top//login";

	public function __construct($Url, $Username, $Password, $HostInfo) {
		$this->Url = $Url;
		$this->Username = $Username;
		$this->Password = $Password;
		$this->HostInfo = $HostInfo;
	}

	public function Verify($ClearCookie) {
		$ret = $this->login();
		if ($ClearCookie && file_exists($this->COOKIE_JAR)) {
			unlink($this->COOKIE_JAR);
		}
		return $ret;
	}

	public function GetDownloadInfo() {
		if($this->login() == LOGIN_FAIL) {
			$DownloadInfo = array();
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_REQUIRED_PREMIUM;
			return $DownloadInfo;
		}
		return $this->parseDownloadLink();
	}

	private function login() {
		$CSRF = '';
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
		curl_setopt($curl, CURLOPT_COOKIEJAR, $this->COOKIE_JAR);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $this->COOKIE_JAR);
		curl_setopt($curl, CURLOPT_HEADER, TRUE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_URL, $this->LOGIN_URL);
		$GetResult = curl_exec($curl);
		curl_close($curl);
		if (FALSE != $GetResult) {
			$DOM = new DOMDocument;
			$DOM->loadHTML($GetResult);
			$Inputs = $DOM->getElementsByTagName('input');
			foreach ($Inputs as $Input) {
				if ($Input->getAttribute('name') == 'csrf') {
					$CSRF = $Input->getAttribute('value');
				}
			}
		}

		if ('' != $CSRF) {
			$PostData = array(
				'csrf'=>$CSRF,
				'email'=>$this->Username,
				'password'=>$this->Password
			);

			$PostData = http_build_query($PostData);
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $PostData);
			curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
			curl_setopt($curl, CURLOPT_COOKIEJAR, $this->COOKIE_JAR);
			curl_setopt($curl, CURLOPT_COOKIEFILE, $this->COOKIE_JAR);
			curl_setopt($curl, CURLOPT_HEADER, TRUE);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($curl, CURLOPT_URL, $this->LOGIN_URL);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded', 'Cookie: csrf=' + $CSRF));
			$LoginInfo = curl_exec($curl);
			curl_close($curl);

			if (FALSE != $LoginInfo) {
				$DOM = new DOMDocument;
				$DOM->loadHTML($LoginInfo);
				$Inputs = $DOM->getElementsByTagName('input');
				foreach ($Inputs as $Input) {
					if ($Input->getAttribute('name') == 'email') {
						return LOGIN_FAIL;
					}
				}
				return USER_IS_PREMIUM;
			}
		}

		return LOGIN_FAIL;
	}

	private function parseDownloadLink() {
		$DownloadUrl = $this->Url;
		if (strpos($this->Url, '/file/download/') == FALSE) {
			$DownloadUrl = str_replace('/file/', '/file/download/', $this->Url);
		}

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
		curl_setopt($curl, CURLOPT_COOKIEJAR, $this->COOKIE_JAR);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $this->COOKIE_JAR);
		curl_setopt($curl, CURLOPT_HEADER, TRUE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, FALSE);
		curl_setopt($curl, CURLOPT_URL, $DownloadUrl);
		$GetResult = curl_exec($curl);
		$GetInfo = curl_getinfo($curl);
		curl_close($curl);
		if (FALSE != $GetResult) {
			$Headers = substr($GetResult, 0, $GetInfo["header_size"]);
			preg_match("!\r\n(?:Location|URI): *(.*?) *\r\n!", $Headers, $Matches);
			$DownloadUrl = $Matches[1];
		}

		if (strpos($DownloadUrl, '/?') == FALSE) {
			$DownloadUrl = str_replace('?', '/?', $DownloadUrl);
		}

		$DownloadInfo = array();
		$DownloadInfo[DOWNLOAD_URL] = $DownloadUrl;
		$DownloadInfo[DOWNLOAD_COOKIE] = $this->COOKIE_JAR;
		return $DownloadInfo;
	}
}
?>