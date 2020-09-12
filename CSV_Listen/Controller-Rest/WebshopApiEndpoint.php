<?php
session_start();
header('Access-Control-Allow-Origin: *');
include "bar/BarcodeGenerator.php";
include "bar/BarcodeGeneratorPNG.php";
include "../Model/LoginValidate.php";
include "../Model/Products.php";
include "../Model/Logs.php";
include "../Model/Order.php";
include "../Helper/PlentyApi.php";
include "../Helper/Webshop.php";
//This Endpoint will be called by Webshop

$productsModel = new Products();
$webshop = new Webshop();

//Http POST request
if(isset($_POST["action"])){
    $action = $_POST["action"];
    $json = $_POST["json"];
    $jsonUrlDecoded = urldecode($json);
    //NOT NEED $jsonUrlAndUtf8Decoded = utf8_decode($jsonUrlDecoded); //Das ist nötig, wenn Request von Wildfly(Java) kommt (ISO-8859-1 characters were encoded with UTF-8)
    Logs::insertNewLog("NEW ORDER: got action: ".$action.", jsonRaw: ".$json." --> jsonUrlDecoded: ".$jsonUrlDecoded,Logs::$type_system);

    if ($action === "newOrder"){
        //$mail = "s.hajir27@gmail.com";
        //$jsonOrder = json_decode('{"id":12,"token":"aMz1WUDCVpW0iguZydqy","status":"In Bearbeitung","trackingCode":"","parcel":"","tax":0.19,"paymentMethod":"Vorkasse","paypalMail":"","customerUsername":"s.hajir27@gmail.com","customerFirstname":"ÜÄÄÄ?ßß","customerLastname":"Hajir","shipping_firstname":"Test1f","shipping_lastname":"Test1l","shipping_street_house":"Dahlgrünring, 4","shipping_postalcode":"21109","shipping_city":"Hamburg","shipping_country":"Deutschland","shipping_phone":"017679831856","shippingCostFull":0.0,"cartPrice":35.0,"cartPriceFull":35.0,"mapSkuAndslimProduct":{"GDUni60x40":{"skuu":"GDUni60x40","name1":"UNI","name2":"60 x 40, Gabbeh Gold","price":35,"qtyInCart":1}},"orderedProducts":[]}',true);

        $jsonOrder = json_decode($jsonUrlDecoded,true);
        $jsonOrder["id"];

        $paymethod = $jsonOrder["paymentMethod"];
        $email = $jsonOrder["customerUsername"];//email
        $firstName = $jsonOrder["shipping_firstname"];
        $lastname = $jsonOrder["shipping_lastname"];
        $street = $jsonOrder["shipping_street"];
        $housenumber = $jsonOrder["shipping_house"];

        $plz = $jsonOrder["shipping_postalcode"];
        $town = $jsonOrder["shipping_city"];
        $country = $jsonOrder["shipping_country"];
        $phone = $jsonOrder["shipping_phone"];
        $shippingCost = $jsonOrder["shippingCostFull"];
        $tax = intval($jsonOrder["tax"]);
        //$amount = $jsonOrder["cartPriceFull"];
        $productsAssocArray = $jsonOrder["mapSkuAndslimProduct"];
        Logs::insertNewLog("$ jsonOrder: ".json_encode($jsonOrder),Logs::$type_system);
        echo "thanks, got your action '".$action."' and data(urldecoded): ".$jsonUrlDecoded;

        $token = PlentyApi::getToken();
        //1.
        $resp1 = PlentyApi::createNewContact($token,$firstName,$lastname,$phone,$email);
        $contact = json_decode($resp1,true);
        //2.
        $resp2 = PlentyApi::createNewContactAddress($token,$contact["id"],$firstName,$lastname,$street,$housenumber,$plz,$town,$country,$phone,$email,"");
        $contactAddress = json_decode($resp2,true);
        //3.
        $resp3 = PlentyApi::createNewOrder($token,$contact["id"],$contactAddress["id"],$paymethod,$productsAssocArray,$shippingCost,$tax);
        $order = json_decode($resp3,true);

        //Optional
        //If Paymethod was Paypal -> Make Payment
        if (strpos($paymethod, 'pal') !== false ) {
            $amount = $order["amounts"][0]["invoiceTotal"];
            $resp4 = PlentyApi::createNewPayment($token,$amount);
            $payment = json_decode($resp4,true);
            PlentyApi::createPaymentOrderRelation($token,$payment["id"],$order["id"]);
        }
        decrementProductsQty($productsAssocArray);
    }
    else if ($action === "wunschmassNewOrder"){
        $jsonOrder = json_decode($jsonUrlDecoded,true);
        $jsonOrder["id"];

        $paymethod = $jsonOrder["paymentMethod"];
        $vorrausZahlung = $jsonOrder["vorrausZahlung"];
        $email = $jsonOrder["customerUsername"];//email
        $firstName = $jsonOrder["shipping_firstname"];
        $lastname = $jsonOrder["shipping_lastname"];
        $street = $jsonOrder["shipping_street"];
        $housenumber = $jsonOrder["shipping_house"];

        $plz = $jsonOrder["shipping_postalcode"];
        $town = $jsonOrder["shipping_city"];
        $country = $jsonOrder["shipping_country"];
        $phone = $jsonOrder["shipping_phone"];
        $shippingCost = $jsonOrder["shippingCostFull"];
        $lieferzeit = $jsonOrder["lieferzeit"];
        //$amount = $jsonOrder["cartPriceFull"];
        $productsAssocArray = $jsonOrder["mapSkuAndslimProduct"];
        Logs::insertNewLog("$ jsonOrder WUNSCHMASS: ".json_encode($jsonOrder),Logs::$type_system);

        $token = PlentyApi::getToken();
        //1.
        $resp1 = PlentyApi::createNewContact($token,$firstName,$lastname,$phone,$email);
        $contact = json_decode($resp1,true);
        //2.
        $resp2 = PlentyApi::createNewContactAddress($token,$contact["id"],$firstName,$lastname,$street,$housenumber,$plz,$town,$country,$phone,$email,$lieferzeit);
        $contactAddress = json_decode($resp2,true);
        //3.
        $resp3 = PlentyApi::createNewWunschmassOrder($token,$contact["id"],$contactAddress["id"],$paymethod,$productsAssocArray,$shippingCost);
        $order = json_decode($resp3,true);

        echo "thanks, got your action '".$action."' paymentMethod: ".$paymethod." vorrausZahlung: ".$vorrausZahlung." data: ".$jsonUrlDecoded;

        //Optional
        //If Paymethod was Paypal -> Make vorrausZahlung-Payment
        if (strpos($paymethod, 'pal') !== false ) {
            //Vorrausbezahlten Betrag gutschreiben
            $resp4 = PlentyApi::createNewPayment($token,floatval($vorrausZahlung));
            $payment = json_decode($resp4,true);
            PlentyApi::createPaymentOrderRelation($token,$payment["id"],$order["id"]);
        }
    }
}






