<?php

//AJAX CONTENT

require_once dirname(__FILE__) . '/../../config.php';
require_once dirname(__FILE__) . '/../protect.php';

$action = @$_GET["id"];
$ctype = @$_GET["ctype"];

function plateCostingFrame($mpwId)
{
    global $db;
    $query = new sqlBuilder("SELECT", "mpw");
    $query->addBind('frame');
    $query->addCondition("frame is not null AND id = $mpwId LIMIT 1");
    $data = $query->getData();

    if (count($data) == 0) {
        return null;
    }

    return $data[0]["frame"];
}

function getValues()
{
    global $db;

    $details = $_POST["did"];
    $did = explode(",", $details);

    $detail = intval($did[0]);
    try {
        $q = $db->prepare("SELECT `pid` FROM `details` WHERE `id` = :detail");
        $q->bindValue(':detail', $detail, PDO::PARAM_INT);
        $q->execute();
        $row = $q->fetch();
    } catch (PDOException $e) {
        echo 'Wystapil blad biblioteki PDO: ' . $e->getMessage();
    }
    $pid = $row["pid"];
    $q->closeCursor();

    $q = $db->prepare("SELECT `src` FROM `projects` WHERE `id` = :pid");
    $q->bindValue(':pid', $pid, PDO::PARAM_INT);
    $q->execute();
    $row = $q->fetch();

    $src = $row["src"];
    $_SESSION["psrc"] = $src;

    $q->closeCursor();

    $output = array();
    //Get version
    $version = array();
    foreach (glob($src . "/*", GLOB_ONLYDIR) as $dir) {
        $v = filter_var(basename($dir), FILTER_SANITIZE_NUMBER_INT);
        if (basename($dir) == "V" . $v || basename($dir) == "v" . $v) {
            if (array_search($v, $version) === false && in_array($v, $version) === false) {
                array_push($version, $v);
            }
        }
    }
    arsort($version);

    $output["version"] = json_encode($version);

    if ($_GET["t"] == 2) { // INIT Profile
        $radius = array(); // GET RADIUS
        foreach ($version as $v) {
            foreach (glob($src . "/V" . $v . "/*", GLOB_ONLYDIR) as $r) {
                $t_radius = explode("R", basename($r));
                if (basename($r) == "R" . $t_radius[1]) {
                    array_push($radius, $t_radius[1]);
                }
            }
            arsort($radius);
            $output["radius"] = json_encode($radius);
        }
    }
    return $output;
}

