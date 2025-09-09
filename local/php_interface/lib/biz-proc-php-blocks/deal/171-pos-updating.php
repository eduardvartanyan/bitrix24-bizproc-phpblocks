<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Обновляем товарные позиции

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$dealId = '{{ID}}';

$rsElements = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_KLIENTA' => $dealId, 'PROPERTY_PRODAZHA' => 1],
    false,
    false,
    ['ID', 'PROPERTY_ID_TOVARA', 'PROPERTY_KOLICHESTVO', 'PROPERTY_ZAKAZ_POSTAVSHCHIKU', 'PROPERTY_DOSTAVKA',
        'PROPERTY_PRIOBRETENIE', 'PROPERTY_REALIZATSIYA', 'PROPERTY_ZAKAZANO_U_POSTAVSHCHIKA', 'PROPERTY_OTGRUZHENO',
        'PROPERTY_JSON']
);

$dealPositions = $positionIds = [];
while ($arElement = $rsElements->fetch()) {
    $qty = $arElement['PROPERTY_KOLICHESTVO_VALUE'];
    $array = json_decode($arElement['PROPERTY_JSON_VALUE'], true) ?? [];

    foreach ($array as $id => $orderItem) {
        if (!array_key_exists('S', $orderItem)) { continue; }

        foreach ($orderItem['S'] as $item) {
            if ($item['ID'] == $dealId) {
                $qty = $item['Q'];
                break;
            } else {
                $qty -= $item['Q'];
            }
        }
    }

    if (array_key_exists($arElement['ID'], $dealPositions)) {
        if ($arElement['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE']) {
            $dealPositions[$arElement['ID']]['ZAKAZ_POSTAVSHCHIKU'][] = $arElement['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE'];
        }
        if ($arElement['PROPERTY_DOSTAVKA_VALUE']) {
            $dealPositions[$arElement['ID']]['DOSTAVKA'][] = $arElement['PROPERTY_DOSTAVKA_VALUE'];
        }
        if ($arElement['PROPERTY_PRIOBRETENIE_VALUE']) {
            $dealPositions[$arElement['ID']]['PRIOBRETENIE'][] = $arElement['PROPERTY_PRIOBRETENIE_VALUE'];
        }
        if ($arElement['PROPERTY_REALIZATSIYA_VALUE']) {
            $dealPositions[$arElement['ID']]['REALIZATSIYA'][] = $arElement['PROPERTY_REALIZATSIYA_VALUE'];
        }
    } else {
        $dealPositions[$arElement['ID']] = [
            'ID'                       => $arElement['ID'],
            'ID_TOVARA'                => $arElement['PROPERTY_ID_TOVARA_VALUE'],
            'KOLICHESTVO'              => $arElement['PROPERTY_KOLICHESTVO_VALUE'],
            'ZAKAZ_POSTAVSHCHIKU'      => $arElement['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE'] ? [$arElement['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE']] : [],
            'DOSTAVKA'                 => $arElement['PROPERTY_DOSTAVKA_VALUE'] ? [$arElement['PROPERTY_DOSTAVKA_VALUE']] : [],
            'PRIOBRETENIE'             => $arElement['PROPERTY_PRIOBRETENIE_VALUE'] ? [$arElement['PROPERTY_PRIOBRETENIE_VALUE']] : [],
            'REALIZATSIYA'             => $arElement['PROPERTY_REALIZATSIYA_VALUE'] ? [$arElement['PROPERTY_REALIZATSIYA_VALUE']] : [],
            'ZAKAZANO_U_POSTAVSHCHIKA' => $arElement['PROPERTY_ZAKAZANO_U_POSTAVSHCHIKA_VALUE'],
            'OTGRUZHENO'               => $arElement['PROPERTY_OTGRUZHENO_VALUE'],
            'JSON'                     => $array
        ];
    }

    $positionIds[] = $arElement['ID'];
}

$array = $dealPositions;
$dealPositions = [];
foreach ($array as $position) {
    $dealPositions[] = $position;
}

$dealProducts = CAllCrmProductRow::LoadRows('D', $dealId);

