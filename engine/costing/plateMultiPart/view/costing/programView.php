<?php
/** @var mainCardModel $main */
$main = $data["main"];
/** @var ProgramData $mainProgram */
$mainProgram = $data["program"];
/** @var MaterialData $mainMaterial */
$mainMaterial = $mainProgram->getMaterial();
?>
<div class="row">
    <div class="col-lg-12">
        <h2 class="page-title">Costing - multipart - karta programu </h2>
    </div>
</div>

<form id="count" method="POST" action="?">
    <input type="hidden" name="program_id" value="<?= $mainProgram->getId() ?>">
    <div class="row">
        <div class="col-lg-12">
            <div class="portlet box green-soft">
                <div class="portlet-title">
                    <div class="caption">
                        Informacje
                    </div>
                    <div class="actions">
                        <a class="btn btn-default" href="/plateMulti/<?= $data["directoryId"] ?>/">Main</a>
                        <div class="btn-group">
                            <a class="btn btn-default" href="javascript:;" data-toggle="dropdown"
                               aria-expanded="false">
                                <i class="fa fa-list"></i> Programy
                                <i class="fa fa-angle-down "></i>
                            </a>
                            <ul class="dropdown-menu pull-right" style="position: absolute;">
                                <?php
                                $programs = $main->getPlateMultiPart()->getPrograms();
                                ?>
                                <?php foreach ($programs as $program): ?>
                                    <li>
                                        <a href="/plateMulti/program/<?= $data["directoryId"] ?>/<?= $program->getId() ?>/">
                                            <?= $program->getSheetName() ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="btn-group">
                            <a class="btn btn-default" href="javascript:;" data-toggle="dropdown"
                               aria-expanded="false">
                                <i class="fa fa-list"></i> Detale
                                <i class="fa fa-angle-down "></i>
                            </a>
                            <ul class="dropdown-menu pull-right" style="position: absolute;">
                                <?php
                                $usedDetails = [];
                                $clients = $main->getClients();
                                ?>
                                <?php foreach ($clients as $client): ?>
                                    <?php foreach ($client->getDetails() as $detail): ?>
                                        <?php
                                        if (isset($usedDetails[$detail->getDetailId()])) {
                                            continue;
                                        }
                                        ?>
                                        <li>
                                            <a href="/plateMulti/detail/<?= $data["directoryId"] ?>/<?= $detail->getDetailId() ?>/">
                                                <?= $detail->getProject()->getDetailName() ?>
                                            </a>
                                        </li>
                                        <?php
                                        $usedDetails[$detail->getDetailId()] = true;
                                        ?>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="portlet-body">
                    <div class="table-scrollable">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Nazwa programu</th>
                                <th>Materiał</th>
                                <th>Ilość</th>
                                <th>Rozmiar</th>
                                <th>Grubość</th>
                                <th>SheetCode</th>
                                <th>Tabela</th>
                                <th>Czas przeładunku</th>
                                <th>Wartość przeladunku</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td><?= $mainProgram->getSheetName() ?></td>
                                <td><b><?= $mainMaterial->getName() ?></b> - <i><?= $mainMaterial->getMatName() ?></i></td>
                                <td><?= $mainProgram->getSheetCount() ?></td>
                                <td><?= $mainMaterial->getSheetSize() ?></td>
                                <td><?= $mainMaterial->getThickness() ?></td>
                                <td><?= $mainMaterial->getSheetCode() ?></td>
                                <td><b><?= $mainProgram->getParts()[0]->getLaserMatName() ?></b></td>
                                <td><input
                                            class="form-control"
                                            value="<?= globalTools::seconds_to_time($mainProgram->getPrgOTime() * 60) ?>"
                                            id="time1"
                                            name="oTime"
                                    ></td>
                                <td><?= $mainProgram->getPrgOValue() ?></td>
                                <td></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="portlet box yellow-saffron">
                <div class="portlet-title">
                    <div class="caption">
                        Detale
                    </div>
                </div>
                <div class="portlet-body">
                    <div class="table-scrollable">
                        <table class="table table-striped table-hover">
                            <thead>
                            <tr>
                                <th>LP</th>
                                <th>Nazwa</th>
                                <th>Ilość</th>
                                <th>Rozmiar</th>
                                <th>RECT</th>
                                <th>RECT W</th>
                                <th>RECT WO</th>
                                <th>Waga [kg]</th>
                                <th>Single time</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $lp = 0;
                            ?>
                            <?php foreach ($mainProgram->getParts() as $part): ?>
                                <?php
                                $lp++
                                ?>
                                <tr>
                                    <td><?= $lp ?></td>
                                    <td><?= $part->getPartName() ?></td>
                                    <td><?= $part->getPartCount() ?></td>
                                    <td><?= $part->getUnfoldXSize() ?> x <?= $part->getUnfoldYSize() ?></td>
                                    <td><?= $part->getRectangleArea() ?></td>
                                    <td><?= $part->getRectangleAreaW() ?></td>
                                    <td><?= $part->getRectangleAreaWO() ?></td>
                                    <td><?= $part->getWeight() / 1000 ?></td>
                                    <td><?= $part->getPrgDetSingleTime() ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="portlet box blue-chambray">
                <div class="portlet-title">
                    <div class="caption">
                        Materiał
                    </div>
                </div>
                <div class="portlet-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="portlet box blue-dark">
                                <div class="portlet-title">
                                    <div class="caption">
                                        Główne
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <div class="table-scrollable">
                                        <table class="table">
                                            <thead>
                                            <tr>
                                                <td>Powierzchnia arkusza</td>
                                                <td>zł/kg</td>
                                                <td>zł/arkusz</td>
                                                <td>Waga</td>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr>
                                                <td><?= $mainMaterial->getSheetSize() ?></td>
                                                <td><?= round($mainMaterial->getPrgSheetPriceKg(), 2) ?></td>
                                                <td>
                                                    <input
                                                            class="form-control"
                                                            name="prgSheetPrice"
                                                            value="<?= $mainMaterial->getPrgSheetPrice() ?>"
                                                    >
                                                </td>
                                                <td><?= $mainMaterial->getPrgSheetAllWeight() ?></td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="portlet box blue-hoki">
                <div class="portlet-title">
                    <div class="caption">
                        Kosz cięcia
                    </div>
                </div>
                <div class="portlet-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="portlet box blue-madison">
                                <div class="portlet-title">
                                    <div class="caption">
                                        Komplet
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <div class="table-scrollable">
                                        <table class="table">
                                            <thead>
                                            <tr>
                                                <td>Czas cięcia</td>
                                                <td>Cena cięcia</td>
                                                <td>Koszt cięcia</td>
                                                <td>Koszt cięcia + przeladowanie</td>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr>
                                                <td><?= $mainProgram->getPreTime() ?></td>
                                                <td><input class="form-control"
                                                           value="<?= round($mainProgram->getPrgMinPrice(), 2) ?>"
                                                           name="prgMinPrice"
                                                    ></td>
                                                <td><?= round($mainProgram->getCleanCutAll(), 2) ?></td>
                                                <td><?= round($mainProgram->getCutAll(), 2) ?></td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="portlet box green-sharp">
                                <div class="portlet-title">
                                    <div class="caption">
                                        Detale
                                    </div>
                                </div>
                                <div class="portlet-body">
                                    <div class="table-scrollable">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                            <tr>
                                                <td><b>LP</b></td>
                                                <td><b>Nazwa</b></td>
                                                <td><b>Ciecie</b></td>
                                                <td><b>Ciecie</b></td>
                                                <td><b>Cięcie all netto</b></td>
                                                <td><b>Ilość sztuk</b></td>
                                                <td><b>Cena kg</b></td>
                                                <td><b>Cena ostateczna</b></td>
                                            </tr>
                                            <tr>
                                                <td></td>
                                                <td></td>
                                                <td><i>[zł/kom]</i></td>
                                                <td><i>[zł/szt]</i></td>
                                                <td><i>[zł]</i></td>
                                                <td><i>[szt]</i></td>
                                                <td><i>[zł/kg]</i></td>
                                                <td><i>[zł/szt]</i></td>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $lp = 0;
                                            ?>
                                            <?php foreach ($mainProgram->getParts() as $part): ?>
                                                <?php
                                                $lp++;
                                                ?>
                                                <tr>
                                                    <td><?= $lp ?></td>
                                                    <td><?= $part->getPartName() ?></td>
                                                    <td><?= round($part->getComplAllPrice(), 2) ?></td>
                                                    <td><?= round($part->getDetailCut(), 2) ?></td>
                                                    <td><?= round($part->getComplAllPrice() * $mainProgram->getSheetCount(), 2) ?></td>
                                                    <td><?= $part->getAllSheetQty() ?></td>
                                                    <td><?= round($part->getPriceKg(), 2) ?></td>
                                                    <td><?= round($part->getLastPrice(), 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-2 col-lg-offset-10">
            <button class="btn btn-info" type="submit">Licz</button>
            <a class="btn btn-success" href="javascript:;" id="saveProgram">Zapisz</a>
        </div>
    </div>
</form>

<script type="text/javascript" src="/js/plateMultiPart/programCard.js"></script>