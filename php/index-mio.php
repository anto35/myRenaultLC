	<?php
$debuglog  = $_GET['debug'];

//echo "DEBUG=" . $debuglog . "<br>";

if (!isset($_GET['pass'])) {
    die("Not authorized");
} else {
	if ($_GET['pass'] != "miapasssegretissima") {
		die("Not authorized");
	}
    
  $username  = $_GET['username'];
  $password  = $_GET['password'];
  //$vin  = $_GET['vin'];

}



function logme($text) {
//echo "DEBUG='" . $debuglog . "'<br>";
//	if ($debuglog == "1") {
		echo $text;
//	}
}

session_cache_limiter('nocache');
require 'api-keys.php';
require 'config.php';
if (file_exists('lng/'.$country.'.php')) require 'lng/'.$country.'.php';
else require 'lng/EN.php';
if (empty(${$country})) $gigya_api = $GB;
else $gigya_api = ${$country};

//Evaluate parameters
if (isset($_GET['cron']) || (isset($argv[1]) && $argv[1] == 'cron')) {
  header('Content-Type: text/plain; charset=utf-8');
  $cmd_cron = TRUE;
} else {
  header('Content-Type: text/html; charset=utf-8');
  $cmd_cron = FALSE;
}
if (isset($_GET['acnow']) || (isset($argv[1]) && $argv[1] == 'acnow')) $cmd_acnow = TRUE;
else $cmd_acnow = FALSE;
if (isset($_GET['chargenow']) || (isset($argv[1]) && $argv[1] == 'chargenow')) $cmd_chargenow = TRUE;
else $cmd_chargenow = FALSE;
if (isset($_GET['cmon']) || (isset($argv[1]) && $argv[1] == 'cmon')) $cmd_cmon = TRUE;
else {
  $cmd_cmon = FALSE;
  if (isset($_GET['cmoff']) || (isset($argv[1]) && $argv[1] == 'cmoff')) $cmd_cmoff = TRUE;
  else $cmd_cmoff = FALSE;
}

$date_today = date_create('now');
$date_today = date_format($date_today, 'md');
$timestamp_now = date_create('now');
$timestamp_now = date_format($timestamp_now, 'YmdHi');

/**Retrieve cached data
/*
// UNSECURE UNLESS SAVED FILE IS ENCRYPTED
$session = file_get_contents('session');
if ($session !== FALSE) $session = explode('|', $session);
else*/

$session = array(
	'0000',             // 0: Date Gigya JWT Token request (md)
	'',                 // 1: Gigya JWT Token
	'',                 // 2: Renault account id
	'',                 // 3: MD5 hash of the last data retrieval
	'202001010000',     // 4: Timestamp of the last data retrieval (YmdHi)
	'N',                // 5: Action done when reaching battery level (Y/N)
	'N',                // 6: Car is charging (Y/N)
	'please wait...',   // 7: Mileage
	'please wait...',   // 8: Date status update
	'please wait...',   // 9: Time status update
	'please wait...',   // 10: Charging status
	'please wait...',   // 11: Cable status
	'please wait...',   // 12: Battery level
	'please wait...',   // 13: Battery temperature (Ph1) / battery capacity (Ph2)
	'please wait...',   // 14: Range in km
	'please wait...',   // 15: Charging time
	'please wait...',   // 16: Charging effect
	'please wait...',   // 17: Outside temperature (Ph1) / GPS-Latitude (Ph2)
	'please wait...',   // 18: GPS-Longitude (Ph2)
	'please wait...',   // 19: GPS: GPS time (Ph2, H:i) date (Ph2, d.m.Y)
	'please wait...',   // 20: GPS time (Ph2, H:i)
	'80',               // 21: Setting battery level for mail function
	'please wait...',   // 22: Outside temperature (Ph2, openweathermap API)
	'please wait...',   // 23: Weather condition (Ph2, openweathermap API)
	'please wait...'    // 24: Chargemode
);

//Retrieve setting battery level for mail function
if (isset($_POST['bl']) && is_numeric($_POST['bl']) && $_POST['bl'] >= 1 && $_POST['bl'] <= 99) {
  if ($_POST['bl'] > $session[21])
  	$session[5] = 'N';
  $session[21] = $_POST['bl'];
}