$positionsForDelete = [];
$updateProducts = false;
for ($i = 0; $i < count($dealPositions); ++$i) {
    $deletePosition = true;
    foreach ($dealProducts as $key => &$product) {
        if ($dealPositions[$i]['ID_TOVARA'] == $product['PRODUCT_ID']) {
            $deletePosition = false;
            $qty = $product['QUANTITY'];
            foreach ($dealPositions[$i]['JSON'] as $id => $orderItem) {
                if (array_key_exists('S', $orderItem)) {
                    foreach ($orderItem['S'] as $key => $item) {
                        if ($item['ID'] != $dealId) {
                            $qty += $item['Q'];
                        } else {
                            $dealPositions[$i]['JSON'][$id]['S'][$key]['Q'] = $product['QUANTITY'];
                            CIBlockElement::SetPropertyValues($dealPositions[$i]['ID'], 20, json_encode($dealPositions[$i]['JSON']), 'JSON');
                        }
                    }
                }
            }

            CIBlockElement::SetPropertyValues($dealPositions[$i]['ID'], 20, $qty, 'KOLICHESTVO');
        } else {
            $obElementList = CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => 20, 'ID' => $dealPositions[$i]['ID']],
                false,
                false,
                ['PROPERTY_ZAKAZ_KLIENTA']
            );

            $dealIds = [];
            while ($arElement = $obElementList->fetch()) {
                if ($arElement['PROPERTY_ZAKAZ_KLIENTA_VALUE'] == $dealId) { continue; }
                $dealIds[] = $arElement['PROPERTY_ZAKAZ_KLIENTA_VALUE'];
            }

            if (count($dealIds) > 0) {
                $deletePosition = false;

                $qty = $dealPositions[$i]['KOLICHESTVO'];
                $qty1 = 0;
                foreach ($dealPositions[$i]['JSON'] as $id => $orderItem) {
                    if (array_key_exists('S', $orderItem)) {
                        foreach ($orderItem['S'] as $key => $item) {
                            if ($item['ID'] == $dealId) {
                                unset($dealPositions[$i]['JSON'][$id]['S'][$key]);
                                CIBlockElement::SetPropertyValues($dealPositions[$i]['ID'], 20, json_encode($dealPositions[$i]['JSON']), 'JSON');
                                $qty1 = $qty - $item['Q'];
                                break;
                            } else {
                                $qty1 += $item['Q'];
                            }
                        }
                    }
                }

                CIBlockElement::SetPropertyValues($dealPositions[$i]['ID'], 20, $dealIds, 'ZAKAZ_KLIENTA');
                CIBlockElement::SetPropertyValues($dealPositions[$i]['ID'], 20, $qty1, 'KOLICHESTVO');
            }
        }
    }

    if ((!$deletePosition) && ($i > 0)) {
        for ($j = 0; $j < $i; ++$j) {
            if (
                $dealPositions[$i]['ID_TOVARA'] == $dealPositions[$j]['ID_TOVARA']
                && count($dealPositions[$i]['ZAKAZ_POSTAVSHCHIKU']) == 0 && count($dealPositions[$i]['DOSTAVKA']) == 0
                && count($dealPositions[$i]['PRIOBRETENIE']) == 0 && count($dealPositions[$i]['REALIZATSIYA']) == 0
            ) {
                $deletePosition = true;
            }
        }
    }

    if ($deletePosition) {
        $positionsForDelete[] = $dealPositions[$i]['ID'];
    }
}

$this->SetVariable('arPositionForDelete', $positionsForDelete);

if ($updateProducts) {
    CCrmProductRow::SaveRows('D', $dealId, $dealProducts);
}


$isPurchase = 1;
$dealData = CCrmDeal::GetList([], ['ID' => $dealId], ['TYPE_ID']);
if ($arItem = $dealData->Fetch()) {
    if ($arItem['TYPE_ID'] == 'COMPLEX') {
        $isPurchase = 0;
    }
}

foreach (CAllCrmProductRow::LoadRows('D', $dealId) as $key => $product) {
    $havePosition = false;
    foreach ($dealPositions as $position) {
        if ($position['ID_TOVARA'] == $product['PRODUCT_ID']) {
            $havePosition = true;
        }
    }

    if ($havePosition) { continue; }

    $newPosition = new CIBlockElement;
    $props = [
        'ID_TOVARA'     => $product['PRODUCT_ID'],
        'KOLICHESTVO'   => $product['QUANTITY'],
        'ZAKAZ_KLIENTA' => $dealId,
        'ZAKUPKA'       => $isPurchase,
        'PRODAZHA'      => 1,
        'STATUS'        => 'Запуск'
    ];
    $arField = [
        'MODIFIED_BY'       => 1,
        'IBLOCK_SECTION_ID' => false,
        'IBLOCK_ID'         => 20,
        'NAME'              => $product['PRODUCT_NAME'],
        'ACTIVE'            => 'Y',
        'PROPERTY_VALUES'   => $props
    ];
    $newPositionId = $newPosition->Add($arField);

    $positionIds[] = $newPositionId;
}

$this->SetVariable('positionIds', $positionIds);
