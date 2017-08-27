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

        return $this->render("mainView.php", [
            "directoryId" => $directoryId,
            "directoryName" => $this->getDirectoryName($directoryId),
            "multiPart" => $plateMultiPart,
            "alerts" => $alerts,
            "frameSetup" => $frameSetup,
            "frameView" => $frameDiv,
            "main" => $mainCardModel
        ]);
    }

    /**
     * @param int $directoryId
     * @param int $detailId
     * @return string
     */
    public function viewDetailCard(int $directoryId, int $detailId): string
    {
        $checkboxModel = new CheckboxModel();
        $plateMultiPart = new PlateMultiPart();
        $plateMultiPart->MakeFromDirId($directoryId);
        $mainCardModel = new mainCardModel($plateMultiPart);

        $plateMultiPart->Calculate();
        $mainCardModel->make($plateMultiPart->getPriceFactor());

        return $this->render("detailView.php", [
            "checkbox" => $checkboxModel->renderAttributes(),
            "card" => $mainCardModel,
            "detailId" => $detailId
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
            "program" => $program
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
    private function getDirectoryName(int $directoryId): string {
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
}