//Checking cron time interval
if ($cmd_cron == TRUE) {
  $s = date_create_from_format('YmdHi', $session[4]);
  if ($session[6] == 'Y') date_add($s, date_interval_create_from_date_string($cron_acs.' minutes'));
  else date_add($s, date_interval_create_from_date_string($cron_ncs.' minutes'));
  $s = date_format($s, 'YmdHi');
  if ($timestamp_now < $s) exit('INTERVAL NOT REACHED');
}

//Max one API request per minute
$s = date_create_from_format('YmdHi', $session[4]);
date_add($s, date_interval_create_from_date_string('1 minutes'));
$s = date_format($s, 'YmdHi');

if ($timestamp_now < $s)
	$update_authorized = FALSE;
else
	$update_authorized = TRUE;

logme( "<br>Login... <br>");
//Retrieve new Gigya token if the date has changed since last request
//if (empty($session[1]) || $session[0] !== $date_today) {
  //Login Gigya
  $update_authorized = TRUE;
  $postData = array(
    'ApiKey' => $gigya_api,
    'loginId' => $username,
    'password' => $password,
    'include' => 'all', // includes PersonId in response if ="data"
	'sessionExpiration' => 60
  );
  $ch = curl_init('https://accounts.eu1.gigya.com/accounts.login');
  curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));  
  $responseData = json_decode($response, TRUE);
  $personId = $responseData['data']['personId'];
  $oauth_token = $responseData['sessionInfo']['cookieValue'];
	if ($oauth_token === null) {
		echo "<br>LOGIN ERROR:<pre>" .  json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br>";
	} else {
		logme(  "--><br>personId: " .  $personId . "<br>");
		logme( "UID: '" .  $responseData['UID'] . "'<br>");
		/*logme( "<br>userInfo.UID:<br>'" .  $responseData['userInfo']['UID'] . "'<br>");
		logme( "<br>userInfo.loginProviderUID:<br>'" .  $responseData['userInfo']['loginProviderUID'] . "'<br>");
		logme( "<br>userInfo.identities[0].providerUID:<br>'" .  $responseData['userInfo']['identities'][0]['providerUID'] . "'<br>");
		logme( "<br>cookieValue:<br>'" .  $responseData['sessionInfo']['cookieValue']. "'<br>");
		*/
		logme( "invalid id_token:<br>'" .  $responseData['id_token']. "'<br>");
		$testme = $responseData['id_token'];
}
logme(  "<br>JWT... ");
  //Request Gigya JWT token
  $postData = array(
    'login_token' => $oauth_token,
    'ApiKey' => $gigya_api,
    'fields' => 'data.personId,data.gigyaDataCenter',
	'expiration' => 87000
  );
  $ch = curl_init('https://accounts.eu1.gigya.com/accounts.getJWT');
  //curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  //$session[1] = $responseData['id_token'];
  $id_token=  $responseData['id_token'];
 //$session[0] = $date_today;
//}

logme("--><br>JWT: '".$responseData['id_token'] . "<br>");
//logme($response . "<br>");

logme(  "Account...");
//Request Renault account id if not cached
//if (empty($session[2])) {
  //Request Kamereon account id
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token,
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/persons/'.$personId.'?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  //$session[2] = $responseData['accounts'][0]['accountId'];
  $accountId = $responseData['accounts'][0]['accountId'];
  $accountId2 = $responseData['accounts'][1]['accountId'];
//}
//logme( "<br><br>Account response:<br><pre>" .  json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br><br>");
logme("-->". $accountId . "<br><br>");


logme(  "<br>VIN... ");
//Request VIN
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token,
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId2.'/vehicles?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  //$session[2] = $responseData['accounts'][0]['accountId'];
  $vin = $responseData['vehicleLinks'][0]['vehicleDetails']['vin'];
  $regNum = $responseData['vehicleLinks'][0]['vehicleDetails']['registrationNumber'];
  $regDate = $responseData['vehicleLinks'][0]['vehicleDetails']['firstRegistrationDate'];
  $batteryCode = $responseData['vehicleLinks'][0]['vehicleDetails']['battery']['code'] . "/" . $responseData['vehicleLinks'][0]['vehicleDetails']['battery']['group'];

  $carModel = $responseData['vehicleLinks'][0]['vehicleDetails']['model']['label'] .
  	" (" .
	 $responseData['vehicleLinks'][0]['vehicleDetails']['model']['code'] .
	 "/" .
	 $responseData['vehicleLinks'][0]['vehicleDetails']['model']['group'] .
	 ")";

