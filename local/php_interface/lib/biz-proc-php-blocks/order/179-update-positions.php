<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Обновляем товарные позиции

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$orderId = '{{ID}}';

$rsElementList = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_POSTAVSHCHIKU' => $orderId],
    false,
    false,
    ['ID', 'PROPERTY_ID_TOVARA', 'PROPERTY_KOLICHESTVO', 'PROPERTY_PRODAZHA', 'PROPERTY_JSON',
        'PROPERTY_ZAKAZANO_U_POSTAVSHCHIKA']
);

$arPositions = [];
while ($item = $rsElementList->fetch()) {
    $array = json_decode($item['PROPERTY_JSON_VALUE'], true);

    $arPositions[] = [
        'ID'                       => $item['ID'],
        'ID_TOVARA'                => $item['PROPERTY_ID_TOVARA_VALUE'],
        'KOLICHESTVO'              => $item['PROPERTY_KOLICHESTVO_VALUE'],
        'ZAKAZANO_U_POSTAVSHCHIKA' => $item['PROPERTY_ZAKAZANO_U_POSTAVSHCHIKA_VALUE'],
        'PRODAZHA'                 => $item['PROPERTY_PRODAZHA_VALUE'],
        'JSON'                     => $array
    ];
}

$arRows = CAllCrmProductRow::LoadRows('Ta2', $orderId);

$arPositionForDelete = $positionIds = [];
foreach ($arPositions as $position) {
    $isDeleted = true;

    foreach ($arRows as $key => $product) {
        if ($position['ID_TOVARA'] == $product['PRODUCT_ID']) {
            $isDeleted = false;

            if ($position['PRODAZHA'] == 1) {
                if ($product['QUANTITY'] < $position['JSON'][$orderId]['Q']) {
                    $position['JSON'][$orderId]['Q'] = $product['QUANTITY'];

                    $quantity = 0;
                    foreach ($position['JSON'] as $item) {
                        $quantity += $item['Q'];
                    }

                    CIBlockElement::SetPropertyValues($position['ID'], 20, $quantity, 'ZAKAZANO_U_POSTAVSHCHIKA');
                } elseif ($product['QUANTITY'] > $position['JSON'][$orderId]['Q']) {
                    if ($product['QUANTITY'] - $position['JSON'][$orderId]['Q'] + $position['ZAKAZANO_U_POSTAVSHCHIKA'] <= $position['KOLICHESTVO']) {
                        $position['JSON'][$orderId]['Q'] = $product['QUANTITY'];

                        $quantity = 0;
                        foreach ($position['JSON'] as $item) {
                            $quantity += $item['Q'];
                        }

                        CIBlockElement::SetPropertyValues($position['ID'], 20, $quantity, 'ZAKAZANO_U_POSTAVSHCHIKA');
                    } else {
                        $qty = $position['KOLICHESTVO'] - $position['ZAKAZANO_U_POSTAVSHCHIKA'] + $position['JSON'][$orderId]['Q'];
                        $position['JSON'][$orderId]['Q'] = $arRows[$key]['QUANTITY'] = $qty;
                        CIBlockElement::SetPropertyValues($position['ID'], 20, $position['KOLICHESTVO'], 'ZAKAZANO_U_POSTAVSHCHIKA');
                    }
                }
            } else {
                CIBlockElement::SetPropertyValues($position['ID'], 20, $product['QUANTITY'], 'KOLICHESTVO');
                CIBlockElement::SetPropertyValues($position['ID'], 20, $product['QUANTITY'], 'ZAKAZANO_U_POSTAVSHCHIKA');

                $position['JSON'][$orderId]['Q'] = $product['QUANTITY'];
            }
            CIBlockElement::SetPropertyValues($position['ID'], 20, json_encode($position['JSON']), 'JSON');
        }
    }

    if ($isDeleted) {
        if ($position['PRODAZHA'] == 1) {
            $orderIds = [];
            $rsElementList = CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => 20, 'ID' => $position['ID']],
                false,
                false,
                ['PROPERTY_ZAKAZ_POSTAVSHCHIKU']
            );
            while ($item = $rsElementList->fetch()) {
                if ($item['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE'] == $orderId) { continue; }

                $orderIds[] = $item['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE'];
            }

            CIBlockElement::SetPropertyValues($position['ID'], 20, $orderIds, 'ZAKAZ_POSTAVSHCHIKU');

            $quantity = 0;
            foreach ($position['JSON'] as $id => $item) {
                if ($id == $orderId) {
                    unset($position['JSON'][$id]);
                } else {
                    $quantity += $item['Q'];
                }
            }

            CIBlockElement::SetPropertyValues($position['ID'], 20, json_encode($position['JSON']), 'JSON');
            CIBlockElement::SetPropertyValues($position['ID'], 20, $quantity, 'ZAKAZANO_U_POSTAVSHCHIKA');
        } else {
            $arPositionForDelete[] = $position['ID'];
        }
    }

    if (!in_array($position['ID'], $positionIds)) {
        $positionIds[] = $position['ID'];
    }
}

$this->SetVariable('arPositionForDelete', $arPositionForDelete);

CCrmProductRow::SaveRows('Ta2', $orderId, $arRows);

foreach ($arRows as $key => $product) {
    $havePosition = false;

    foreach ($arPositions as $position) {
        if ($position['ID_TOVARA'] == $product['PRODUCT_ID']) {
            $havePosition = true;
            break;
        }
    }

    if (!$havePosition) {
        $array = [$orderId => ['Q' => $product['QUANTITY'], 'D' => []]];

        $position = new CIBlockElement;
        $positionFields = [
            'IBLOCK_ID' => 20,
            'ACTIVE' => 'Y',
            'NAME' => $product['PRODUCT_NAME'],
            'PROPERTY_VALUES' => [
                'ID_TOVARA' => $product['PRODUCT_ID'],
                'KOLICHESTVO' => 0,
                'ZAKAZ_POSTAVSHCHIKU' => [$orderId],
                'STATUS' => 'Запуск',
                'ZAKUPKA' => 1,
                'PRODAZHA' => 1,
                'JSON' => json_encode($array),
                'ZAKAZANO_U_POSTAVSHCHIKA' => $product['QUANTITY']
            ]
        ];
        $positionId = $position->Add($positionFields);
        $positionIds[] = $positionId;
    }
}

$this->SetVariable('positionIds', $positionIds);
