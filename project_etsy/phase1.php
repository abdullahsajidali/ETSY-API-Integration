<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</head>
<body>
<?php

// Giving user the initial option
echo '<div class="container">
		<div class="jumbotron">
			<h1>ETSY Analytics</h1>      
				<p>To get a list of all the Shops provided by ETSY, Please click the Get ETSY Shops button below. <br> 
				   And then Click on the OAuth Authentication button to get your app authorized by ETSY </p></br></br>
				
				<form method="post">
					<input type="submit" class="btn btn-success" name="getShops" value="Get ETSY Shops"> </br></br>
					<input type="submit" class="btn btn-warning" name="getOAuth" value="OAuth Authentication">
				</form>
		</div>      
</div>';

// upon user request to get all shops
if(isset($_POST['getShops']))
{
     $etsy_shops = getAllShops();
	 
	 //now store these shops in database
	 storeShops($etsy_shops);
	 
	 echo '<div class="alert alert-success">
				<strong>Success!</strong> Shops stored in the database. Below is the list
		   </div>';
	 
	 // display these shops to user 
	 print"<pre>";
	 print_r($etsy_shops);
}

// upon user request to authenticate app
if(isset($_POST['getOAuth']))
{
// oauth authentication with key and secret
$oauth = new OAuth('nufapty26fxrqyk8vul9kel6', 'g2m4e3jw0b');
$oauth->disableSSLChecks();

// make an API request for your temporary credentials & redirect to my 2nd page of the application
$req_token = $oauth->getRequestToken("https://openapi.etsy.com/v2/oauth/request_token?scope=transactions_r%20listings_r", 'http://localhost/wgsn_etsy/phase2.php');

setcookie("request_secret", $req_token['oauth_token_secret']);
print "<a class=btn btn-large btn-info href='{$req_token['login_url']}'>Login here to Verify App</a>";
}



// helper functions
function getAllShops()
{
	//set up URL
	$url = "https://openapi.etsy.com/v2/shops?api_key=nufapty26fxrqyk8vul9kel6";
	//set up Curl Request & options
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	$response_body = curl_exec($ch);
	
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if (intval($status) != 200) throw new Exception("HTTP $status\n$response_body");

	$response = json_decode($response_body);
	// now selecting 10 random shops
	$i=0; $randomShops = array();
	foreach($response ->results as $shop)
	{
		$randomShops[] =  array("shop_id" => $shop->shop_id, "shop_name" => $shop->shop_name, "user_id" => $shop->user_id);
		$i++; if($i==10){break;}
	}
	return $randomShops;
}

function storeShops($etsy_shops)
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

	//actual query
	foreach ($etsy_shops as $shops) {
        $sql = "INSERT into shop (shop_id, shop_name, user_id) VALUES ('{$shops['shop_id']}', '{$shops['shop_name']}', '{$shops['user_id']}')";
        $result = $conn->query($sql);
    }
}