logme("-->". $vin . "<br>");
logme("Plate: " . $regNum . " (" . $regDate . ")<br>");
logme("Battery: " . $batteryCode . "<br>");
logme("Car model: " . $carModel . "<br>");

//logme( "<br><br>VIN response:<br><pre>" .  json_encode(json_decode($response), JSON_PRETTY_PRINT) . "</pre><br><br>");


$authstring =  "&pass=miapasssegretissima&username="  .  $username . "&password=" . $password . "&vin=" . $vin;

//Evaluate parameter "acnow" for preconditioning
if ($cmd_acnow === TRUE) {
  $postData = array(
    'Content-type: application/vnd.api+json',
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token
  );
  $jsonData = '{"data":{"type":"HvacStart","attributes":{"action":"start","targetTemperature":"21"}}}';
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/hvac-start?country='.$country . $authstring);
  //curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
}

//Evaluate parameter "chargenow" for instant charging
if ($cmd_chargenow === TRUE) {
  $postData = array(
    'Content-type: application/vnd.api+json',
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token
  );
  $jsonData = '{"data":{"type":"ChargingStart","attributes":{"action":"start"}}}';
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/charging-start?country='.$country);
  //curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
}

//Evaluate parameters "cmon" respectively "cmoff" for setting the chargemode
if ($cmd_cmon === TRUE || $cmd_cmoff === TRUE) {
  $postData = array(
    'Content-type: application/vnd.api+json',
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token
  );
  if ($cmd_cmon === TRUE) $jsonData = '{"data":{"type":"ChargeMode","attributes":{"action":"schedule_mode"}}}';
  else $jsonData = '{"data":{"type":"ChargeMode","attributes":{"action":"always_charging"}}}';
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/charge-mode?country='.$country);
  //curl_setopt($ch, CURLOPT_POST, TRUE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
}


//Request battery and charging status from Renault
if ($update_authorized === TRUE) {
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/battery-status?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $md5 = md5($response);
  $responseData = json_decode($response, TRUE);
  $s = date_create_from_format(DATE_ISO8601, $responseData['data']['attributes']['timestamp'], timezone_open('UTC'));
  $utc_timestamp = date_timestamp_get($s);
  if (empty($s)) {
  	$update_sucess = FALSE;
	echo "Cannot update battery:<br>" . json_encode(json_decode($response, TRUE),JSON_PRETTY_PRINT);
}
  else {
    $update_sucess = TRUE;
  }
} else {
	$update_sucess = FALSE;
	echo "Have to wait 1 minute between requests.<br>";
}

echo "<br>Battery 1:<br><pre>" . str_replace($vin, "xxxxxxxxxxxx", json_encode(json_decode($response, TRUE)['data']['attributes'],JSON_PRETTY_PRINT)) . "</pre><br>";

echo "Values for <b>chargingStatus</b><br>";
echo "NOT_IN_CHARGE = 0.0<br>
WAITING_FOR_A_PLANNED_CHARGE = 0.1<br>
CHARGE_ENDED = 0.2<br>
WAITING_FOR_CURRENT_CHARGE = 0.3<br>
ENERGY_FLAP_OPENED = 0.4<br>
CHARGE_IN_PROGRESS = 1.0<br>
CHARGE_ERROR = -1.0 // 'not charging' (<= ZE40) or 'error' (ZE50).<br>
UNAVAILABLE = -1.1<br>";


