<?php
session_start(); //bei phtml(frontend) wird es gestartet aber im backend nicht ! Und wir manipulieren mit LoginControl die SESSION
header('Access-Control-Allow-Origin: *');
include "bar/BarcodeGenerator.php";
include "bar/BarcodeGeneratorPNG.php";
include "../Model/LoginValidate.php"; // ../ -> 1 dir up -> ../../ ->2 dirs up
include "../Model/Products.php";
include "../Model/Logs.php";
include "../Model/Order.php";
include "../Model/LabelSmall.php";
include "../Helper/Magento.php";
include "../Helper/Webshop.php";
include "../Helper/Etikett.php";
include "LoginControl.php";
//Handles request from 'admin.phtml', 'admin-products.phtml', 'admin-logs.html', 'admin-contacts.phtml'

$magento = new Magento;
$webshop = new Webshop;
$orders = new Order;
$labels = new LabelSmall;

$loginValidate = new LoginValidate;
$products = new Products;
$rnd = mt_rand(0, 990);
//*Frontcontroller bearbeitet Ajax Anfragen anhand von $_POST["action"] Befehl***
//Idee $_POST["action"] wird bei jedem Ajax mit verschiedenen Befehlen gesetzt
if (isset($_POST["action"])) {
    if ($_POST["action"] === "log-user-out"){
        LoginControl::destroySession();
        echo true;
    }
    elseif ($_POST["action"] === "log-user-in"){
        if(isset($_POST["uname"]) && isset($_POST["pw"])){
            $uname = $_POST["uname"];
            $pw = $_POST["pw"];

            $userIsValid = $loginValidate->validate_login_admin($uname, $pw); // true OR false | error
            if($userIsValid === TRUE){
                LoginControl::logThisUserIn($uname, $pw, LoginControl::$user_type_admin);
                echo '<i style="color: green;">Welcome </i> '.$uname.' <p><a href="https://morgenland-teppiche.com/wawi2/admin-products.phtml"> ->Click here to proceed.</a> </p>' . json_encode($_SESSION);
            }else {echo '<i style="color: red;">Username/Password is incorrect</i>';}

        }
    }
    elseif ($_POST["action"] === "create"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){

            $productId = $products->insert($_POST["type"], $_POST["sku"],$_POST["ean"],$_POST["qty"],$_POST["name"]);
            $imgSrc="";
            if (strpos($productId, 'Error') !== false) {
                $imgSrc = "ERROR product not inserted";
            }else $imgSrc = Etikett::createEtikettKlein($_POST["sku"],$_POST["ean"]);
            echo $productId."<br><img src='https://morgenland-teppiche.com/wawi2/".$imgSrc."'>";

        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    elseif ($_POST["action"] === "createLabel"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin) || isset($_POST["freaccess"])){
            $sku = $_POST["sku"];
            $str ="";
            if(is_array($labels::getBySku($sku))){
                $str = "Label für diese SKU wurde schonmal erzeugt. trotzdem erzeugen ? <button data-sku='".$sku."' data-ean='".$_POST["ean"]."' onclick='createLabelForce(this)'>Erzeugen erzwingen</button>";
            } else {
                $str = "<img src='https://morgenland-teppiche.com/wawi2/".Etikett::createEtikettKlein($_POST["sku"],$_POST["ean"])."'> Request: ".$_POST["sku"].'  '.$_POST["ean"] ;
                $labels::insertNew($sku);
            }

            echo $str;

        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    elseif ($_POST["action"] === "createLabelForce"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin) || isset($_POST["freaccess"])){

            echo "<img src='https://morgenland-teppiche.com/wawi2/".Etikett::createEtikettKlein($_POST["sku"],$_POST["ean"])."'> Request: ".$_POST["sku"].'  '.$_POST["ean"];

        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    elseif ($_POST["action"] === "delete"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){
            $result ='';
            $result = $products->delete($_POST["sku"]);
            $result .= $webshop->deleteItem($_POST["sku"]);
            echo $result;

        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    elseif ($_POST["action"] === "unsetAsSold"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){
            echo $products->unsetAsSold($_POST["sku"]);
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    elseif ($_POST["action"] === "update"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){
            $sku = $_POST["sku"];
            $qty = $_POST["qty"];
            $result = $products->update($_POST["type"], $sku,$_POST["ean"],$qty,$_POST["name"]);
            $result .= " UPDATE: ".magentoWebshopUpdateStock($sku,$qty);//Magento + Webshop UPDDATE
            echo $result;

        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    elseif ($_POST["action"] === "search"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){

            $result = $products->find($_POST["type"], $_POST["sku"],$_POST["ean"],$_POST["qty"],$_POST["name"], 200,$_POST["soldDate"]);
            if(is_array($result)){
                echo json_encode($result);
            }else echo $result;

        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    elseif ($_POST["action"] === "laufzettel-update-stock"){
        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){

            $system = $_POST["system"];
            $orderId = $_POST["orderId"];
            $sku = $_POST["sku"];
            $orderItemQty = $_POST["qty"];
            if ($orders->itemIsProcessed($system,$orderId,$sku,$orderItemQty)){
                echo "Error: Kann nicht verarbeiten. Ist schon Verarbeitet! : ".$system." ".$orderId." ".$sku." qty: ".$orderItemQty;
            }else {
                $product = $products->getBySku($sku);
                $qty = $product["qty"];
                $newQty = $qty-$orderItemQty;
                if($newQty < 0){
                    echo "Error: Kann nicht Verarbeiten. Qty wäre nach der subtraktion negativ:  ".$system." ".$orderId." ".$sku." orderQty: ".$orderItemQty. "  productQty: ".$qty;
                }else {
                    $res = $products->updateStock($sku,$newQty);
                    $res .= " UPDATE: ".magentoWebshopUpdateStock($sku,$newQty);//Magento + Webshop UPDDATE
                    $res .= "  ". $orders->markItemProcessed($system,$orderId,$sku,$orderItemQty);
                    echo $res;
                }

            }
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    elseif ($_POST["action"] === "laufzettel-update-stock-manuell"){
        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){

            $system = $_POST["system"];
            $sku = $_POST["sku"];
            $qtyOrdered = $_POST["qty"];
            $product = $products->getBySku($sku);
            $qty = $product["qty"];
            $newQty = $qty-$qtyOrdered;
            if ($newQty<0){
                echo "Error: Kann nicht Verarbeiten. Qty wäre nach der subtraktion negativ:  ".$system." ".$sku." orderQty: ".$qtyOrdered. "  productQty: ".$qty;
            }else {
                $res = $products->updateStock($sku,$newQty);
                $res .= " UPDATE: ".magentoWebshopUpdateStock($sku,$newQty);//Magento + Webshop UPDDATE
                echo $res;
            }
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }

    elseif ($_POST["action"] === "laufzettel-mark-processed"){
        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){

            $system = $_POST["system"];
            $orderId = $_POST["orderId"];
            $sku = $_POST["sku"];
            $orderItemQty = $_POST["qty"];
            $res = $orders->markItemProcessed($system,$orderId,$sku,$orderItemQty);
            echo $res;
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    elseif ($_POST["action"] === "show-logs-system"){
        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){

            //echo json_encode(str_replace('"',"'",Logs::getLatestLogs()));
            $arr = Logs::getLatestLogs();
            $size = count($arr);
            $rows ='';
            for($i=0; $i<$size; $i++){
                $rows .= $arr[$i][0].'***'.$arr[$i][1].'***'.$arr[$i][2].'######';
            }
            $rows = substr($rows, 0, -6);//cut last 6 chars '######'
            echo $rows;
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }

	elseif ($_POST["action"] === "search-products-zawak"){
		//no authentification
		$merchantCode = $_POST["merchant_code"]; //is always "merchant001"
		$keyword = $_POST["keyword"];
		if(strpos($merchantCode, 'merchant001') !== false){
			if(strpos($keyword, 'ancy') !== false || strpos($keyword, 'steria') !== false || strpos($keyword, 'eppstar') !== false || strpos($keyword, 'omet') !== false
				|| strpos($keyword, 'agune') !== false){
				
				echo json_encode($products->find("","","","",$keyword, 400,""));
			}
			else {echo "error. Suchwort ungültig";}
		}else {echo "error. Merchant-code ungültig";}
    }
}

function magentoWebshopUpdateStock($sku, $qty){
    global $magento;
    global $webshop;
    $res = "";
    $res .=" webshop: ".$webshop->setItemQty($sku.",".$qty);
    //$res .=" magento: ".$magento->setItemQty('["Shahir00xcyz", "'.$sku.','.$qty.'"]');
    return $res;
}