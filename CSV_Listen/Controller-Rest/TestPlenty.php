<?php
header('Access-Control-Allow-Origin: *');
include "bar/BarcodeGenerator.php";
include "bar/BarcodeGeneratorPNG.php";
include "../Model/LoginValidate.php";
include "../Model/Products.php";
include "../Model/Logs.php";
include "../Model/Order.php";
include "../Helper/PlentyApi.php";
//This Endpoint will be called by Webshop

if(isset($_GET["action"])){
    header('Content-Type: text/html; charset=utf-8');
    $token = PlentyApi::getToken();
    $amount = 35;
    $resp4 = PlentyApi::createNewPayment($token,$amount);
    echo "<h1>RESPONSE</h1>".$resp4;
}