//Request battery and charging status from Renault
if ($update_authorized === TRUE) {
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v2/cars/'.$vin.'/battery-status?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $md5 = md5($response);
  $responseData = json_decode($response, TRUE);
  $s = date_create_from_format(DATE_ISO8601, $responseData['data']['attributes']['timestamp'], timezone_open('UTC'));
  $utc_timestamp = date_timestamp_get($s);
  if (empty($s))
  	$update_sucess = FALSE;
  else {
    $update_sucess = TRUE;
    $weather_api_dt = date_format($s, 'U');
    $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
    $session[8] = date_format($s, 'd.m.Y');
    $session[9] = date_format($s, 'H:i');
    $session[10] = $responseData['data']['attributes']['chargingStatus'];
    $session[11] = $responseData['data']['attributes']['plugStatus'];
    $session[12] = $responseData['data']['attributes']['batteryLevel'];
    if (($zoeph == 1)) $session[13] = $responseData['data']['attributes']['batteryTemperature'];
    else $session[13] = $responseData['data']['attributes']['batteryAvailableEnergy'];
    $session[14] = $responseData['data']['attributes']['batteryAutonomy'];
    $session[15] = $responseData['data']['attributes']['chargingRemainingTime'];
    $s = $responseData['data']['attributes']['chargingInstantaneousPower'];
    if ($zoeph == 1) $session[16] = $s/1000;
    else $session[16] = $s;

echo "Battery 2:<br><pre>" . str_replace($vin, "xxxxxxxxxxxx", json_encode(json_decode($response, TRUE)['data']['attributes'],JSON_PRETTY_PRINT)) . "</pre><br>";

  }
} else {
	$update_sucess = FALSE;
	echo "Have to wait 1 minute between requests.<br>";
}

//Request more data from Renault only if changed data since last request are expected
if (isset($md5) && $md5 != $session[3] && $update_sucess === TRUE) {
  //Request mileage
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/cockpit?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  $s = $responseData['data']['attributes']['totalMileage'];
  if (empty($s)) $update_sucess = FALSE;
  else $session[7] = $s;

echo "Dashboard 1:<br><pre>" .str_replace($vin, "xxxxxxxxx",  json_encode(json_decode($response, TRUE)['data']['attributes'],JSON_PRETTY_PRINT)) . "</pre><br>";

  //Request mileage
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v2/cars/'.$vin.'/cockpit?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  $s = $responseData['data']['attributes']['totalMileage'];
  if (empty($s)) $update_sucess = FALSE;
  else $session[7] = $s;

echo "Dashboard 2:<br><pre>" .str_replace($vin, "xxxxxxxxx",  json_encode(json_decode($response, TRUE)['data']['attributes'],JSON_PRETTY_PRINT)) . "</pre><br>";



  //Request chargemode
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/charge-mode?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);
  $s = $responseData['data']['attributes']['chargeMode'];
  if (empty($s)) $session[24] = 'n/a';
  else $session[24] = $s;

echo "Charge mode:<br><pre>" .str_replace($vin, "xxxxxxxxx",  json_encode(json_decode($response, TRUE)['data']['attributes'],JSON_PRETTY_PRINT)) . "</pre><br>";



  //Request HVAC 1
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/hvac-status?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);

echo "HVAC:<br><pre>" .str_replace($vin, "xxxxxxxxx",  json_encode(json_decode($response, TRUE)['data']['attributes'],JSON_PRETTY_PRINT)) . "</pre><br>";




  //Request outside temperature (only Ph1)
  //if ($zoeph == 1) {
    $postData = array(
      'apikey: '.$kamereon_api,
      'x-gigya-id_token: '.$id_token
    );
    $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/hvac-status?country='.$country);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
    $response = curl_exec($ch);
    if ($response === FALSE) die(curl_error($ch));
    $responseData = json_decode($response, TRUE);
    $s = $responseData['data']['attributes']['externalTemperature'];
    if (empty($s) && $s != '0.0') $update_sucess = FALSE;
    else  $session[17] = $s;
  //}

