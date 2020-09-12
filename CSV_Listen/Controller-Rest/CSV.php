<?php
session_start(); //bei phtml(frontend) wird es gestartet aber im backend nicht ! Und wir manipulieren mit LoginControl die SESSION
header('Access-Control-Allow-Origin: *');
include "bar/BarcodeGenerator.php";
include "bar/BarcodeGeneratorPNG.php";
include "../Model/Products.php";
include "../Model/Logs.php";
include "../Helper/Magento.php";
include "../Helper/Webshop.php";
include "../Helper/Etikett.php";
include "../Helper/Zipper.php";
include "LoginControl.php";

$magento = new Magento;
$webshop = new Webshop;
$products = new Products;
$logs = new Logs;

if (isset($_POST["action"])) {

    if ($_POST["action"] === "download"){
        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){
            $fileNew = fopen('csv/wawi-bestand.csv', 'w'); //create a file (if exist overwrite)
            $str = "";
            $totalcount = $products->countAll();
            $str .= "totalcount: ".$totalcount."<br>";

            $lastPage = intval(ceil($totalcount / 400));
            $str .="lastpage: ".$lastPage. " = ".$totalcount."/400 (ceil)<br>";

            for($page=1;$page<=$lastPage;$page++){
                if($page == 1){
                    //Header
                    $columns = array("sku", "ean", "qty", "type", "name");
                    fputcsv($fileNew, $columns);
                }
                $arr = $products->getAll(400,$page);
                foreach ($arr as $product){
                    $columns = array($product["sku"], $product["ean"], $product["qty"], $product["type"], $product["name"]);
                    fputcsv($fileNew, $columns);
                }
            }
            fclose($fileNew);
            $str .= "<br><a style='background-color: #ffda00;' href='https://morgenland-teppiche.com/wawi2/Controller-Rest/csv/wawi-bestand.csv'>DOWNLOAD BESTAND CSV</a><br>";
            echo $str;

        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }

    else if ($_POST["action"] === "insert"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){
            $filename = "nothing";
            $str = "";
            $str .= json_encode($_FILES);
            if ($_FILES["csv"]["error"] > 0) {
                $str .= "Return Code: " . $_FILES["csv"]["error"] . "<br />";

            }
            else {
                //Print file details
                $str .= "Upload: " . $_FILES["csv"]["name"] . "<br />";
                $str .= "Type: " . $_FILES["csv"]["type"] . "<br />";
                $str .= "Size: " . ($_FILES["csv"]["size"] / 1024) . " Kb<br />";
                $str .= "Temp file: " . $_FILES["csv"]["tmp_name"] . "<br />";

                $filename = $_FILES["csv"]["name"];
                $savedFilename = "insert_".$filename;
                //Store file
                move_uploaded_file($_FILES["csv"]["tmp_name"], "csv/" . $savedFilename);
                $str .= "Stored in: " . "csv/" . $savedFilename. "<br />";

                $oldFile = fopen("csv/".$savedFilename, 'r');

                $counter = 0;
                $headerOK = false;
                while (($line = fgetcsv($oldFile, 0, ',')) !== FALSE) {
                    if(!empty($line)){
                        if($counter == 0) {
                            //header check
                            if ($line[0] === "sku" && $line[1] === "ean" && $line[2] === "qty" && $line[3] === "type" && $line[4] === "name"){
                                $headerOK = true;
                                $str .= $line[0]." (HEADER) übersprungen <br> Kopfzeile ist OK";
                            }else $str .= $line[0]." (HEADER) übersprungen <br> <b style='color: red;'>Kopfzeile ist FEHLERHAFT</b>";

                        }
                        else if($headerOK){
                            $sku = $line[0];
                            $ean = $line[1];
                            $qty = $line[2];
                            $type = $line[3];
                            $name = $line[4];
                            if(strpos($type, 'parent') !== false) {
                                $str .=  "[".$counter."] ".$sku." ".$type." -> SKIP <br>";
                            }else {
                                $r = $products->insert($type,$sku,$ean,$qty,$name);
                                if(strpos($r, 'Error') !== false) $r = "<span style='color: red;'>".$r."</span>";
                                else{
                                    //$r .= Etikett::createEtikettKlein($sku,$ean);
                                    $r = "<span style='color: green;'>".$r."</span>";
                                }

                                $str .=  "[".$counter."] ".$sku." ".$ean." qty: ".$qty." ".$type." -> ".$r."<br>";
                            }

                        }
                        $counter ++;
                    }
                }
                fclose($oldFile);
            }
            echo $str;
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    else if ($_POST["action"] === "update-stock"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){
            $filename = "nothing";
            $str = "";
            $str .= json_encode($_FILES);
            if ($_FILES["csv"]["error"] > 0) {
                $str .= "Return Code: " . $_FILES["csv"]["error"] . "<br />";

            }
            else {
                //Print file details
                $str .= "Upload: " . $_FILES["csv"]["name"] . "<br />";
                $str .= "Type: " . $_FILES["csv"]["type"] . "<br />";
                $str .= "Size: " . ($_FILES["csv"]["size"] / 1024) . " Kb<br />";
                $str .= "Temp file: " . $_FILES["csv"]["tmp_name"] . "<br />";

                $filename = $_FILES["csv"]["name"];
                $savedFilename = "updateStock_".$filename;
                //Store file
                move_uploaded_file($_FILES["csv"]["tmp_name"], "csv/" . $savedFilename);
                $str .= "Stored in: " . "csv/" . $savedFilename. "<br />";

                $oldFile = fopen("csv/".$savedFilename, 'r');

                $counter = 0;
                $headerOK = false;
                while (($line = fgetcsv($oldFile, 0, ',')) !== FALSE) {
                    if(!empty($line)){
                        if($counter == 0) {
                            //header check
                            if ($line[0] === "sku" && $line[1] === "qty"){
                                $headerOK = true;
                                $str .= $line[0]." (HEADER) übersprungen <br> Kopfzeile ist OK";
                            }else $str .= $line[0]." (HEADER) übersprungen <br> <b style='color: red;'>Kopfzeile ist FEHLERHAFT</b>";

                        }
                        else if($headerOK){
                            $sku = $line[0];
                            $qty = $line[1];
                            $r = $products->updateStock($sku,$qty);

                            if(strpos($r, 'Error') !== false) $r = "<span style='color: red;'>".$r."</span>";
                            else $r = "<span style='color: green;'>".$r."</span>";

                            $r .= magentoWebshopUpdateStock($sku, $qty);

                            $str .=  "[".$counter."] ".$sku." qty: ".$qty." -> ".$r."<br>";
                        }
                        $counter ++;
                    }
                }
                fclose($oldFile);
            }
            echo $str;
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    else if ($_POST["action"] === "process-inventur-liste"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){
            $filename = "nothing";
            $str = "";
            $str .= json_encode($_FILES);
            if ($_FILES["csv"]["error"] > 0) {
                $str .= "Return Code: " . $_FILES["csv"]["error"] . "<br />";

            }
            else {
                //Print file details
                $str .= "Upload: " . $_FILES["csv"]["name"] . "<br />";
                $str .= "Type: " . $_FILES["csv"]["type"] . "<br />";
                $str .= "Size: " . ($_FILES["csv"]["size"] / 1024) . " Kb<br />";
                $str .= "Temp file: " . $_FILES["csv"]["tmp_name"] . "<br />";

                $filename = $_FILES["csv"]["name"];
                $savedFilename = "updateStock_".$filename;
                //Store file
                move_uploaded_file($_FILES["csv"]["tmp_name"], "csv/" . $savedFilename);
                $str .= "Stored in: " . "csv/" . $savedFilename. "<br />";

                $oldFile = fopen("csv/".$savedFilename, 'r');

                $counter = 0;
                $headerOK = false;
                while (($line = fgetcsv($oldFile, 0, ',')) !== FALSE) {
                    if(!empty($line)){
                        if($counter == 0) {
                            //header check
                            if ($line[0] === "ean" && $line[1] === "qty"){
                                $headerOK = true;
                                $str .= $line[0]." (HEADER) übersprungen <br> Kopfzeile ist OK";
                            }else $str .= $line[0]." (HEADER) übersprungen <br> <b style='color: red;'>Kopfzeile ist FEHLERHAFT</b>";

                        }
                        else if($headerOK){
                            $ean = $line[0];
                            $qty = $line[1];
                            $p = $products->getByEan($ean);
                            if(is_array($p)){
                                $r = $products->updateStock($p["sku"],$qty);
                            } else $r = $p;


                            if(strpos($r, 'Error') !== false) $r = "<span style='color: red;'>".$r."</span>";
                            else $r = "<span style='color: green;'>".$r."</span>";

                            if(is_array($p)){
                                $r .= magentoWebshopUpdateStock($p["sku"], $qty);
                            } else $r .= " No Shopupdate";


                            $str .=  "[".$counter."] ".$ean." qty: ".$qty." -> ".$r."<br>";
                        }
                        $counter ++;
                    }
                }
                fclose($oldFile);
            }
            echo $str;
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    else if ($_POST["action"] === "delete"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){
            $filename = "nothing";
            $str = "";
            $str .= json_encode($_FILES);
            if ($_FILES["csv"]["error"] > 0) {
                $str .= "Return Code: " . $_FILES["csv"]["error"] . "<br />";

            }
            else {
                //Print file details
                $str .= "Upload: " . $_FILES["csv"]["name"] . "<br />";
                $str .= "Type: " . $_FILES["csv"]["type"] . "<br />";
                $str .= "Size: " . ($_FILES["csv"]["size"] / 1024) . " Kb<br />";
                $str .= "Temp file: " . $_FILES["csv"]["tmp_name"] . "<br />";

                $filename = $_FILES["csv"]["name"];
                $savedFilename = "delete_".$filename;
                //Store file
                move_uploaded_file($_FILES["csv"]["tmp_name"], "csv/" . $savedFilename);
                $str .= "Stored in: " . "csv/" . $savedFilename. "<br />";

                $oldFile = fopen("csv/".$savedFilename, 'r');

                $counter = 0;
                $headerOK = false;
                while (($line = fgetcsv($oldFile, 0, ',')) !== FALSE) {
                    if(!empty($line)){
                        if($counter == 0) {
                            //header check
                            if ($line[0] === "sku"){
                                $headerOK = true;
                                $str .= $line[0]." (HEADER) übersprungen <br> Kopfzeile ist OK";
                            }else $str .= $line[0]." (HEADER) übersprungen <br> <b style='color: red;'>Kopfzeile ist FEHLERHAFT</b>";

                        }
                        else if($headerOK){
                            $sku = $line[0];
                            $r = $products->delete($sku);
							$s = $webshop->deleteItem($sku);

                            if(strpos($r, 'Error') !== false) $r = "<span style='color: red;'>".$r."</span>";
                            else $r = "<span style='color: green;'>".$r."</span>";

                            $str .=  "[".$counter."] Wawi: ".$sku." -> ".$r."<br> Webshop: ".$s;
                        }
                        $counter ++;
                    }
                }
                fclose($oldFile);
            }
            echo $str;
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }

    else if ($_POST["action"] === "fill-list"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){
            $filename = "nothing";
            $str = "";
            $str .= json_encode($_FILES);
            if ($_FILES["csv"]["error"] > 0) {
                $str .= "Return Code: " . $_FILES["csv"]["error"] . "<br />";

            }
            else {
                //Print file details
                $str .= "Upload: " . $_FILES["csv"]["name"] . "<br />";
                $str .= "Type: " . $_FILES["csv"]["type"] . "<br />";
                $str .= "Size: " . ($_FILES["csv"]["size"] / 1024) . " Kb<br />";
                $str .= "Temp file: " . $_FILES["csv"]["tmp_name"] . "<br />";

                $filename = $_FILES["csv"]["name"];
                $savedFilename = "tofill_".$filename;
                //Store file
                move_uploaded_file($_FILES["csv"]["tmp_name"], "csv/" . $savedFilename);
                $str .= "Stored in: " . "csv/" . $savedFilename. "<br />";

                $oldFile = fopen("csv/".$savedFilename, 'r');
                $fileNew = fopen('csv/filled_'.$filename, 'w'); //create a file (if exist overwrite)

                $counter = 0;
                $headerOK = false;
                while (($line = fgetcsv($oldFile, 0, ',')) !== FALSE) {
                    if(!empty($line)){
                        if($counter == 0) {
                            //header check
                            if ($line[0] === "sku"){
                                $headerOK = true;
                                $str .= $line[0]." (HEADER) übersprungen <br> Kopfzeile ist OK";
                                $columns = array("sku", "ean", "qty", "type", "name");
                                fputcsv($fileNew, $columns);
                            }else $str .= $line[0]." (HEADER) übersprungen <br> <b style='color: red;'>Kopfzeile ist FEHLERHAFT</b>";

                        }
                        else if($headerOK){
                            $sku = $line[0];
                            $r ="";
                            $p = $products->getBySku($sku);
                            if(is_array($p)){
                                $columns = array($sku, $p["ean"], $p["qty"], $p["type"], $p["name"]);//sku,ean,qty,type,name
                                fputcsv($fileNew, $columns);
                                $r .= $sku."  <b style='color: green;'>data filled</b>";
                            }else{
                                $columns = array($sku, "not found", "none", "none", "none");
                                fputcsv($fileNew, $columns);
                                $r .= $sku."  <b style='color: red;'>ERROR </b>".$p;
                            }

                            $str .=  "[".$counter."] ".$sku." -> ".$r."<br>";
                        }
                        $counter ++;
                    }
                }
                fclose($oldFile);
                fclose($fileNew);
            }
            echo "<br><a style='background-color: #ffda00;font-size: xx-large;' href='https://morgenland-teppiche.com/wawi2/Controller-Rest/csv/filled_".$filename."'>DOWNLOAD CSV</a><br> " .$str;
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    else if ($_POST["action"] === "fill-list-ean"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin)){
            $filename = "nothing";
            $str = "";
            $str .= json_encode($_FILES);
            if ($_FILES["csv"]["error"] > 0) {
                $str .= "Return Code: " . $_FILES["csv"]["error"] . "<br />";

            }
            else {
                //Print file details
                $str .= "Upload: " . $_FILES["csv"]["name"] . "<br />";
                $str .= "Type: " . $_FILES["csv"]["type"] . "<br />";
                $str .= "Size: " . ($_FILES["csv"]["size"] / 1024) . " Kb<br />";
                $str .= "Temp file: " . $_FILES["csv"]["tmp_name"] . "<br />";

                $filename = $_FILES["csv"]["name"];
                $savedFilename = "tofill_".$filename;
                //Store file
                move_uploaded_file($_FILES["csv"]["tmp_name"], "csv/" . $savedFilename);
                $str .= "Stored in: " . "csv/" . $savedFilename. "<br />";

                $oldFile = fopen("csv/".$savedFilename, 'r');
                $fileNew = fopen('csv/filled_'.$filename, 'w'); //create a file (if exist overwrite)

                $counter = 0;
                $headerOK = false;
                while (($line = fgetcsv($oldFile, 0, ',')) !== FALSE) {
                    if(!empty($line)){
                        if($counter == 0) {
                            //header check
                            if ($line[0] === "ean"){
                                $headerOK = true;
                                $str .= $line[0]." (HEADER) übersprungen <br> Kopfzeile ist OK";
                                $columns = array("sku", "ean", "qty", "type", "name");
                                fputcsv($fileNew, $columns);
                            }else $str .= $line[0]." (HEADER) übersprungen <br> <b style='color: red;'>Kopfzeile ist FEHLERHAFT</b>";

                        }
                        else if($headerOK){
                            $ean = $line[0];
                            $r ="";
                            $p = $products->getByEan($ean);
                            if(is_array($p)){
                                $columns = array($p["sku"], $p["ean"], $p["qty"], $p["type"], $p["name"]);//sku,ean,qty,type,name
                                fputcsv($fileNew, $columns);
                                $r .= $ean."  <b style='color: green;'>data filled</b>";
                            }else{
                                $columns = array($ean, "not found", "none", "none", "none");
                                fputcsv($fileNew, $columns);
                                $r .= $ean."  <b style='color: red;'>ERROR </b>".$p;
                            }

                            $str .=  "[".$counter."] ".$ean." -> ".$r."<br>";
                        }
                        $counter ++;
                    }
                }
                fclose($oldFile);
                fclose($fileNew);
            }
            echo "<br><a style='background-color: #ffda00;font-size: xx-large;' href='https://morgenland-teppiche.com/wawi2/Controller-Rest/csv/filled_".$filename."'>DOWNLOAD CSV</a><br> " .$str;
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    else if ($_POST["action"] === "create-label-small"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin) || isset($_POST["freaccess"])){
            $filename = "nothing";
            $str = "";
            $str .= json_encode($_FILES);
            if ($_FILES["csv"]["error"] > 0) {
                $str .= "Return Code: " . $_FILES["csv"]["error"] . "<br />";

            }
            else {
                //Print file details
                $str .= "Upload: " . $_FILES["csv"]["name"] . "<br />";
                $str .= "Type: " . $_FILES["csv"]["type"] . "<br />";
                $str .= "Size: " . ($_FILES["csv"]["size"] / 1024) . " Kb<br />";
                $str .= "Temp file: " . $_FILES["csv"]["tmp_name"] . "<br />";

                $filename = $_FILES["csv"]["name"];
                $savedFilename = "etikett_".$filename;
                //Store file
                move_uploaded_file($_FILES["csv"]["tmp_name"], "csv/" . $savedFilename);
                $str .= "Stored in: " . "csv/" . $savedFilename. "<br />";

                $oldFile = fopen("csv/".$savedFilename, 'r');
                $counter = 0;
                $headerOK = false;
                //Delete Dir content
                deleteDirectoryContents("../qr_images/");
                Logs::insertNewLog("Deleted label folder contents ../qr_images/", Logs::$type_system);

                while (($line = fgetcsv($oldFile, 0, ',')) !== FALSE) {
                    if(!empty($line)){
                        if($counter == 0) {
                            //header check
                            if ($line[0] === "sku"){
                                $headerOK = true;
                                $str .= $line[0]." (HEADER) übersprungen <br> Kopfzeile ist OK";
                            }else $str .= $line[0]." (HEADER) übersprungen <br> <b style='color: red;'>Kopfzeile ist FEHLERHAFT</b>";

                        }
                        else if($headerOK){
                            $sku = $line[0];
                            $ean = $line[1];
                            $r ="";
                            $p = $products->getBySku($sku);
                            if(is_array($p)){
                                $r .= $sku."  <b style='color: green;'>Found</b>";
                            }else{
                                $r .= $sku."  <b style='color: red;'>ERROR Product not found: </b>".$p;
                            }
                            //Create Etiketten
                            $rr = Etikett::createEtikettKlein($sku,$ean);
                            $str .=  "[".$counter."] ".$sku." -> ".$r."<br> --->".$rr."<br>";
                        }
                        $counter ++;
                    }
                }
                fclose($oldFile);
                Zipper::zipFolder("../qr_images/","../labels_small.zip");
            }
            echo "<br><a style='background-color: #ffda00;font-size: xx-large;' href='https://morgenland-teppiche.com/wawi2/labels_small.zip'>DONWLOAD ZIP</a><br> " .$str;
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    else if ($_POST["action"] === "create-label-big-small"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin) || isset($_POST["freaccess"])){
            $filename = "nothing";
            $str = "";
            $str .= json_encode($_FILES);
            if ($_FILES["csv"]["error"] > 0) {
                $str .= "Return Code: " . $_FILES["csv"]["error"] . "<br />";

            }
            else {
                //Print file details
                $str .= "Upload: " . $_FILES["csv"]["name"] . "<br />";
                $str .= "Type: " . $_FILES["csv"]["type"] . "<br />";
                $str .= "Size: " . ($_FILES["csv"]["size"] / 1024) . " Kb<br />";
                $str .= "Temp file: " . $_FILES["csv"]["tmp_name"] . "<br />";

                $filename = $_FILES["csv"]["name"];
                $savedFilename = "etikett_".$filename;
                //Store file
                move_uploaded_file($_FILES["csv"]["tmp_name"], "csv/" . $savedFilename);
                $str .= "Stored in: " . "csv/" . $savedFilename. "<br />";

                $oldFile = fopen("csv/".$savedFilename, 'r');
                $counter = 0;
                $headerOK = false;
                //Delete Dir content
                deleteDirectoryContents("../qr_images_batch/");
                Logs::insertNewLog("Deleted label folder contents ../qr_images_batch/", Logs::$type_system);

                while (($line = fgetcsv($oldFile, 0, ',')) !== FALSE) {
                    if(!empty($line)){
                        if($counter == 0) {
                            //header check
                            if ($line[0] === "type"){
                                $headerOK = true;
                                $str .= $line[0]." (HEADER) übersprungen <br> Kopfzeile ist OK";
                            }else $str .= $line[0]." (HEADER) übersprungen <br> <b style='color: red;'>Kopfzeile ist FEHLERHAFT</b>";

                        }
                        else if($headerOK){
                            $type = $line[0];
                            $sku = $line[1];
                            $art = $line[2];
                            $kollektion = $line[3];
                            $masse = $line[4];
                            $farbe = $line[5];
                            $ean = $line[6];
                            $r ="";
                            $p = $products->getBySku($sku);
                            if(is_array($p)){
                                $r .= $sku."  <b style='color: green;'>Found</b>";
                            }else{
                                $r .= $sku."  <b style='color: red;'>ERROR Product not found: </b>".$p;
                            }
                            //Create Etiketten
                            $rr = Etikett::createEtikettGrossUndKlein($type,$sku,$art,$kollektion,$masse,$farbe,$ean,1);
                            $str .=  "[".$counter."] ".$sku." -> ".$r."<br> --->".$rr."<br>";
                        }
                        $counter ++;
                    }
                }
                fclose($oldFile);
                Zipper::zipFolder("../qr_images_batch/","../labels.zip");
            }
            echo "<br><a style='background-color: #ffda00;font-size: xx-large;' href='https://morgenland-teppiche.com/wawi2/labels.zip'>DONWLOAD ZIP</a><br> " .$str;
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
    else if ($_POST["action"] === "vorlage-download"){

        if(LoginControl::isUserLoggedIn(LoginControl::$user_type_admin) || isset($_POST["freaccess"])){
            $number = intval($_POST["number"]);
            $fname = "";
            if($number == 1){
                $fname = 'csv/vorlage_bestand_einpflegen.csv';
                $fileNew = fopen($fname, 'w');
                $columns = array("sku", "ean", "qty", "type", "name");
                fputcsv($fileNew, $columns);
                $columns = array("123Ziegler90x150","12333459", 1, "simple", "123Ziegler90x150 oder leer");
                fputcsv($fileNew, $columns);
                $columns = array("GDUni200x100","14733222", 8, "variant_child", "Uni oder leer");
                fputcsv($fileNew, $columns);
            }
            else if($number == 2) {
                $fname = 'csv/vorlage_bestand_update.csv';
                $fileNew = fopen( $fname, 'w');
                $columns = array("sku","qty");
                fputcsv($fileNew, $columns);
                $columns = array("123Ziegler90x150",1);
                fputcsv($fileNew, $columns);
                $columns = array("GDUni200x100",8);
                fputcsv($fileNew, $columns);
            }
            else if($number == 3 || $number == 4) {
                if($number==3) $fname = 'csv/vorlage_bestand_delete.csv';
                else $fname = 'csv/vorlage_autocomplete_data.csv';
                $fileNew = fopen($fname, 'w');
                $columns = array("sku");
                fputcsv($fileNew, $columns);
                $columns = array("123Ziegler90x150");
                fputcsv($fileNew, $columns);
                $columns = array("GDUni200x100");
                fputcsv($fileNew, $columns);
            }
            else if($number == 41) {
                $fname = 'csv/vorlage_autocomplete_data.csv';
                $fileNew = fopen($fname, 'w');
                $columns = array("ean");
                fputcsv($fileNew, $columns);
                $columns = array("4251469453449");
                fputcsv($fileNew, $columns);
                $columns = array("4251469412934");
                fputcsv($fileNew, $columns);
            }
            else if($number == 5) {
                $fname = 'csv/vorlage_label_generate.csv';
                $fileNew = fopen($fname, 'w');
                $columns = array("type", "sku","art","kollektion","masse","farbe", "ean");
                fputcsv($fileNew, $columns);
                $columns = array("simple","123Ziegler90x150","Ziegler","leer","90 x 150 cm","leer","4251469453449");
                fputcsv($fileNew, $columns);
                $columns = array("variant_child","GDUni200x100","Gabbeh","Uni","100 x 200 cm","Gold","4251469412934");
                fputcsv($fileNew, $columns);
            }
            else if($number == 6) {
                $fname = 'csv/vorlage_inventur_liste.csv';
                $fileNew = fopen($fname, 'w');
                $columns = array("ean","qty");
                fputcsv($fileNew, $columns);
                $columns = array("1470992123","8");
                fputcsv($fileNew, $columns);
                $columns = array("1470992123","1");
                fputcsv($fileNew, $columns);
                $columns = array("1472111111","2");
                fputcsv($fileNew, $columns);
            }
            else if($number == 7) {
                $fname = 'csv/vorlage_label_generate2.csv';
                $fileNew = fopen($fname, 'w');
                $columns = array("sku","ean");
                fputcsv($fileNew, $columns);
                $columns = array("123Ziegler90x150","4251469453449");
                fputcsv($fileNew, $columns);
                $columns = array("GDUni200x100","4251469412934");
                fputcsv($fileNew, $columns);
            }
            echo "<br><a style='background-color: #ffda00; font-size: xx-large;' href='https://morgenland-teppiche.com/wawi2/Controller-Rest/".$fname."'>Vorlage Download LINK- anklicken</a><br>";
        }else {echo '<div style="color: red;">Error: Nutzer nicht autorisiert</div>';}
    }
}
function deleteDirectoryContents($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != '.' && $object != '..') {
                (filetype($dir . '/' . $object) == 'dir') ? deleteDirectoryContents($dir . '/' . $object) : unlink($dir . '/' . $object);
            }
        }
        reset($objects);
    }
    return true;
}

function magentoWebshopUpdateStock($sku, $qty){
    global $magento;
    global $webshop;
    $res = "";
    $res .=" webshop: ".$webshop->setItemQty($sku.",".$qty);
    //$res .=" magento: ".$magento->setItemQty('["Shahir00xcyz", "'.$sku.','.$qty.'"]');
    return $res;
}

/**
 * Generate QR Image Etikett, returns img src
 *
 */
function generateQRImage($uid, $skuu, $qrString, $eanCode){
    //generate nd save ean png
    $generatorPng = new Picqer\Barcode\BarcodeGeneratorPNG();
    file_put_contents("ean13.png", $generatorPng->getBarcode($eanCode, $generatorPng::TYPE_EAN_13, 5,230));//($code, $type, $widthFactor, $totalHeightPx, $color)


    $width  = $height = 290;
    $data = urlencode($qrString); // "bla".$qrString;    *****COMMENT IN
    $error  = "L"; // handle up to "H" 30% data loss, or "L" (7%), "M" (15%), "Q" (25%)
    $border = 0;

    //1.make chart api request -> save as tmp png
    $url = "http://chart.googleapis.com/chart?chs={$width}x{$height}&cht=qr&chld=$error|$border&chl=$data";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if(FALSE === ($retval = curl_exec($ch))) {
        echo error_log(curl_error($ch));
        echo " ERROOOR CURL <br>";
    } else {
        //echo $retval;
        file_put_contents("qr1.png", $retval);
    }

    //2.create big empty image -> put tmp png into this image
    //$logo = imagecreatefromstring(file_get_contents("qr1.png"));
    $data = getimagesize("ean13.png"); //get width and height
    $widthEan = $data[0];$heightEan = $data[1];
    $ean13 = imagecreatefromstring(file_get_contents("ean13.png"));
    $width1 = 600;$height1=408; //final image size
    $imgOut = imagecreate($width1, $height1); // $imgOut = imagecreatetruecolor($width1, $height1);
    $white = imagecolorallocate($imgOut, 255, 255, 255);//white color
    imagefill($imgOut, 0, 0, $white);					//fill img with white
    imagecopy($imgOut, $ean13, 50,120, 0,0, $widthEan,$heightEan); //put ean into img


    //3.write string into image

    $text_colour = imagecolorallocate( $imgOut, 0, 0, 0 );

	ImageTTFText ($imgOut, 25, 0, 20, 60, $text_colour, "DroidSans-Bold.ttf", "uid: ".$uid);
    ImageTTFText ($imgOut, 30, 0, 20, 110, $text_colour, "DroidSans-Bold.ttf", $skuu);//(resourceImg ,size ,angle ,int x ,int y , int col, fontfile, text)
    ImageTTFText ($imgOut, 24, 0, 100, 385, $text_colour, "Roboto-Medium.ttf", $eanCode);
    //4.save new image with uid+sku as name
    imagepng($imgOut, "../qr_images/".$uid."_".$skuu.".png"); // imagepng( resource $image, $to); save $to Pfad, Ordner muss schon existieren !
    //imagedestroy($logo);
    imagedestroy($imgOut);

    $imageSrc = "../qr_images/".$uid."_".$skuu.".png";
    return $imageSrc;
}