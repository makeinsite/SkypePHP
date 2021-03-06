<?php
/*
   _____ _                     _____  _    _ _____  
  / ____| |                   |  __ \| |  | |  __ \ 
 | (___ | | ___   _ _ __   ___| |__) | |__| | |__) |
  \___ \| |/ / | | | '_ \ / _ \  ___/|  __  |  ___/ 
  ____) |   <| |_| | |_) |  __/ |    | |  | | |     
 |_____/|_|\_\\__, | .__/ \___|_|    |_|  |_|_|     
               __/ | |                              
              |___/|_|      
			  
Version: 1.2.1 by Kibioctet (admin@n-mail.fr)
GitHub : https://github.com/Kibioctet/SkypePHP
*/

class Skype {
	public $username;
	private $password, $registrationToken, $skypeToken, $expiry = 0, $logged = false, $hashedUsername;
	
	public function __construct($username, $password, $folder = "skypephp") {
		$this->username = $username;
		$this->password = $password;
		$this->folder = $folder;
		$this->hashedUsername = sha1($username);
		
		if (file_exists($this->folder)) {
			if (file_exists("{$this->folder}/auth_{$this->hashedUsername}")) {
				$auth = json_decode(file_get_contents("{$this->folder}/auth_{$this->hashedUsername}"), true);
				if (time() >= $auth["expiry"])
					unset($auth);
			}
		} else {
			if (!mkdir("{$this->folder}"))
				exit(trigger_error("Skype : Unable to create the SkypePHP directoy.", E_USER_WARNING));
		}
		
		if (isset($auth)) {
			$this->skypeToken = $auth["skypeToken"];
			$this->registrationToken = $auth["registrationToken"];
			$this->expiry = $auth["expiry"];
		} else {
			$this->login();
		}
	}
	
	private function login() {
		$loginForm = $this->web("https://login.skype.com/login/oauth/microsoft?client_id=578134&redirect_uri=https%3A%2F%2Fweb.skype.com%2F&username={$this->username}", "GET", [], true, true);
		
		preg_match("`urlPost:'(.+)',`isU", $loginForm, $loginURL);
		$loginURL = $loginURL[1];
		
		preg_match("`name=\"PPFT\" id=\"(.+)\" value=\"(.+)\"`isU", $loginForm, $ppft);
		$ppft = $ppft[2];
		
		preg_match("`t:\'(.+)\',A`isU", $loginForm, $ppsx);
		$ppsx = $ppsx[1];
		
		preg_match_all('`Set-Cookie: (.+)=(.+);`isU', $loginForm, $cookiesArray);
		$cookies = "";
		for ($i = 0; $i <= count($cookiesArray[1])-1; $i++)
			$cookies .= "{$cookiesArray[1][$i]}={$cookiesArray[2][$i]}; ";
		
		$post = [
			"loginfmt" => $this->username,
			"login" => $this->username,
			"passwd" => $this->password,
			"type" => 11,
			"PPFT" => $ppft,
			"PPSX" => $ppsx,
			"NewUser" => (int)1,
			"LoginOptions" => 3,
			"FoundMSAs" => "",
			"fspost" => (int)0,
			"i2" => (int)1,
			"i16" => "",
			"i17" => (int)0,
			"i18" => "__DefaultLoginStrings|1,__DefaultLogin_Core|1,",
			"i19" => 556374,
			"i21" => (int)0,
			"i13" => (int)0
		];
		
		
		$loginForm = $this->web($loginURL, "POST", $post, true, true, $cookies);
		
		preg_match("`<input type=\"hidden\" name=\"NAP\" id=\"NAP\" value=\"(.+)\">`isU", $loginForm, $NAP);
		preg_match("`<input type=\"hidden\" name=\"ANON\" id=\"ANON\" value=\"(.+)\">`isU", $loginForm, $ANON);
		preg_match("`<input type=\"hidden\" name=\"t\" id=\"t\" value=\"(.+)\">`isU", $loginForm, $t);
		if (!isset($NAP[1]) || !isset($ANON[1]) || !isset($t[1]))
			exit(trigger_error("Skype : Authentication failed for {$this->username}", E_USER_WARNING));
		
		$NAP = $NAP[1];
		$ANON = $ANON[1];
		$t = $t[1];
		
		preg_match_all('`Set-Cookie: (.+)=(.+);`isU', $loginForm, $cookiesArray);
		$cookies = "";
		for ($i = 0; $i <= count($cookiesArray[1])-1; $i++)
			$cookies .= "{$cookiesArray[1][$i]}={$cookiesArray[2][$i]}; ";
		
		$post = [
			"NAP" => $NAP,
			"ANON" => $ANON,
			"t" => $t
		];
		
		
		$loginForm = $this->web("https://lw.skype.com/login/oauth/proxy?client_id=578134&redirect_uri=https://web.skype.com/&site_name=lw.skype.com&wa=wsignin1.0", "POST", $post, true, true, $cookies);
		
		preg_match("`<input type=\"hidden\" name=\"t\" value=\"(.+)\"/>`isU", $loginForm, $t);
		$t = $t[1];
		
		$post = [
			"t" => $t,
			"site_name" => "lw.skype.com",
			"oauthPartner" => 999,
			"form" => "",
			"client_id" => 578134,
			"redirect_uri" => "https://web.skype.com/"
		];
		
		
		$login = $this->web("https://login.skype.com/login/microsoft?client_id=578134&redirect_uri=https://web.skype.com/", "POST", $post);
		
		preg_match("`<input type=\"hidden\" name=\"skypetoken\" value=\"(.+)\"/>`isU", $login, $skypeToken);
		$this->skypeToken = $skypeToken[1];
		
		
		$login = $this->web("https://client-s.gateway.messenger.live.com/v1/users/ME/endpoints", "POST", "{}", true);
		
		preg_match("`registrationToken=(.+);`isU", $login, $registrationToken);
		$this->registrationToken = $registrationToken[1];
		
		
		$expiry = time()+21600;
		
		$cache = [
			"skypeToken" => $this->skypeToken,
			"registrationToken" => $this->registrationToken,
			"expiry" => $expiry
		];
		
		$this->expiry = $expiry;
		$this->logged = true;
		
		file_put_contents("{$this->folder}/auth_{$this->hashedUsername}", json_encode($cache));
		
		return true;
	}
	