function createCosting()
{
    global $db;
    $details = $_POST["did"];
    $did = explode(",", $details);
    if (!is_array($did)) {
        $did[0] = $details;
    }

    $material = intval($_POST["material"]);
    $q = $db->prepare("SELECT `sname` FROM `material` WHERE `id` = :mid");
    $q->bindValue(":mid", $material, PDO::PARAM_INT);
    $q->execute();
    $row = $q->fetch();
    $smaterial = $row["sname"];

    $version = "V" . $_POST["version"];
    $pieces = $_POST["pieces"];
    $thickness = doubleval($_POST["thickness"]);
    $des = $_POST["des"];
    $atribute = array();
    $atribute_t = array();
    $radius = "";

    if (isset($_SESSION["psrc"]) == false) {

        return "Wystąpił błąd wewnętrzny! NO_SESSION";
    }

//DIRS
    $det_src = $_SESSION["psrc"];
    $prj_src = $_SESSION["psrc"] . "/../../";
    if (file_exists($prj_src . "cost/") === false) {
        make_dir($prj_src . "cost/");
        make_dir($prj_src . "cost/sheet");
        make_dir($prj_src . "cost/roto");
    }

    if (is_array(@$_POST['cba']) || is_object(@$_POST['cba'])) {
        foreach ($_POST['cba'] as $selected) {
            array_push($atribute, $selected);
            $t = "";
            $t = _getChecboxText($selected);
            array_push($atribute_t, $t);
        }
    }
    $jatribute = json_encode($atribute);

    if ($_GET["t"] == 2) { // Olny profile
        $radius = floatval($_POST["radius"]);
        $file_dir = "roto";
    } else {
        $file_dir = "sheet";
    }

//DETAIL EXIST?
    $d_accepted = array();
    $d_src = array();
    foreach ($did as $detail) {
        $_did = intval($detail);
        $q = $db->prepare("SELECT `src` FROM `details` WHERE `id` = :did");
        $q->bindValue(':did', $_did, PDO::PARAM_INT);
        $q->execute();
        $row = $q->fetch();
        $src = $row["src"];

        if ($_GET["t"] == 2) { // Profile
            if ($radius > 0) {
                $_src = $det_src . "/" . $version . "/R" . $radius . "/shd/" . $src;
            } else {
                $_src = $det_src . "/" . $version . "/shd/" . $src;
            }
        } else {
            $_src = $det_src . "/" . $version . "/dxf/" . $src;
        }

        if (file_exists($_src) === false) {
            echo "Detal: " . $_src . " nie istnieje! \n";
        } else {
            //Coppy
            $ts = str_replace(".", "P", $thickness);
            $code = "W" . $pieces . "X" . $ts . "-" . $smaterial;
            $new_name = $code . "-" . $_did;

            if (count($atribute_t) > 0) {
                $new_name .= "-";
                foreach ($atribute_t as $a) {
                    $new_name .= $a;
                }
            }

            $old_src = $_src;
            $efex = explode(".", $src);
            $fex = end($efex);

            $Code = $new_name;
            $new_name .= "." . $fex;
            $fullname = $prj_src . "cost/" . $file_dir . "/" . $new_name;

            $cquery = $db->prepare("SELECT `id` FROM `mpw` WHERE `code` = '$Code'");
            if (file_exists($fullname) == false && $cquery->fetch() == false) {
                copy($old_src, $fullname);

                $pid = $_COOKIE["plProjectId"];
                $date = date("Y-m-d H:i:s");
                $qs = $db->prepare("INSERT INTO `mpw` (`pid`, `src`, `did`, `code`, `version`, `material`, `thickness`, `pieces`, `radius`, `atribute`, `des`, `date`) VALUES (:pid, :src, :did, :code, :version, :material, :thickness, :pieces, :radius, :atribute, :des, :date)");
                $qs->bindValue(":pid", $pid, PDO::PARAM_INT);
                $qs->bindValue(":src", $fullname, PDO::PARAM_STR);
                $qs->bindValue(":did", $_did, PDO::PARAM_INT);
                $qs->bindValue(":code", $Code, PDO::PARAM_STR);
                $qs->bindValue(":version", $_POST["version"], PDO::PARAM_INT);
                $qs->bindValue(":material", $material, PDO::PARAM_INT);
                $qs->bindValue(":thickness", $thickness, PDO::PARAM_STR);
                $qs->bindValue(":pieces", $pieces, PDO::PARAM_INT);
                $qs->bindValue(":radius", $radius, PDO::PARAM_STR);
                $qs->bindValue(":atribute", $jatribute, PDO::PARAM_STR);
                $qs->bindValue(":des", $des, PDO::PARAM_STR);
                $qs->bindValue(":date", $date, PDO::PARAM_STR);
                $qs->execute();
            } else {
                echo "Wycena dla detalu: " . $d_src[$key] . " juz istnieje! \n";
            }
        }
    }
    return "1";
}

function deleteDetail()
{
    global $db;
    $detail = $_POST["did"];
    $Cid = $_COOKIE["cfeCID"];

    $cq = $db->prepare("SELECT `did`, `code`, `src`, `atribute` FROM `mpw` WHERE `id` = :id");
    $cq->bindValue(":id", $Cid, PDO::PARAM_INT);
    $cq->execute();
    $costing = $cq->fetch();

    $details = json_decode($costing["did"]);
    $_details = array();
    if (is_array($details)) {
        foreach ($details as $d) {
            if ($d != $detail) {
                array_push($_details, $d);
            }
        }
    }
    $jdetails = json_encode($_details);

    if (count($_details) > 0) {
        $cu = $db->prepare("UPDATE `mpw` SET `did` = :jd WHERE `id` = :id");
        $cu->bindValue(":jd", $jdetails, PDO::PARAM_STR);
        $cu->bindValue(":id", $Cid, PDO::PARAM_INT);
        $cu->execute();
    } else {
        $cd = $db->prepare("DELETE FROM `mpw` WHERE `id` = :id");
        $cd->bindValue(":id", $Cid, PDO::PARAM_INT);
        $cd->execute();
    }
    //REMOVE FILE

    if (file_exists($costing["src"])) {
        unlink($costing["src"]);
    }

    return "1";
}

//Get values
if ($action == 2) {
    die(json_encode(getValues()));
}
//add to list
if ($action == 1) {
    die(createCosting());
}