//Http GET request
if(isset($_GET["action"])){
	
	//Put 'plentyExportFull.csv' data into plentytable -> set wawi & webshop qty (netStock)
	if($_GET["action"] === "importPlentyStockUpdateSelf"){
        header('Content-Type: text/html; charset=utf-8');
        $_SESSION["importPlentyStockUpdateSelf"] = "Done";
        //Import plenty data
        $oldFile = fopen('https://morgenland-teppiche.com/wawi2/plenty/plentyExportFull.csv','r');
        $counter = 0;
        $headerOK = false;
        $indxSku = 50;$indxId=50;$indxVariantId =50;
        $indxIsMain=50;$indxMainVariantId=50;$indxPhysStock=50;$indxResvStock=50;
        $indxBarcode=50;$indxAttrValue=50;$indxWeightG=50;
		$updateWebshop=false;
		$skuQtyString = "";
        Plenty::deleteAllItems();
        echo "<h2>Import 'plenytExportFull.csv' -> set Wawi+Webshop Net Stock</h2><h3>1.Truncated 'plentyproducts' table</h3><h3>2.Now inputting new data into table</h3>";
        while (($line = fgetcsv($oldFile, 0, ';')) !== FALSE) {
            if(!empty($line)){
                if($counter == 0) {
                    //header check
                    $lineSize = count($line);
                    for ($i=0; $i<$lineSize; $i++){
                        $headername = $line[$i];
                        if(strpos($headername, 'Variation.number') !== false ) $indxSku = $i;
                        else if(strpos($headername, 'Item.id') !== false ) $indxId = $i;
                        else if(strpos($headername, 'Variation.id') !== false ) $indxVariantId = $i;
                        else if(strpos($headername, 'Variation.isMain') !== false ) $indxIsMain = $i;
                        else if(strpos($headername, 'Variation.mainVariationId') !== false ) $indxMainVariantId = $i;
                        else if(strpos($headername, 'VariationStock.physicalStock') !== false ) $indxPhysStock = $i;
						else if(strpos($headername, 'VariationStock.reservedStock') !== false ) $indxResvStock = $i;
                        else if(strpos($headername, 'VariationBarcode.code') !== false ) $indxBarcode = $i;
                        else if(strpos($headername, 'VariationAttributeValues.attributeValues') !== false ) $indxAttrValue = $i;
                        else if(strpos($headername, 'Variation.weightG') !== false ) $indxWeightG = $i;
                    }

                    echo "Import from plenytExportFull.csv (HEADER) <br> <b style='color: red;'>Kopfzeile</b>".json_encode($line)."<br>";

                }
                else {
                    $sku = $line[$indxSku];
                    $id = $line[$indxId];
                    $variantId = $line[$indxVariantId];
                    $isMain =$line[$indxIsMain];
                    $mainVariationId = $line[$indxMainVariantId];
                    $physicalStock = $line[$indxPhysStock];
					$resvStock = $line[$indxResvStock];
					if($isMain === "" || empty($isMain)) $isMain = 0;
                    if($mainVariationId === "" || empty($mainVariationId)) $mainVariationId = 0;
					if($physicalStock === "" || empty($physicalStock)) $physicalStock = 0;
					if($resvStock === "" || empty($resvStock)) $resvStock = 0;
					
                    $netStock = intval($physicalStock)-intval($resvStock); //Net Stock
                    
					$barcode = $line[$indxBarcode];
                    $attrValues=$line[$indxAttrValue];
                    $weightG = $line[$indxWeightG];
                    echo "<br>[".$counter."] <b>".$sku."</b> ".$id."  variantId: ".$variantId.", physicalStock - resvStock: ".$physicalStock." - ".$resvStock." = <b>".$netStock."</b>, attrValues: ".$attrValues." weight: ".$weightG."<br>";
                    $res = Plenty::insertItem($sku,$id,$variantId,$isMain,$mainVariationId,$physicalStock,$barcode,$attrValues,$weightG);

                    if(strpos($res,'Error') !== FALSE) echo "<em style='color:red;'>".$res."</em>";
                    else {
						echo "<em style='color:green;'>".$res."</em>";
						//Update wawi
						$r1 = $productsModel->updateStock($sku,$netStock);
						$col = "green";
						if(strpos($r1,'Error') !== FALSE) $col = "red";
						echo "<br> --><b>Wawi response</b>: <em style='color:".$col."'>".$r1."</em><br>";
						$updateWebshop = true;
						$skuQtyString .=$sku.",".$netStock."___";
					}
                }
                $counter ++;
            }
        }
		if($updateWebshop==true) {
			//Update webshop
			//input: "Sku,Qty___Sku,Qty___Sku,Qty" OR "Sku,Qty"
			//output: "Success: 'RDNova120x80' updated to 5; "+"Success: 'RTBlue120x80' updated to 2; " OR "Error: 'SKU' not found in shop(DE); "
			$skuQtyString = substr($skuQtyString, 0, -3); //remove last "___"
			$r2 = $webshop->setItemQty($skuQtyString);
			$arr = explode(";",$r2);
			$html = "<h1>Webshop response: </h1>";
			foreach ($arr as $e) {
				$col = "green";
				if(strpos($e,'Error') !== FALSE) $col = "red";
				$html .= " -><em style='color:".$col."'>".$e."</em><br>";
			}
			echo $html;
		}
        echo "<h1>Fetched Plenty Stock Data for <b>".$counter."</b> Items</h1>";
        fclose($oldFile);
    }
	//Called via cron curl
	if($_GET["action"] === "importPlentyStockUpdateSelf-Remote"){
        header('Content-Type: text/html; charset=utf-8');
        //Import plenty data
        $oldFile = fopen('https://morgenland-teppiche.com/wawi2/plenty/plentyExportFull.csv','r');
        $counter = 0;
        $headerOK = false;
        $indxSku = 50;$indxId=50;$indxVariantId =50;
        $indxIsMain=50;$indxMainVariantId=50;$indxPhysStock=50;$indxResvStock=50;
        $indxBarcode=50;$indxAttrValue=50;$indxWeightG=50;
		$updateWebshop=false;
		$skuQtyString = "";
		$log = "<html><head><title>Last Automatic Log - importPlentyStockUpdateSelf-Remote</title><meta charset='UTF-8'></head><body>";
        Plenty::deleteAllItems();
        $log .= "<h2>".date("d.m.Y-h:ia")." - Import 'plenytExportFull.csv' -> set Wawi+Webshop Net Stock</h2><h3>1.Truncated 'plentyproducts' table</h3><h3>2.Now inputting new data into table</h3>";
        while (($line = fgetcsv($oldFile, 0, ';')) !== FALSE) {
            if(!empty($line)){
                if($counter == 0) {
                    //header check
                    $lineSize = count($line);
                    for ($i=0; $i<$lineSize; $i++){
                        $headername = $line[$i];
                        if(strpos($headername, 'Variation.number') !== false ) $indxSku = $i;
                        else if(strpos($headername, 'Item.id') !== false ) $indxId = $i;
                        else if(strpos($headername, 'Variation.id') !== false ) $indxVariantId = $i;
                        else if(strpos($headername, 'Variation.isMain') !== false ) $indxIsMain = $i;
                        else if(strpos($headername, 'Variation.mainVariationId') !== false ) $indxMainVariantId = $i;
                        else if(strpos($headername, 'VariationStock.physicalStock') !== false ) $indxPhysStock = $i;
						else if(strpos($headername, 'VariationStock.reservedStock') !== false ) $indxResvStock = $i;
                        else if(strpos($headername, 'VariationBarcode.code') !== false ) $indxBarcode = $i;
                        else if(strpos($headername, 'VariationAttributeValues.attributeValues') !== false ) $indxAttrValue = $i;
                        else if(strpos($headername, 'Variation.weightG') !== false ) $indxWeightG = $i;
                    }

                    $log .= "Import from plenytExportFull.csv (HEADER) <br> <b style='color: red;'>Kopfzeile</b>".json_encode($line)."<br>";

                }
                else {
                    $sku = $line[$indxSku];
                    $id = $line[$indxId];
                    $variantId = $line[$indxVariantId];
                    $isMain =$line[$indxIsMain];
                    $mainVariationId = $line[$indxMainVariantId];
                    $physicalStock = $line[$indxPhysStock];
					$resvStock = $line[$indxResvStock];
					if($isMain === "" || empty($isMain)) $isMain = 0;
                    if($mainVariationId === "" || empty($mainVariationId)) $mainVariationId = 0;
					if($physicalStock === "" || empty($physicalStock)) $physicalStock = 0;
					if($resvStock === "" || empty($resvStock)) $resvStock = 0;
					
                    $netStock = intval($physicalStock)-intval($resvStock); //Net Stock
                    
					$barcode = $line[$indxBarcode];
                    $attrValues=$line[$indxAttrValue];
                    $weightG = $line[$indxWeightG];
                    $log .= "<br>[".$counter."] <b>".$sku."</b> ".$id."  variantId: ".$variantId.", physicalStock - resvStock: ".$physicalStock." - ".$resvStock." = <b>".$netStock."</b>, attrValues: ".$attrValues." weight: ".$weightG."<br>";
                    $res = Plenty::insertItem($sku,$id,$variantId,$isMain,$mainVariationId,$physicalStock,$barcode,$attrValues,$weightG);

                    if(strpos($res,'Error') !== FALSE) $log .= "<em style='color:red;'>".$res."</em>";
                    else {
						$log .= "<em style='color:green;'>".$res."</em>";
						//Update wawi
						$r1 = $productsModel->updateStock($sku,$netStock);
						$col = "green";
						if(strpos($r1,'Error') !== FALSE) $col = "red";
						$log .= "<br> --><b>Wawi response</b>: <em style='color:".$col."'>".$r1."</em><br>";
						$updateWebshop = true;
						$skuQtyString .=$sku.",".$netStock."___";
					}
                }
                $counter ++;
            }
        }
		if($updateWebshop==true) {
			//Update webshop
			//input: "Sku,Qty___Sku,Qty___Sku,Qty" OR "Sku,Qty"
			//output: "Success: 'RDNova120x80' updated to 5; "+"Success: 'RTBlue120x80' updated to 2; " OR "Error: 'SKU' not found in shop(DE); "
			$skuQtyString = substr($skuQtyString, 0, -3); //remove last "___"
			$r2 = $webshop->setItemQty($skuQtyString);
			$arr = explode(";",$r2);
			$html = "<h1>Webshop response: </h1>";
			foreach ($arr as $e) {
				$col = "green";
				if(strpos($e,'Error') !== FALSE) $col = "red";
				$html .= " -><em style='color:".$col."'>".$e."</em><br>";
			}
			$log .= $html;
		}
        $log .= "<h1>Fetched Plenty Stock Data for <b>".$counter."</b> Items</h1></body></html>";
		$fp = fopen('log/latest-log.html', 'w');
		fwrite($fp, $log); //save log to file
		fclose($fp);
        echo "saved log to morgenland-teppiche.com/wawi2/Controller-Rest/log/latest-log.html";
		fclose($oldFile);
    }
	//deprecated
    elseif($_GET["action"] === "importPlentyProducts"){
        header('Content-Type: text/html; charset=utf-8');
        $_SESSION["importPlentyProducts"] = "Done";
        echo fillPlentyProductsTable();
    }
	//Check Plenty products against Wawi -> Update system with lower qty value
    /*elseif ($_GET["action"] === "syncPlentyQty"){
        header('Content-Type: text/html; charset=utf-8');
        $_SESSION["syncPlentyQty"] = "Done";
        //1. Fill PlentyTable
        //echo fillPlentyProductsTable();
        $token = PlentyApi::getToken();
        //2. Foreach PlentyProduct -> Check this Sku's Qty in Wawi2
        $number = Plenty::countAllProducts();
        $limit = 300;
        $maxpage = ceil($number/$limit);
        for($i=1; $i<=$maxpage; $i++){
            $products = Plenty::getItems($i,$limit);
            foreach ($products as $plentyproduct) {
               //get same product from wawi2 --> compare qty
                $sku = $plentyproduct["sku"];
                $wawiproduct = $productsModel->getBySku($sku);
                if(is_array($wawiproduct)){
                    $qtyWawi = intval($wawiproduct["qty"]);
                    $qtyPlenty = intval($plentyproduct["physicalStock"]);
                    echo "<br>'".$sku."' qtyPlenty = <b>".$qtyPlenty."</b> qtyWawi = <b>".$qtyWawi."</b>";
                    //COMPARE QTY -> Do Action
                    if ($qtyWawi < $qtyPlenty){
                        $plentyItemId = $plentyproduct["itemId"];
                        $plentyVariationId = $plentyproduct["variationId"];
                        //Wawi is lower -> Set Plenty
                        echo "  <em style='background-color:darkorange;color: white;'> ---> WAWI IS LOWER [Set Plenty to] -> [<b>".$qtyWawi."</b>] </em>".$plentyItemId." variationId: ".$plentyVariationId."<br>";
                        echo "Plenty Rest response: ".PlentyApi::setItemQty($token,$plentyItemId,$plentyVariationId,$qtyWawi);
                    }
                    else if ($qtyWawi == $qtyPlenty) echo "  <em style='color: darkgreen;'> ---> Same Qty, DO NOTHING</em>"; //Both same
                    else if ($qtyWawi > $qtyPlenty){
                        //Plenty is lower -> Set Wawi
                        echo "  <em style='background-color:yellowgreen;color: white;'> ---> PLENTY IS LOWER [set Webshop+Wawi] -> [<b>".$qtyPlenty."</b>] </em>";
                        $productsModel->updateStock($sku,$qtyPlenty);
                        echo "Webshop Rest response: ".$webshop->setItemQty($sku.",".$qtyPlenty);
                    }
                }else {
                    echo "<br><em style='color: red;'>Error: Plenty SKU '".$sku."' not found in WAWI</em>";
                }
            }
        }
    }*/
    elseif ($_GET["action"] === "showDifference"){
        header('Content-Type: text/html; charset=utf-8');
		    $info = "";
		//Import plenty data
		$oldFile = fopen('https://morgenland-teppiche.com/wawi2/plenty/plentyExportFull.csv','r');
		$counter = 0;
		$headerOK = false;
		$indxSku = 50;$indxId=50;$indxVariantId =50;
		$indxIsMain=50;$indxMainVariantId=50;$indxStock=50;
		$indxBarcode=50;$indxAttrValue=50;$indxWeightG=50;
		$info .= "<h2>Difference between 'plentyExportFull.csv' (plenty Net Stock) and Wawi</h2>";
		while (($line = fgetcsv($oldFile, 0, ';')) !== FALSE) {
			if(!empty($line)){
				if($counter == 0) {
					//header check
					$lineSize = count($line);
					for ($i=0; $i<$lineSize; $i++){
						$headername = $line[$i];
						if(strpos($headername, 'Variation.number') !== false ) $indxSku = $i;
						else if(strpos($headername, 'Item.id') !== false ) $indxId = $i;
						else if(strpos($headername, 'Variation.id') !== false ) $indxVariantId = $i;
						else if(strpos($headername, 'Variation.isMain') !== false ) $indxIsMain = $i;
						else if(strpos($headername, 'Variation.mainVariationId') !== false ) $indxMainVariantId = $i;
						else if(strpos($headername, 'VariationStock.physicalStock') !== false ) $indxPhysStock = $i;
						else if(strpos($headername, 'VariationStock.reservedStock') !== false ) $indxResvStock = $i;
						else if(strpos($headername, 'VariationBarcode.code') !== false ) $indxBarcode = $i;
						else if(strpos($headername, 'VariationAttributeValues.attributeValues') !== false ) $indxAttrValue = $i;
						else if(strpos($headername, 'Variation.weightG') !== false ) $indxWeightG = $i;
					}
					$info .= "plenytExportFull.csv (HEADER) <br> <b style='color: red;'>Kopfzeile</b>".json_encode($line)."<br>";

				}
				else {
					$sku = $line[$indxSku];
					$id = $line[$indxId];
					$variantId = $line[$indxVariantId];
					$isMain =$line[$indxIsMain];
					$mainVariationId = $line[$indxMainVariantId];
					$physicalStock = $line[$indxPhysStock];
					$resvStock = $line[$indxResvStock];
					if($isMain === "" || empty($isMain)) $isMain = 0;
					if($mainVariationId === "" || empty($mainVariationId)) $mainVariationId = 0;
					if($physicalStock === "" || empty($physicalStock)) $physicalStock = 0;
					if($resvStock === "" || empty($resvStock)) $resvStock = 0;
					
					$netStock = intval($physicalStock)-intval($resvStock); //Net Stock
					
					$barcode = $line[$indxBarcode];
					$attrValues=$line[$indxAttrValue];
					$weightG = $line[$indxWeightG];
						
					$info .= "<br>[".$counter."] <b>".$sku."</b> variantId: ".$variantId.", physicalStock - resvStock: ".$physicalStock." - ".$resvStock." = <b>".$netStock."</b>";
					
					//**check plenty netStock against wawi
					$wawiproduct = $productsModel->getBySku($sku);
					if(is_array($wawiproduct)){
						$qtyWawi = intval($wawiproduct["qty"]);
						$qtyPlenty = $netStock;
						$info .= " -> netStock Plenty = <b>".$qtyPlenty."</b>  Wawi = <b>".$qtyWawi."</b>";
						//COMPARE QTY
						if ($qtyWawi < $qtyPlenty) $info .= " <em style='background-color:darkorange;color: white;'> ---> WAWI IS LOWER -> [<b>".$qtyWawi."</b>]</em>"; //Wawi is lower
						else if ($qtyWawi == $qtyPlenty) $info .= "  <em style='color: darkgreen;'> ---> SAME</em>";
						else if ($qtyWawi > $qtyPlenty) $info  .= " <em style='background-color:yellowgreen;color: white;'> ---> PLENTY IS LOWER -> [<b>".$qtyPlenty."</b>] </em>"; //Plenty is lower
					}
					else $info .= " -><em style='color: red;'>Error: Plenty SKU '".$sku."' not found in WAWI</em>";
					//**End
				}
				$counter ++;
			}
		}
		fclose($oldFile);
		echo $info;
    }
	
    elseif ($_GET["action"] === "asSold"){
        header('Content-Type: text/html; charset=utf-8');
        echo $productsModel->setAsSold($_GET["sku"]);
    }
}