echo "Temp:<br><pre>" .str_replace($vin, "xxxxxxxxx",  json_encode(json_decode($response, TRUE)['data']['attributes'],JSON_PRETTY_PRINT)) . "</pre><br>";
// V2 not available for temp

  //Request GPS position (only Ph2)
  //if ($zoeph == 2) {
    $postData = array(
      'apikey: '.$kamereon_api,
      'x-gigya-id_token: '.$id_token
    );
    $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/location?country='.$country);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
    $response = curl_exec($ch);
    if ($response === FALSE) die(curl_error($ch));
    $responseData = json_decode($response, TRUE);
    $s = date_create_from_format(DATE_ISO8601, $responseData['data']['attributes']['lastUpdateTime'], timezone_open('UTC'));
	if (empty($s)) $update_sucess = FALSE;
	else {
      $s = date_timezone_set($s, timezone_open('Europe/Berlin'));
	  $session[17] = $responseData['data']['attributes']['gpsLatitude'];
	  $session[18] = $responseData['data']['attributes']['gpsLongitude'];
      $session[19] = date_format($s, 'd.m.Y');
	  $session[20] = date_format($s, 'H:i');
	}
  //}

echo "Location 1:<br><pre>" .str_replace($vin, "xxxxxxxxx",  json_encode(json_decode($response, TRUE)['data']['attributes'],JSON_PRETTY_PRINT)) . "</pre><br>";
// V2 not available for location

function testCommand($comm, $ver, $kamereon_api, $id_token, $vin, $accountId, $country) {
/*echo "kamereon_api=" . $kamereon_api . "<br>";
echo "id_token=" . $id_token . "<br>";
echo "VIN3=" . $vin . "<br>";
echo "account3=" . $accountId . "<br>";*/

  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token
  );
  $finalUrl = 'https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/' . $comm;
  if  (strpos($finalUrl,'?') >= 0) {
  		$finalUrl = $finalUrl . '&country='.$country;
	} else {
  		$finalUrl = $finalUrl . '?country='.$country;
	}
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);


	if (json_decode($response, TRUE)['data'] !== null) {
		echo "<b>" . $comm . " v." . $ver .  "</b>:<br><pre>" .str_replace($vin, "xxxxxxxxx",  json_encode(json_decode($response, TRUE)['data']['attributes'],JSON_PRETTY_PRINT)) . "</pre><br>";
	} else {
		echo "<b>" . $comm . " v." . $ver . " did not work</b><br>";
		echo 'URL: https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v' . $ver . '/cars/'.$vin.'/' . $comm . '?country='.$country . "<br>";
		//echo "Header DOPO:<br>Apikey = " . $postData['apikey'] . "<br>token=" . $postData['x-gigya-id_token'] . "<br>";
		//echo "Header DOPO2:<br>Apikey = " . $postData[0] . "<br>token=" . $postData[1] . "<br>";
		echo  json_encode(json_decode($response, TRUE),JSON_PRETTY_PRINT);
	}
	echo "<br>-----------------<br>";
}


function testGroup($vv, $kamereon_api, $id_token, $vin, $accountId, $country) {
/*echo "VIN2=" . $vin . "<br>";
echo "account2=" . $accountId . "<br>";*/
	testCommand("cockpit",$vv, $kamereon_api, $id_token, $vin,  $accountId, $country);
	testCommand("hvac-history?type=day&start=20210901&end=20210930",$vv, $kamereon_api, $id_token, $vin, $accountId, $country);
	testCommand("charges-history?type=day&start=20210901&end=20210930",$vv, $kamereon_api, $id_token, $vin, $accountId, $country);
	testCommand("trips-history?type=day&start=20210901&end=20210930",$vv, $kamereon_api, $id_token, $vin, $accountId, $country);
	testCommand("energy-unit-cost",$vv, $kamereon_api, $id_token, $vin, $accountId, $country);
	testCommand("hvac-schedule",$vv, $kamereon_api, $id_token, $vin, $accountId, $country);
}


/*echo "VIN1=" . $vin . "<br>";*/
testGroup(1, $kamereon_api, $id_token, $vin, $accountId, "IT");
testGroup(2, $kamereon_api, $id_token, $vin, $accountId, "IT");




