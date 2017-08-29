<?php
/**
 * Created by PhpStorm.
 * User: dawidadamus
 * Date: 15.08.2017
 * Time: 22:24
 */

require_once dirname(__DIR__) . "/../mainController.php";
require_once dirname(__DIR__) . "/../model/CheckboxModel.php";
require_once dirname(__FILE__) . "/plateMultiPart.php";
require_once dirname(__FILE__) . "/model/mainCardModel/mainCardModel.php";
require_once dirname(__FILE__) . "/model/detailCardModel/detailCardModel.php";

/**
 * Class plateMultiPartController
 */
class plateMultiPartController extends mainController
{
    /**
     * plateMultiPartController constructor.
     */
    public function __construct()
    {
        $this->setViewPath(dirname(__FILE__) . '/view/costing/');
    }

    /**
     * @param int $directoryId
     * @param int $programId
     * @return string
     */
    public function viewMainCard(int $directoryId, int $programId = 0): string
    {
        global $db;
        $frameSetup = false;
        $alerts = [];
        $missingFrames = 0;

        $plateMultiPart = new PlateMultiPart();
        $plateMultiPart->MakeFromDirId($directoryId);
        $mainCardModel = new mainCardModel($plateMultiPart);

        $programs = $plateMultiPart->getPrograms();
        foreach ($programs as $program) {
            $frame = $program->getFrame();

            if ($frame->getValue() <= 0) {
                $alerts[] = [
                    "type" => "warning",
                    "message" => "Program " . $program->getSheetName() . " nie posiada określonej ramki!"
                ];
                $missingFrames++;
                $frameSetup = $plateMultiPart;
            }
        }


        if (isset($_POST["dots"])) { //Zapis gotowej ramki
            $this->SaveFrameData($plateMultiPart, $programId);
            return true;
        }


        $frameDiv = null;
        if ($frameSetup !== false) {
            $frameDiv = $this->render("ImgFrameView.php", [
                "multiPart" => $frameSetup,
            ]);
        }

        if ($frameDiv == null) {
            $plateMultiPart->Calculate();
            $mainCardModel->make($plateMultiPart->getPriceFactor());
        }

        if (isset($_GET["r"])) { //Tylko do testow
            echo '<pre>';
            print_r($mainCardModel);
            echo '</pre>';
        }

        //Info o projektancie
        $designerId = 0;
        $designerQuery = $db->query("
            SELECT 
            user_id
            FROM
            designers
            WHERE
            `type` = 'plateMulti'
            AND item_id = $directoryId
            ORDER BY id DESC
            LIMIT 1
        ");
        $designerQueryData = $designerQuery->fetch();
        if ($designerQueryData !== false) {
            $designerId = $designerQueryData["user_id"];
        }
        $usersQuery = $db->query("
            SELECT 
            id, `name`
            FROM accounts
        ");

        return $this->render("mainView.php", [
            "directoryId" => $directoryId,
            "directoryName" => $this->getDirectoryName($directoryId),
            "multiPart" => $plateMultiPart,
            "alerts" => $alerts,
            "frameSetup" => $frameSetup,
            "frameView" => $frameDiv,
            "main" => $mainCardModel,
            "designerId" => $designerId,
            "users" => $usersQuery->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    /**
     * @param int $directoryId
     * @param int $detailId
     * @return string
     */
    public function viewDetailCard(int $directoryId, int $detailId): string
    {
        $plateMultiPart = new PlateMultiPart();
        $plateMultiPart->MakeFromDirId($directoryId);
        $mainCardModel = new mainCardModel($plateMultiPart);

        if (isset($_POST["p_factor"])) { //Liczenie
            $plateMultiPart->setPriceFactor($_POST["p_factor"]);
        }

        $save = false;
        if (isset($_POST["save"])) {
            $save = true;
        }

        $plateMultiPart->Calculate();
        $mainCardModel->make($plateMultiPart->getPriceFactor(), $save);

        /** @var ProgramData[] $programs */
        $programs = [];
        /** @var ProgramCardPartData $programDetail */
        $programDetail = [];
        /** @var mainCardClientModel $mainClient */
        $mainClient = null;
        /** @var mainCardDetailModel $mainDetail */
        $mainDetail = null;

        foreach ($plateMultiPart->getPrograms() as $program) {
            foreach ($program->getParts() as $part) {
                if ($part->getDetailId() == $detailId) {
                    if (!isset($programs[$program->getSheetName()])) {
                        $programs[$program->getSheetName()] = $program;
                        $programDetail[$program->getSheetName()] = $part;
                        continue 2;
                    }
                }
            }
        }

        foreach ($mainCardModel->getClients() as $client) {
            $detail = $client->getDetail($detailId);

            if ($detail !== false) {
                $mainClient = $client;
                $mainDetail = $detail;
            }
        }

        return $this->render("detailView.php", [
            "checkbox" => $mainDetail->getCheckbox()->renderAttributes($mainDetail->getCountAll()),
            "card" => $mainCardModel,
            "detailId" => $detailId,
            "mainClient" => $mainClient,
            "mainDetail" => $mainDetail,
            "programs" => $programs,
            "programDetail" => $programDetail,
            "directoryId" => $directoryId
        ]);
    }

    public function viewProgramCard(int $directoryId, int $programId): string
    {
        $plateMultiPart = new PlateMultiPart();
        $plateMultiPart->MakeFromDirId($directoryId);
        $mainCardModel = new mainCardModel($plateMultiPart);

        $plateMultiPart->Calculate();
        $mainCardModel->make($plateMultiPart->getPriceFactor());

        $program = $plateMultiPart->getProgramById($programId);

        return $this->render("programView.php", [
            "main" => $mainCardModel,
            "programId" => $programId,
            "program" => $program,
            "directoryId" => $directoryId
        ]);
    }

    /**
     * @param PlateMultiPart $plateMultiPart
     * @param int $programId
     */
    private function SaveFrameData(PlateMultiPart $plateMultiPart, int $programId)
    {
        $program = $plateMultiPart->getProgramById($programId);
        $frame = $program->getFrame();

        $frame->setPoints($_POST["dots"]);
        $frame->setValue($_POST["areaValue"]);
        $frame->save();
    }

    /**
     * @param int $directoryId
     * @return string
     * @throws Exception
     */
    private function getDirectoryName(int $directoryId): string
    {
        global $db;

        $searchQuery = $db->prepare("
            SELECT 
            dir_name
            FROM 
            plate_multiPartDirectories
            WHERE
            id = :id
        ");
        $searchQuery->bindValue(":id", $directoryId, PDO::PARAM_INT);
        $searchQuery->execute();

        $dirData = $searchQuery->fetch();
        if ($dirData === false) {
            throw new \Exception("Brak folderu o id: " . $directoryId);
        }

        return $dirData["dir_name"];
    }

    /**
     * @param int $dirId
     * @param int $userId
     * @return string
     */
    public function changeDesigner(int $dirId, int $userId): string
    {
        $SqlBuilder = new sqlBuilder(sqlBuilder::INSERT, "designers");
        $SqlBuilder->bindValue("user_id", $userId, PDO::PARAM_INT);
        $SqlBuilder->bindValue("type", "plateMulti", PDO::PARAM_STR);
        $SqlBuilder->bindValue("item_id", $dirId, PDO::PARAM_INT);
        $SqlBuilder->bindValue("added_by", $_SESSION["login"], PDO::PARAM_INT);
        $SqlBuilder->bindValue("created_at", date("Y-m-d H:i:s"), PDO::PARAM_STR);
        $SqlBuilder->flush();
        return "ok";
    }

    /**
     * @param int $directoryId
     */
    public function block(int $directoryId)
    {
        global $db;

        $mpwDataQuery = $db->prepare("
            SELECT 
            mpw
            FROM
            plate_multiPartDetails
            WHERE
            dirId = :dir
        ");
        $mpwDataQuery->bindValue(":dir", $directoryId, PDO::PARAM_INT);
        $mpwDataQuery->execute();

        $newType = OT::AUTO_WYCENA_BLACH_MULTI_ZABLOKOWANE;

        while ($mpw = $mpwDataQuery->fetch(PDO::FETCH_ASSOC)) {
            $mpwId = $mpw["mpw"];

            $mpwUpdateQuery = $db->prepare("
              UPDATE
              mpw
              SET `type` = $newType
              WHERE 
              id = :id
            ");
            $mpwUpdateQuery->bindValue(":id", $mpwId, PDO::PARAM_INT);
            $mpwUpdateQuery->execute();
        }
    }
}