function decrementProductsQty($slimpoducts){
    global $productsModel;
    foreach ($slimpoducts as $product) {
        $sku = $product["skuu"];
        $qtyInCart = intval($product["qtyInCart"]);
        $p = $productsModel->getBySku($sku);
        if(is_array($p)) {
            $qty = intval($p["qty"]);
            $newQty = $qty-$qtyInCart;
            $productsModel->updateStock($sku,$newQty);
            Logs::insertNewLog("ORDER-ITEM updateQty + setAsSold '".$sku."' newQty: ".$newQty,Logs::$type_system);
            $productsModel->setAsSold($sku);
        }
    }
}

function fillPlentyProductsTable(){
    $info = "";
    //Import plenty data
    $oldFile = fopen('https://morgenland-teppiche.com/wawi2/plenty/plentyExportFull.csv','r');
    $counter = 0;
    $headerOK = false;
    $indxSku = 50;$indxId=50;$indxVariantId =50;
    $indxIsMain=50;$indxMainVariantId=50;$indxStock=50;
    $indxBarcode=50;$indxAttrValue=50;$indxWeightG=50;
    Plenty::deleteAllItems();
    $info .= "<h2>1.Truncated 'plentyproducts' table</h2><h2>2.Now inputting new data into table</h2>";
    while (($line = fgetcsv($oldFile, 0, ';')) !== FALSE) {
        if(!empty($line)){
            if($counter == 0) {
                //header check
                $lineSize = count($line);
                for ($i=0; $i<$lineSize; $i++){
                    $headername = $line[$i];
					if(strpos($headername, 'Variation.number') !== false ) $indxSku = $i;
					else if(strpos($headername, 'Item.id') !== false ) $indxId = $i;
					else if(strpos($headername, 'Variation.id') !== false ) $indxVariantId = $i;
					else if(strpos($headername, 'Variation.isMain') !== false ) $indxIsMain = $i;
					else if(strpos($headername, 'Variation.mainVariationId') !== false ) $indxMainVariantId = $i;
					else if(strpos($headername, 'VariationStock.physicalStock') !== false ) $indxPhysStock = $i;
					else if(strpos($headername, 'VariationStock.reservedStock') !== false ) $indxResvStock = $i;
					else if(strpos($headername, 'VariationBarcode.code') !== false ) $indxBarcode = $i;
					else if(strpos($headername, 'VariationAttributeValues.attributeValues') !== false ) $indxAttrValue = $i;
					else if(strpos($headername, 'Variation.weightG') !== false ) $indxWeightG = $i;
                }
                $info .= "Import from plenytExportFull.csv (HEADER) <br> <b style='color: red;'>Kopfzeile</b>".json_encode($line)."<br>";

            }
            else {
				$sku = $line[$indxSku];
				$id = $line[$indxId];
				$variantId = $line[$indxVariantId];
				$isMain =$line[$indxIsMain];
				$mainVariationId = $line[$indxMainVariantId];
				$physicalStock = $line[$indxPhysStock];
				$resvStock = $line[$indxResvStock];
				if($isMain === "" || empty($isMain)) $isMain = 0;
				if($mainVariationId === "" || empty($mainVariationId)) $mainVariationId = 0;
				if($physicalStock === "" || empty($physicalStock)) $physicalStock = 0;
				if($resvStock === "" || empty($resvStock)) $resvStock = 0;
				
				$netStock = intval($physicalStock)-intval($resvStock); //Net Stock
				
				$barcode = $line[$indxBarcode];
				$attrValues=$line[$indxAttrValue];
				$weightG = $line[$indxWeightG];
					
                $info .= "<br>[".$counter."] <b>".$sku."</b> ".$id."  variantId: ".$variantId.", physicalStock - resvStock: ".$physicalStock." - ".$resvStock." = <b>".$netStock."</b> <small>attrValues: ".$attrValues." weight: ".$weightG."</small><br>";
                $res = Plenty::insertItem($sku,$id,$variantId,$isMain,$mainVariationId,$physicalStock,$barcode,$attrValues,$weightG);

                if(strpos($res,'Error') !== FALSE) $info .= "<em style='color:red;'>".$res."</em>";
                else $info .= "<em style='color:green;'>".$res."</em>";
            }
            $counter ++;
        }
    }
    fclose($oldFile);
    return $info;
}