//LISTA DO WYCENY
if ($action == 3) {
    $pid = intval($_COOKIE["plProjectId"]);

    $type =
        OT::AUTO_WYCENA_BRAK_WYCENY;
    $ql = $db->prepare("
		SELECT 
		mpw.*, 
		material.name as material_name,
		d.src as detail_src,
		d.type as detail_type
		FROM mpw
        LEFT JOIN material ON material.id = mpw.material
        LEFT JOIN details d ON d.id = mpw.did
        WHERE mpw.pid = '$pid' AND mpw.`type` in ($type)
    ");
    $ql->execute();

    $content = "";

    foreach ($ql as $mpw) {
        $nr = $mpw["id"];
        $material = $mpw["material_name"];

        $pieces = $mpw["pieces"];

        $atribute_s = "";
        $as = str_replace(array("{", "}"), array("[", "]"), $mpw["atribute"]);
        $atribute = json_decode($as, true);
        if (count($atribute) > 0) {
            foreach ($atribute as $a => $b) {
                $atribute_s .= " <b>" . _getChecboxText($b) . "</b> ";
            }
        }

        $did = $mpw["did"];
        $dn = $mpw["code"];
        $dname = $mpw["detail_src"];
        /* if ($fdquery["type"] != $ctype) {
          continue;
          } */

        $buttons = '<div class="btn-group open"><button data-toggle="dropdown" class="btn btn-default btn-xs dropdown-toggle" style="margin-top: -10px;">Opcje<span class="caret"></span></button><ul class="dropdown-menu"><li><a href="#" class="bEditC" data-backdrop="false" data-target="#addToCostingModal" data-toggle="modal">Edytuj</a></li><li><a href="#" class="bDeleteC">Usuń</a></li></ul></div>';

        $plateCostingFrame = plateCostingFrame($nr);
        if ($plateCostingFrame > 0) {
            $buttons = '<a class="btn btn-default btn-xs" href="/plateFrame/' . $plateCostingFrame . '/"><i class="fa fa-clone"></i></a>';
        }

        $content .= "<tr class=\"gradeA\">"
            . "<td class=\"Cid\">$nr</td>"
            . "<td>$dname</td>"
            . "<td class=\"did\" id=\"" . $did . "_id\">$dn</td>"
            . "<td>$material</td>"
            . "<td>$pieces</td>"
            . "<td>$atribute_s</td>"
            . "<td>$buttons</td>"
            . "</tr>";
    }
    die($content);
}

//DATA FOR EDIT
if ($action == 4) {
    $did = $_COOKIE["cfeDID"];
    $Cid = $_COOKIE["cfeCID"];
    $_POST["did"] = $did;

    $values = getValues();

    $cd = $db->prepare("SELECT * FROM `mpw` WHERE `id` = :id");
    $cd->bindValue(":id", $Cid, PDO::PARAM_INT);
    $cd->execute();
    $costing = $cd->fetch();

    $values["dversion"] = $costing["version"];
    $values["dmaterial"] = $costing["material"];
    $values["dthickness"] = $costing["thickness"];
    $values["dpieces"] = $costing["pieces"];
    $values["dradius"] = $costing["radius"];
    $values["datribute"] = $costing["atribute"];
    $values["ddes"] = $costing["des"];

    die(json_encode($values));
}

//EDIT
if ($action == 5) {
    $did = $_COOKIE["cfeDID"];
    $Cid = $_COOKIE["cfeCID"];
    $_POST["did"] = $did;

    deleteDetail();
    createCosting();

    die("1");
}

//DELETE
if ($action == 6) {
    $did = $_COOKIE["cfeDID"];
    $Cid = $_COOKIE["cfeCID"];
    $_POST["did"] = $did;

    deleteDetail();

    die("1");
}

//PRICED LIST
if ($action == 7) {
    $pid = $_COOKIE["plProjectId"];

    $types = OT::AUTO_WYCENA_WYCENIONE . "," .
        OT::RECZNA_WYCENA_PROFILU_WYCENIONE . "," .
        OT::RECZNA_WYCENA_BLACH_WYCENIONE . "," .
        OT::AUTO_WYCENA_BLACH_SINGLE_WYCENIONE;

    $ql = $db->prepare("
		SELECT 
		mpw.*,
		m.name as material_name,
		d.src as detail_src,
		d.type as detail_type,
		mpc.last_price_all_netto, 
		mpc.id as mpc_id,
		pc.priceset,
		pspcc.price_kom_n as splate_all_cost_netto
 		FROM mpw 
		LEFT JOIN material m ON m.id = mpw.material
		LEFT JOIN details d ON d.id = mpw.did
		LEFT JOIN mpc ON mpc.wid = mpw.id
		LEFT JOIN profile_costing pc ON pc.id = mpw.mcp
		LEFT JOIN plate_singlePartCosting pspc ON pspc.detal_code = mpw.code
		LEFT JOIN plate_singlePartCostingCalculate pspcc ON pspcc.plate_singlePartCosting = pspc.id
		WHERE mpw.pid = :pid AND mpw.type in ($types)
	");

    $ql->bindValue(":pid", $pid, PDO::PARAM_INT);
    $ql->execute();

    $content = "";

    foreach ($ql as $mpw) {
        $nr = $mpw["id"];

        $material_id = $mpw["material"];
        $material = $mpw["material_name"];
        $pieces = $mpw["pieces"];

        $addClass = "pitr";
        if ($mpw["type"] == OT::AUTO_WYCENA_WYCENIONE) {
            $mpcid = $mpw["mpc_id"];
            $cost = $mpw["last_price_all_netto"];
        } else if ($mpw["type"] == OT::RECZNA_WYCENA_PROFILU_WYCENIONE) {
            $addClass = "";
            $cost = $mpw["priceset"];
        } else if ($mpw["type"] == OT::AUTO_WYCENA_BLACH_SINGLE_WYCENIONE) {
            $addClass = "pspc";
            $cost = $mpw["splate_all_cost_netto"];
        }


        $atribute_s = "";

        $atribute = json_decode($mpw["attributes"], true);
        if (count($atribute) > 0) {
            foreach ($atribute as $a) {
                $atribute_s .= " <b>" . _getChecboxText($a) . "</b> ";
            }
        }

        $did = $mpw["did"];
        $dn = $mpw["code"];
        $dname = $mpw["detail_src"];

        if ($mpw["detail_type"] != $ctype) {
            continue;
        }

        $content .= "<tr class=\"gradeA\" style=\"cursor: pointer;\" id=\"" . $mpcid . "_tpnr\">"
            . '<td style="text-align: center;"><input type="checkbox" class="form-control sorder" name="sorder[]" value="' . $nr . '" style="width: 20px; height: 20px; margin: 0 auto;"></td>'
            . "<td class=\"$addClass\">$nr</td>"
            . "<td class=\"$addClass\">$dname</td>"
            . "<td class=\"$addClass did\" id=\"" . $did . "_id\">$dn</td>"
            . "<td class=\"$addClass\">$material</td>"
            . "<td class=\"$addClass\">$pieces</td>"
            . "<td class=\"$addClass\">$atribute_s</td>"
            . "<td class=\"$addClass\">$cost zł</td>"
            . "</tr>";
    }

    //Autowycena blach
    $autoCostingprice = $db->prepare("
        SELECT
        d.id as did,
        d.src as detail_name,
        mpw.id as nr,
        mpw.pieces,
        mpw.attributes,
        mpd.name as detail_code,
        m.name as material_name,
        mpcds.price
        FROM 
        details d
        LEFT JOIN plate_multiPartCostingDetailsSettings mpcds ON mpcds.detaild_id = d.id
        LEFT JOIN plate_multiPartDetails mpd ON mpd.did = d.id AND dirId = mpcds.directory_id
        LEFT JOIN mpw mpw ON mpw.id = mpd.mpw
        LEFT JOIN material m ON m.id = mpw.material
        WHERE
        d.pid = :pid
        AND mpw.type >= :mpwtype
        GROUP BY mpcds.directory_id, d.id
    ");
    $autoCostingprice->bindValue(":mpwtype", OT::AUTO_WYCENA_BLACH_MULTI_ZATWIERDZONA, PDO::PARAM_INT);
    $autoCostingprice->bindValue(":pid", $pid, PDO::PARAM_INT);
    $autoCostingprice->execute();

    while ($detail = $autoCostingprice->fetch(PDO::FETCH_ASSOC)) {

        $atribute_s = "";
        $atribute = json_decode($detail["attributes"], true);
        if (count($atribute) > 0) {
            foreach ($atribute as $a) {
                $atribute_s .= " <b>" . _getChecboxText($a) . "</b> ";
            }
        }

        $nr = $detail["nr"];
        $addClass = "auto-costing-plate";
        $dname = $detail["detail_name"];
        $did = $detail["did"];
        $dn = $detail["detail_code"];
        $material = $detail["material_name"];
        $pieces = $detail["pieces"];
        $cost = $detail["price"];

        $content .= "<tr class=\"gradeA\" style=\"cursor: pointer;\">"
            . '<td style="text-align: center;"><input type="checkbox" class="form-control sorder" name="sorder[]" value="MPL' . $nr . 'D' . $did . '" style="width: 20px; height: 20px; margin: 0 auto;"></td>'
            . "<td class=\"$addClass\">$nr</td>"
            . "<td class=\"$addClass\">$dname</td>"
            . "<td class=\"$addClass did\" id=\"" . $did . "_id\">$dn</td>"
            . "<td class=\"$addClass\">$material</td>"
            . "<td class=\"$addClass\">$pieces</td>"
            . "<td class=\"$addClass\">$atribute_s</td>"
            . "<td class=\"$addClass\">$cost zł</td>"
            . "</tr>";
    }
    die($content);
}

//History list
if ($action == 8) {
    $pid = $_COOKIE["plProjectId"];

    $types = OT::AUTO_WYCENA_ZABLOKOWANA_EDYCJA . "," .
        OT::RECZNA_WYCENA_PROFILU_ZABLOKOWANA_EDYCJA . "," .
        OT::RECZNA_WYCENA_BLACHY_ZABLOKOWANA_EDYCJA;

    $ql = $db->prepare("
		SELECT 
		mpw.*,
		m.name as material_name,
		d.src as detail_src,
		d.type as detail_type,
		mpc.last_price_all_netto, 
		mpc.id as mpc_id,
		pc.pricedetailu,
		pspcc.price_kom_n as splate_all_cost_netto
 		FROM mpw 
		LEFT JOIN material m ON m.id = mpw.material
		LEFT JOIN details d ON d.id = mpw.did
		LEFT JOIN mpc ON mpc.wid = mpw.id
		LEFT JOIN profile_costing pc ON pc.id = mpw.mcp
		LEFT JOIN plate_singlePartCosting pspc ON pspc.detal_code = mpw.code
		LEFT JOIN plate_singlePartCostingCalculate pspcc ON pspcc.plate_singlePartCosting = pspc.id
		WHERE mpw.pid = :pid AND mpw.type in ($types)
	");
    $ql->bindValue(":pid", $pid, PDO::PARAM_INT);
    $ql->execute();

    $content = "";

    foreach ($ql as $mpw) {
        $nr = $mpw["id"];
        $material_id = $mpw["material"];
        $material = $mpw["material_name"];
        $pieces = $mpw["pieces"];

        $atribute_s = "";
        $atribute = json_decode($mpw["atribute"]);
        if (count($atribute) > 0) {
            foreach ($atribute as $a) {
                $atribute_s .= " <b>" . _getChecboxText($a) . "</b> ";
            }
        }

        $did = $mpw["did"];
        $dn = $mpw["code"];
        $dname = $mpw["detail_src"];
        if ($mpw["detail_type"] != $ctype) {
            continue;
        }
        if ($mpw["type"] == OT::AUTO_WYCENA_ZABLOKOWANA_EDYCJA) {
            $mpcid = $mpw["mpc_id"];
            $cost = $mpw["last_price_all_netto"];
        } else if ($mpw["type"] == OT::RECZNA_WYCENA_PROFILU_ZABLOKOWANA_EDYCJA) {
            $cost = $mpw["pricedetailu"];
        } else if ($mpw["type"] == OT::AUTO_WYCENA_BLACH_SINGLE_WYCENIONE) {
            $cost = $mpw["splate_all_cost_netto"];
        } else {
            var_dump($mpw);
            die;
        }

        $content .= "<tr class=\"gradeA\" id=\"" . $mpcid . "_tpnr\">"
            . "<td class=\"Cid pitr\">$nr</td>"
            . "<td class=\"pitr\">$dname</td>"
            . "<td class=\"did pitr\" id=\"" . $did . "_id\">$dn</td>"
            . "<td class=\"pitr\">$material</td>"
            . "<td class=\"pitr\">$pieces</td>"
            . "<td class=\"pitr\">$atribute_s</td>"
            . "<td class=\"pitr\">$cost zł</td>"
            . "</tr>";
    }
    die($content);
}