	private function web($url, $mode = "GET", $post = [], $showHeaders = false, $follow = true, $customCookies = "", $customHeaders = []) {
		if (!function_exists("curl_init"))
			exit(trigger_error("Skype : cURL is required", E_USER_WARNING));
		
		if (!empty($post) && is_array($post))
			$post = http_build_query($post);
		
		if ($this->logged && time() >= $this->expiry) {
			$this->logged = false;
			$this->login();
		}
		
		$headers = $customHeaders;
		if (isset($this->skypeToken)) {
			$headers[] = "X-Skypetoken: {$this->skypeToken}";
			$headers[] = "Authentication: skypetoken={$this->skypeToken}";
		}
		
		if (isset($this->registrationToken))
			$headers[] = "RegistrationToken: registrationToken={$this->registrationToken}";
		
		$curl = curl_init();
		
		curl_setopt($curl, CURLOPT_URL, $url);
		if (!empty($headers))
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $mode);
		if (!empty($post)) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		}
		if ($customCookies)
			curl_setopt($curl, CURLOPT_COOKIE, $customCookies);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.143 Safari/537.36");
		curl_setopt($curl, CURLOPT_HEADER, $showHeaders);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, $follow);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($curl);
		
		curl_close($curl);
		
		return $result;
	}
	
	public function logout() {
		if (!$this->logged)
			return true;
		
		unlink("{$this->folder}/auth_{$this->username}");
		unset($this->skypeToken);
		unset($this->registrationToken);
		
		return true;
	}
	
	private function URLToUser($url) {
		$url = explode(":", $url, 2);
		
		return end($url);
	}
	
	private function timestamp() {
		return str_replace(".", "", microtime(1));
	}
	
	public function sendMessage($user, $message) {
		$user = $this->URLtoUser($user);
		$mode = strstr($user, "thread.skype") ? 19 : 8;
		$messageID = $this->timestamp();
		$post = [
			"content" => $message,
			"messagetype" => "RichText",
			"contenttype" => "text",
			"clientmessageid" => $messageID
		];
		
		$req = json_decode($this->web("https://client-s.gateway.messenger.live.com/v1/users/ME/conversations/$mode:$user/messages", "POST", json_encode($post)), true);
		
		return isset($req["OriginalArrivalTime"]) ? $messageID : 0;
	}
	
	public function getMessagesList($user, $size = 100) {
		$user = $this->URLtoUser($user);
		if ($size > 199 or $size < 1)
			$size = 199;
		$mode = strstr($user, "thread.skype") ? 19 : 8;
		
		$req = json_decode($this->web("https://client-s.gateway.messenger.live.com/v1/users/ME/conversations/$mode:$user/messages?startTime=0&pageSize=$size&view=msnp24Equivalent&targetType=Passport|Skype|Lync|Thread"), true);
		
		return !isset($req["message"]) ? $req["messages"] : [];
	}
	
	public function createGroup($users = [], $topic = "") {
		$users = [];
		
		foreach ($users as $user)
			$members["members"][] = ["id" => "8:".$this->URLtoUser($user), "role" => "User"];
		
		$members["members"][] = ["id" => "8:{$this->username}", "role" => "Admin"];
		
		$req = $this->web("https://client-s.gateway.messenger.live.com/v1/threads", "POST", json_encode($members), true);
		preg_match("`19\:(.+)\@thread.skype`isU", $req, $group);
		
		$group = isset($group[1]) ? "{$group[1]}@thread.skype" : "";
		
		if (!empty($topic) && !empty($group))
			$this->setGroupTopic($group, $topic);
		
		return $group;
	}
	
	public function setGroupTopic($group, $topic) {
		$group = $this->URLtoUser($group);
		$post = [
			"topic" => $topic
		];
		
		$this->web("https://client-s.gateway.messenger.live.com/v1/threads/19:$group/properties?name=topic", "PUT", json_encode($post));
	}
	
	public function getGroupInfo($group) {
		$group = $this->URLtoUser($group);
		$req = json_decode($this->web("https://client-s.gateway.messenger.live.com/v1/threads/19:$group?view=msnp24Equivalent", "GET"), true);
		
		return !isset($req["code"]) ? $req : [];
	}
	
	public function addUserToGroup($group, $user) {
		$user = $this->URLtoUser($user);
		$post = [
			"role" => "User"
		];
		
		$req = $this->web("https://client-s.gateway.messenger.live.com/v1/threads/19:$group/members/8:$user", "PUT", json_encode($post));
		
		return empty($req);
	}
	
	public function kickUser($group, $user) {
		$user = $this->URLtoUser($user);
		$req = $this->web("https://client-s.gateway.messenger.live.com/v1/threads/19:$group/members/8:$user", "DELETE");
		
		return empty($req);
	}
	
	public function leaveGroup($group) {
		$req = $this->kickUser($group, $this->username);
		
		return $req;
	}
	
	public function ifGroupHistoryDisclosed($group, $historydisclosed) {
		$group = $this->URLtoUser($group);
		$post = [
			"historydisclosed" => $historydisclosed
		];
		
		$req = $this->web("https://client-s.gateway.messenger.live.com/v1/threads/19:$group/properties?name=historydisclosed", "PUT", json_encode($post));
		
		return empty($req);
	}
	
	public function getContactsList() {
		$req = json_decode($this->web("https://contacts.skype.com/contacts/v1/users/{$this->username}/contacts?\$filter=type%20eq%20%27skype%27%20or%20type%20eq%20%27msn%27%20or%20type%20eq%20%27pstn%27%20or%20type%20eq%20%27agent%27&reason=default"), true);
		
		return isset($req["contacts"]) ? $req["contacts"] : [];
	}
	
	public function readProfile($list) {
		$contacts = "";
		foreach ($list as $contact)
			$contacts .= "contacts[]=$contact&";
		
		$req = json_decode($this->web("https://api.skype.com/users/self/contacts/profiles", "POST", $contacts), true);
		
		return !empty($req) ? $req : [];
	}
	
	public function readMyProfile() {
		$req = json_decode($this->web("https://api.skype.com/users/self/profile"), true);
		
		return !empty($req) ? $req : [];
	}
	
	public function searchSomeone($username) {
		$username = $this->URLtoUser($username);
		$req = json_decode($this->web("https://skypegraph.skype.com/search/v1.1/namesearch/swx/?requestid=skype.com-1.63.51&searchstring=$username"), true);
		
		return !empty($req) ? $req : [];
	}
	
	public function addContact($username, $greeting = "Hello, I would like to add you to my contacts.") {
		$username = $this->URLtoUser($username);
		$post = [
			"greeting" => $greeting
		];
		
		$req = $this->web("https://api.skype.com/users/self/contacts/auth-request/$username", "PUT", $post);
		$data = json_decode($req, true);
		
		return isset($data["code"]) && $data["code"] == 20100;
	}
	
	public function skypeJoin($id) {
		$post = [
			"shortId" => $id,
			"type" => "wl"
		];
		$group = $this->web("https://join.skype.com/api/v2/conversation/", "POST", json_encode($post), false, false, false, ["Content-Type: application/json"]);
		$group = json_decode($group, true);
		
		if (!isset($group["Resource"]))
			return "";
		
		$group = str_replace("19:", "", $group["Resource"]);
		
		return $this->addUserToGroup($group, $this->username);
	}
}