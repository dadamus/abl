<?php
/**
 * Created by PhpStorm.
 * User: dawidadamus
 * Date: 07.09.2017
 * Time: 22:16
 */

/**
 * Class PlateSyncController
 */
class PlateSyncController
{
    /**
     * @param array $data
     * @throws Exception
     */
    public function syncAction(array $data)
    {
        global $db;

        $programs = $data['programs'];
        $materials = $data['materials'];
        $materialId = 0;

        foreach ($programs as $program) {
            $sheetName = str_replace(['+', ' '], ['.', '.'], urldecode($program["SheetName"]));
            $sheetCount = $program["SheetCount"];
            $details = $program["Details"];
            $sheetNumber = $program["SheetId"];

            //Material
            $materialRow = $materials[$materialId];

            if (@$materialRow['UsedSheetNum'] <= 0) {
                $materialId++;
            }

            $materials[$materialId]['UsedSheetNum'] -= 1;
            $materialName = $materials[$materialId]['SheetCode'];

            //Id z bazy
            $plateQuery = $db->prepare('SELECT id FROM plate_warehouse WHERE SheetCode = :sheetName');
            $plateQuery->bindValue(':sheetName', $materialName, PDO::PARAM_STR);
            $plateQuery->execute();
            $plateData = $plateQuery->fetch();

            //insert do queue
            $queryBuilder = new sqlBuilder(sqlBuilder::INSERT, 'cutting_queue');
            $queryBuilder->bindValue('sheet_count', $sheetCount, PDO::PARAM_INT);
            $queryBuilder->bindValue('sheet_name', $sheetName, PDO::PARAM_STR);
            $queryBuilder->bindValue('created_at', date("Y-m-d H:i:s"), PDO::PARAM_STR);
            $queryBuilder->bindValue('modified_at', date("Y-m-d H:i:s"), PDO::PARAM_STR);
            $queryBuilder->flush();

            $cuttingQueueId = $db->lastInsertId();

            //Robimy dziwne foldery dla podprogramow zeby statusy im mozna bylo zmieniac
            for ($s = 0; $s < $sheetCount; $s++) {
                $programListQuery = new sqlBuilder(sqlBuilder::INSERT, 'cutting_queue_list');
                $programListQuery->bindValue('lp', $s + 1, PDO::PARAM_INT);
                $programListQuery->bindValue('cutting_queue_id', $cuttingQueueId, PDO::PARAM_INT);
                $programListQuery->bindValue('state', 0, PDO::PARAM_INT);
                $programListQuery->flush();

                $listId = $db->lastInsertId();

                foreach ($details as $detail) {
                    $detailName = $detail["PartName"];
                    $quantity = $detail["Quantity"];
                    $RectangleAreaW = $detail["RectangleAreaW"];

                    $oitemId = $this->getOItemIdByDetailName($detailName);

                    $detailQuery = new sqlBuilder(sqlBuilder::INSERT, 'cutting_queue_details');
                    $detailQuery->bindValue('cutting_queue_list_id', $listId, PDO::PARAM_INT);
                    $detailQuery->bindValue('oitem_id', $oitemId, PDO::PARAM_INT);
                    $detailQuery->bindValue('quantity', $quantity, PDO::PARAM_INT);
                    $detailQuery->bindValue('plate_warehouse_id', $plateData['id'], PDO::PARAM_INT);
                    $detailQuery->bindValue('LaserMatName', $program['LaserMatName'], PDO::PARAM_STR);
                    $detailQuery->bindValue('RectangleAreaW', $RectangleAreaW, PDO::PARAM_STR);
                    $detailQuery->flush();
                }
            }

            $programQuery = new sqlBuilder(sqlBuilder::INSERT, 'programs');
            $programQuery->bindValue('new_cutting_queue_id', $cuttingQueueId, PDO::PARAM_INT);
            $programQuery->bindValue('name', $sheetName, PDO::PARAM_STR);
            $programQuery->flush();

            $programId = $db->lastInsertId();

            //Ustawie parenta blachy
            $parentId = $this->setPlateChildren($plateData['id'], $sheetName);

            $plateId = $parentId;
            if ($parentId === null) {
                $plateId = $plateData['id'];
            }
            $this->getImg($plateId, $programId, $sheetNumber);
        }
    }


    /**
     * @param int $sheetId
     * @param string $programName
     * @return |null
     */
    private function setPlateChildren(int $sheetId, string $programName)
    {
        global $db;

        $try = 0;
        do {
            $try++;

            $plateQuery = new sqlBuilder(sqlBuilder::SELECT, 'plate_warehouse');
            $plateQuery->addBind('id');
            $plateQuery->addBind('SheetCode');
            $plateQuery->addCondition('SheetCode like "%' . $programName . '%"');
            $data = $plateQuery->getData();

            if (count($data) === 0) {
                if ($try <= 3) {
                    sleep(1);
                    continue;
                }
                return null;
                break;
            }

            $rowData = reset($data);

            $updateQuery = $db->prepare("UPDATE plate_warehouse SET parentId = $sheetId WHERE id = '" . $rowData['id'] . "'");
            $updateQuery->execute();
            return $rowData['id'];
        } while (count($data) === 0);
    }

    /**
     * @param int $sheetId
     * @param int $programId
     * @param int $sheetNumber
     * @return bool
     */
    private function getImg(int $sheetId, int $programId, int $sheetNumber): bool
    {
        global $data_src, $db;

        if ($sheetId < 1) {
            return false;
        }

        $imgNumber = $sheetNumber + 1;
        $filePath = $data_src . 'temp/' . $imgNumber . '.bmp';
        $uploadPath = $data_src . 'program_image/';

        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $newName = $programId . '_' . date('Y_m_d_H_i_s') . '_' . rand() . '.bmp';
        $newPath = $uploadPath . $newName;

        if (file_exists($filePath)) {
            rename($filePath, $newPath);
        } else {
            echo $filePath . ' nie istnieje!';
        }

        $sqlBuilder = new sqlBuilder(sqlBuilder::INSERT, 'sheet_image');
        $sqlBuilder->bindValue('plate_warehouse_id', $sheetId, PDO::PARAM_INT);
        $sqlBuilder->bindValue('program_id', $programId, PDO::PARAM_INT);
        $sqlBuilder->bindValue('src', $newPath, PDO::PARAM_STR);
        $sqlBuilder->bindValue('upload_date', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $sqlBuilder->flush();

        return true;
    }

    /**
     * @param string $detailName
     * @return null|int
     * @throws Exception
     */
    private function getOItemIdByDetailName(string $detailName)
    {
        global $db;

        $searchQuery = $db->prepare("SELECT id FROM oitems WHERE `name` = :name");
        $searchQuery->bindValue(':name', $detailName, PDO::PARAM_STR);
        $searchQuery->execute();

        $searchData = $searchQuery->fetch();

        if (!$searchQuery) {
            throw new \Exception('Brak detalu: ' . $detailName);
        }

        return $searchData['id'];
    }
}