/*
  //Request SOMETHING for testing
  $comm = "lock-status";
  $postData = array(
    'apikey: '.$kamereon_api,
    'x-gigya-id_token: '.$id_token
  );
  $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/lock-status?country='.$country);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
  $response = curl_exec($ch);
  if ($response === FALSE) die(curl_error($ch));
  $responseData = json_decode($response, TRUE);

echo $comm . ":<br><pre>" .str_replace($vin, "xxxxxxxxx",  json_encode(json_decode($response, TRUE)['data']['attributes'],JSON_PRETTY_PRINT)) . "</pre><br>";

*/




  //Request weather data from openweathermap (only Ph2)
  if ($zoeph == 2 && $weather_api_key != '') {
	$ch = curl_init('https://api.openweathermap.org/data/2.5/onecall/timemachine?lat='.$session[17].'&lon='.$session[18].'&dt='.$weather_api_dt.'&units=metric&lang='.$weather_api_lng.'&appid='.$weather_api_key);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$response = curl_exec($ch);
	if ($response === FALSE) die(curl_error($ch));
	$responseData = json_decode($response, TRUE);	
	$session[22] = $responseData['current']['temp'];
	$session[23] = $responseData['current']['weather']['0']['description'];
  }

  //Send mail, execute command or activate schedule mode if configured
  if ($mail_bl === 'Y' || $cmon_bl === 'Y' || !empty($exec_bl)) {
    if ($session[12] >= $session[21] && $session[10] == 1 && $session[5] != 'Y') {
      if ($session[15] != '') $s = $session[15];
	  else $s = $lng[31];
      $sendmessage = $lng[32]."\n".$lng[33].': '.$session[12].' %'."\n".$lng[34].': '.$s.' '.$lng[35]."\n".$lng[36].': '.$session[14].' km'."\n".$lng[37].': '.$session[8].' '.$session[9];
	  if ($mail_bl === 'Y') mail($username, $zoename, $sendmessage);
	  if ($cmon_bl === 'Y') {
	    $postData = array(
	      'Content-type: application/vnd.api+json',
	      'apikey: '.$kamereon_api,
	      'x-gigya-id_token: '.$id_token
	    );
	    $jsonData = '{"data":{"type":"ChargeMode","attributes":{"action":"schedule_mode"}}}';
	    $ch = curl_init('https://api-wired-prod-1-euw1.wrd-aws.com/commerce/v1/accounts/'.$accountId.'/kamereon/kca/car-adapter/v1/cars/'.$vin.'/actions/charge-mode?country='.$country);
	    curl_setopt($ch, CURLOPT_POST, TRUE);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, $postData);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
	    $response = curl_exec($ch);
	    if ($response === FALSE) die(curl_error($ch));
	  }
      if (!empty($exec_bl)) shell_exec($exec_bl.' "'.$sendmessage.'"');
	  $session[5] = 'Y';
    } else if ($session[5] == 'Y' && $session[10] != 1) $session[5] = 'N';
  }
  if ($mail_csf === 'Y' || !empty($exec_csf)) {
    $sendmessage = $lng[38]."\n".$lng[33].': '.$session[12].' %'."\n".$lng[36].': '.$session[14].' km'."\n".$lng[37].': '.$session[8].' '.$session[9];
    if ($session[6] == 'Y' && $session[10] != 1) {
	  if ($mail_csf === 'Y') mail($username, $zoename, $sendmessage);
      if (!empty($exec_csf)) shell_exec($exec_bl.' "'.$sendmessage.'"');
	}
	if ($session[10] == 1) $session[6] = 'Y';
    else $session[6] = 'N';
  }

  //Save data in database if configured
  // SECURITY ISSUE! SAVED DATA ARE NOT ENCRYPTED AND ARE PUBLICLY ACCESSIBLE!!
 /* if ($update_sucess === TRUE && $save_in_db === 'Y') {
    if (!file_exists('database.csv')) {
	  if ($zoeph == 1) file_put_contents('database.csv', 'Date;Time;Mileage;Outside temperature;Battery temperature;Battery level;Range;Cable status;Charging status;Charging speed;Remaining charging time;Charging schedule'."\n");
      else file_put_contents('database.csv', 'Date;Time;Mileage;Battery level;Battery capacity;Range;Cable status;Charging status;Charging speed;Remaining charging time;GPS Latitude;GPS Longitude;GPS date;GPS time;Outside temperature;Weather condition;Charging schedule'."\n");
    }
    if ($zoeph == 1) file_put_contents('database.csv', $session[8].';'.$session[9].';'.$session[7].';'.$session[17].';'.$session[13].';'.$session[12].';'.$session[14].';'.$session[11].';'.$session[10].';'.$session[16].';'.$session[15].';'.$session[24]."\n", FILE_APPEND);
	else file_put_contents('database.csv', $session[8].';'.$session[9].';'.$session[7].';'.$session[12].';'.$session[13].';'.$session[14].';'.$session[11].';'.$session[10].';'.$session[16].';'.$session[15].';'.$session[17].';'.$session[18].';'.$session[19].';'.$session[20].';'.$session[22].';'.$session[23].';'.$session[24]."\n", FILE_APPEND);
  }*/

  //Send data to ABRP if configured
  if (!empty($abrp_token) && !empty($abrp_model)) {
    if ($session[10] == 1) $abrp_is_charging = 1;
    else $abrp_is_charging = 0;
    $jsonData = urlencode('{"car_model":"'.$abrp_model.'","utc":'.$utc_timestamp.',"soc":'.$session[12].',"odometer":'.$session[7].',"is_charging":'.$abrp_is_charging.'}');
    $ch = curl_init('https://api.iternio.com/1/tlm/send?api_key=fd99255b-91a0-45cd-9df5-d6baa8e50ef8&token='.$abrp_token.'&tlm='.$jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $response = curl_exec($ch);
    if ($response === FALSE) die(curl_error($ch));
  } else {
		echo "<br>No ABRP data available.<br>";
	}
}
if (isset($ch)) curl_close($ch);


