<?php
include 'phase1.php';



// get temporary credentials from the url
$request_token = $_GET['oauth_token'];

// get the temporary credentials secret - this assumes you set the request secret  
// in a cookie, but you may also set it in a database or elsewhere
$request_token_secret = $_COOKIE['request_secret'];

// get the verifier from the url
$verifier = $_GET['oauth_verifier'];


$oauth = new OAuth('nufapty26fxrqyk8vul9kel6', 'g2m4e3jw0b');
$oauth->disableSSLChecks();

// set the temporary credentials and secret
$oauth->setToken($request_token, $request_token_secret);

try {
    // set the verifier and request Etsy's token credentials url
    $acc_token = $oauth->getAccessToken("https://openapi.etsy.com/v2/oauth/access_token", null, $verifier);
	
	// third part
	// Making an Authorized Request to the API
	$o_token = $acc_token['oauth_token'];
	$token_sec = $acc_token['oauth_token_secret'];
	
	makeRequest($o_token, $token_sec);
	
	checkPermission($o_token, $token_sec);
	
	
	
	//get all the transactions for each shop
	shopTransactions($o_token, $token_sec);
	
} catch (OAuthException $e) {
    error_log($e->getMessage());
    error_log(print_r($oauth->getLastResponse(), true));
    error_log(print_r($oauth->getLastResponseInfo(), true));
    exit;
}


function makeRequest($o_token, $token_sec)
{
	$oauth = new OAuth('nufapty26fxrqyk8vul9kel6', 'g2m4e3jw0b', OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
	$oauth->disableSSLChecks();

	$oauth->setToken($o_token, $token_sec);

try {
    $data = $oauth->fetch("https://openapi.etsy.com/v2/users/__SELF__", null, OAUTH_HTTP_METHOD_GET);
    $json = $oauth->getLastResponse();
	$r = (json_decode($json, true));
	echo '<div class="alert alert-success">
				<strong>Request Results</strong><br>
				USER_ID : .' . $r['results'][0]['user_id'] .'<br>'.
				'Login Name: .' . $r['results'][0]['login_name'] .	
		   '</div>';  
} catch (OAuthException $e) {
    error_log($e->getMessage());
    error_log(print_r($oauth->getLastResponse(), true));
    error_log(print_r($oauth->getLastResponseInfo(), true));
    exit;
}
}

function checkPermission($o_token, $token_sec)
{
	$oauth = new OAuth('nufapty26fxrqyk8vul9kel6', 'g2m4e3jw0b', OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
	$oauth->disableSSLChecks();

	$oauth->setToken($o_token, $token_sec);

try {
    $data = $oauth->fetch("https://openapi.etsy.com/v2/oauth/scopes", null, OAUTH_HTTP_METHOD_GET);
	
    $json = $oauth->getLastResponse();

    $p = (json_decode($json, true));
	echo '<div class="alert alert-success">
				<strong>Permissions</strong><br>'.
				 $p['results'][0] .'<br>'.
				 $p['results'][1] .	
		   '</div>';

} catch (OAuthException $e) {
    error_log($e->getMessage());
    error_log(print_r($oauth->getLastResponse(), true));
    error_log(print_r($oauth->getLastResponseInfo(), true));
    exit;
}
}


function shopTransactions($o_token, $token_sec)
{
//Database configurations
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "wgsnetsy";
	
	// Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
	
	$sql = "SELECT shop_id from shop";
	$result = $conn->query($sql) or die($conn->error);
	$shops = array();
	while ($s = $result->fetch_assoc()) {
			$shops[] = $s;
		}

		$oauth = new OAuth('nufapty26fxrqyk8vul9kel6', 'g2m4e3jw0b', OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI);
		$oauth->disableSSLChecks();

		$oauth->setToken($o_token, $token_sec);

// MAIN LOGIC STARTS HERE
try {
foreach($shops as $shop)
{
	//find the transactions for each shop
	$s_id = $shop['shop_id'];
	$url = "https://openapi.etsy.com/v2/shops/".$s_id."/listings/expired";

    $data = $oauth->fetch($url, null, OAUTH_HTTP_METHOD_GET);

    $json = $oauth->getLastResponse();
	$transactions = (json_decode($json, true));
	

	//now store the transaction information in the database
	foreach($transactions['results'] as $transaction)
	{
		$sql = "INSERT into transaction (shop_id, transaction_id, price) VALUES ('{$shop['shop_id']}', '{$transaction['transaction_id']}', '{$transaction['price']}')";
        $result = $conn->query($sql);
		
		
		//now store individual item for that transaction in the db
		foreach($transaction['materials'] as $item)
		{
			$sql = "INSERT into transaction_item (transaction_id, material) VALUES ('{$transaction['transaction_id']}', '{$item}')";
			$result = $conn->query($sql);
		}
	}
}

// Now since we have got all the data in our database, lets find out stats
	echo '<div class="alert alert-success">
				<strong>Top Items for Each Shop are:</strong></div>'; 
				
				$sql = "SELECT shop.shop_name, transaction.price, transaction_item.material 
						FROM shop, transaction, transaction_item
						WHERE shop.shop_id = transaction.shop_id AND transaction.transaction_id = transaction_item.transaction_id
						ORDER BY transaction.price DESC LIMIT 5";
						
						$result = $conn->query($sql);
						$stats = array();
						while ($s = $result->fetch_assoc()) {
								$stats[] = $s;
							}
				print"<pre>";
				print_r($stats);
    

} catch (OAuthException $e) {
	echo 'inside catch<br>';
    // You may want to recover gracefully here...
    print $oauth->getLastResponse()."\n";
    print_r($oauth->debugInfo);
    die($e->getMessage());
}
}
?>