//Output
if ($cmd_cron === TRUE) {
  if ($cmd_acnow === TRUE) echo 'AC NOW'."\n";
  if ($cmd_chargenow === TRUE) echo 'CHARGE NOW'."\n";
  if ($cmd_cmon === TRUE) echo 'CM ON'."\n";
  else if ($cmd_cmoff === TRUE) echo 'CM OFF'."\n";
  if ($update_sucess === TRUE) echo 'OK';
  else echo 'NO DATA';
} else {
  $requesturi = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
  echo '<HTML>'."\n".'<HEAD>'."\n".'<LINK REL="manifest" HREF="zoephp.webmanifest">'."\n".'<LINK REL="stylesheet" HREF="stylesheet.css">'."\n".'<META NAME="viewport" CONTENT="width=device-width, initial-scale=1.0">'."\n".'<TITLE>'.$zoename.'</TITLE>'."\n".'</HEAD>'."\n".'<BODY>'."\n".'<DIV ID="container">'."\n".'<MAIN>'."\n";
  if ($mail_bl === 'Y') echo '<FORM ACTION="'.$requesturi.'" METHOD="post" AUTOCOMPLETE="off">'."\n";
  echo '<ARTICLE>'."\n".'<TABLE>'."\n".'<TR ALIGN="left"><TH>'.$zoename.'</TH><TD><SMALL><A HREF="'.$requesturi.'">'.$lng[1].'</A></SMALL></TD></TR>'."\n";
  if ($cmd_acnow === TRUE) echo '<TR><TD COLSPAN="2">'.$lng[2].'</TD><TD>'."\n";
  if ($cmd_chargenow === TRUE) echo '<TR><TD COLSPAN="2">'.$lng[3].'</TD><TD>'."\n";
  if ($cmd_cmon === TRUE) echo '<TR><TD COLSPAN="2">'.$lng[4].'</TD><TD>'."\n";
  else if ($cmd_cmoff === TRUE) echo '<TR><TD COLSPAN="2">'.$lng[5].'</TD><TD>'."\n";
  if ($update_sucess === FALSE && $update_authorized === TRUE) echo '<TR><TD COLSPAN="2">'.$lng[6].'</TD><TD>'."\n";
    echo '<TR><TD>'.$lng[7].':</TD><TD>'.$session[7].' km</TD></TR>'."\n".'<TR><TD>'.$lng[8].':</TD><TD>';
    if ($session[11] == 0){
      echo  $lng[9];
    } else {
      echo $lng[10];
    }
    echo '</TD></TR>'."\n".'<TR><TD>'.$lng[11].':</TD><TD>';
    if ($session[10] == 1){
	  if ($session[15] != ''){
        $s = date_create_from_format('d.m.YH:i', $session[8].$session[9]);
        date_add($s, date_interval_create_from_date_string($session[15].' minutes'));
        $s = date_format($s, 'H:i');
      } else $s = $lng[12];
      echo $lng[10].'</TD></TR>'."\n".'<TR><TD>'.$lng[13].':</TD><TD>'.$s;
	  if ($zoeph == 1) echo '</TD></TR>'."\n".'<TR><TD>'.$lng[14].':</TD><TD>'.$session[16].' kW';
    } else {
      echo $lng[9] ;
    }
	if ($hide_cm !== 'Y') {
	  echo '</TD></TR>'."\n".'<TR><TD>'.$lng[15].':</TD><TD>';
	  if (substr($session[24], 0, 6) === 'always' || $session[24] === 'n/a') echo  $lng[16];
	  else echo $lng[17] ;
    }
    echo '</TD></TR>'."\n".'<TR><TD>'.$lng[18].':</TD><TD>'.$session[12].' %</TD></TR>'."\n";
	if ($mail_bl === 'Y' || $cmon_bl === 'Y' || !empty($exec_bl)) echo '<TR><TD>'.$lng[19].':</TD><TD><INPUT TYPE="number" NAME="bl" VALUE="'.$session[21].'" MIN="1" MAX="99"><INPUT TYPE="submit" VALUE="%"></TD></TR>'."\n";
    if ($zoeph == 2) {
      echo '<TR><TD>'.$lng[20].':</TD><TD>'.$session[13].' kWh</TD></TR>'."\n";
    }
    echo '<TR><TD>'.$lng[21].':</TD><TD>'.$session[14].' km</TD></TR>'."\n";
    if ($zoeph == 1) {
      echo '<TR><TD>'.$lng[22].':</TD><TD>'.$session[13].' &deg;C</TD></TR>'."\n".'<TR><TD>'.$lng[23].':</TD><TD>'.$session[17].' &deg;C</TD></TR>'."\n";
    } else {
	  if ($weather_api_key != '') echo '<TR><TD>'.$lng[23].':</TD><TD>'.$session[22].' &deg;C ('.htmlentities($session[23]).')</TD></TR>'."\n";
	}
    echo '<TR><TD>'.$lng[24].':</TD><TD>'.$session[8].' '.$session[9].'</TD></TR>'."\n";
    if ($zoeph == 2) {
      echo '<TR><TD>'.$lng[25].':</TD><TD><A HREF="https://www.google.com/maps/place/'.$session[17].','.$session[18].'" TARGET="_blank">Google Maps</A></TD></TR>'."\n".'<TR><TD>'.$lng[26].':</TD><TD>'.$session[19].' '.$session[20].'</TD></TR>'."\n";
    }
  echo '<TR><TD COLSPAN="2"><A HREF="'.$requesturi.'?acnow' . $authstring . '">'.$lng[27].'</A></TD></TR>'."\n";
  if ($hide_cm !== 'Y') echo '<TR><TD COLSPAN="2">'.$lng[15].': <A HREF="'.$requesturi.'?cmon' . $authstring . '">'.$lng[28].'</A> | <A HREF="'.$requesturi.'?cmoff' . $authstring . '">'.$lng[29].'</A></TD></TR>'."\n".'<TR><TD COLSPAN="2"><A HREF="'.$requesturi.'?chargenow' . $authstring . '">'.$lng[30].'</A></TD></TR>'."\n";
  if ($zoeph == 1) echo '<TR><TD COLSPAN="2"><A HREF="index-mio.php?pass=miapasssegretissima&username=' . $username . '&password=' . $password . '&vin=' . $vin    .'">'.$lng[39].'</A></TD></TR>'."\n";
  echo '</TABLE>'."\n".'</ARTICLE>'."\n";
  if ($mail_bl === 'Y') echo '</FORM>'."\n";
  echo '</MAIN>'."\n".'</DIV>'."\n".'</BODY>'."\n".'</HTML>';
}

//Cache data
if ($update_authorized === TRUE || $cmd_cron == TRUE || (isset($_POST['bl']) && is_numeric($_POST['bl']))) {
  echo  $session[3] = $md5;
  echo  $session[4] = $timestamp_now;
  echo  $session = implode('|', $session);
// UNSECURE, DON'T SAVE!
  // echo  file_put_contents('session', $session);
}
?>
