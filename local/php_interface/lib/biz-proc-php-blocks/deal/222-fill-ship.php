<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Привязываем товарные позиции к доставке

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

$dealId        = '{{ID}}';
$newDeliveryId = '{=A28257_45623_74842_57595:ItemId}';
$positionList  = $availableProducts = [];

$positionsData = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_KLIENTA' => $dealId],
    false,
    false,
    ['ID', 'PROPERTY_KOLICHESTVO', 'PROPERTY_OTGRUZHENO', 'PROPERTY_KOLICHESTVO', 'PROPERTY_JSON', 'PROPERTY_ID_TOVARA']
);

while ($arElement = $positionsData->fetch()) {
    $array = json_decode($arElement['PROPERTY_JSON_VALUE'], true);

    $orderQuantity = 0;
    foreach ($array as $id => $orderItem) {
        if ($id == '0') {
            foreach ($orderItem['D'] as $item) {
                $orderQuantity += $item['Q'];
            }
        } else {
            $orderQuantity += $orderItem['Q'];
        }
    }

    $availableQty = $arElement['PROPERTY_KOLICHESTVO_VALUE'] - $orderQuantity;

    if ($availableQty <= 0) { continue; }

    $deliveryList = [];
    $deliveryData = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => 20, 'ID' => $arElement['ID']],
        false,
        false,
        ['PROPERTY_DOSTAVKA']
    );
    while ($arElement1 = $deliveryData->fetch()) {
        if (!$arElement1['PROPERTY_DOSTAVKA_VALUE']) { continue; }

        $deliveryList[] = $arElement1['PROPERTY_DOSTAVKA_VALUE'];
    }
    $deliveryList[] = $newDeliveryId;

    $array[0]['D'][] = [
        'ID'  => $newDeliveryId,
        'Q'   => $availableQty,
        'PTU' => '',
        'RTU' => ''
    ];

    CIBlockElement::SetPropertyValues($arElement['ID'], 20, $deliveryList, 'DOSTAVKA');
    CIBlockElement::SetPropertyValues($arElement['ID'], 20, json_encode($array), 'JSON');

    $positionList[] = $arElement['ID'];
    $availableProducts[$arElement['PROPERTY_ID_TOVARA_VALUE']] = $availableQty;
}

$this->SetVariable('positionList', $positionList);

if (!$availableProducts) { return; }

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$products = [];
foreach (CAllCrmProductRow::LoadRows('D', $dealId) as $product) {
    if (array_key_exists($product['PRODUCT_ID'], $availableProducts)) {
        $products[] = [
            'PRODUCT_ID'   =>  $product['PRODUCT_ID'],
            'PRODUCT_NAME' => $product['PRODUCT_NAME'],
            'QUANTITY'     => $availableProducts[$product['PRODUCT_ID']],
            'PRICE'        => $product['PRICE'],
            'MEASURE_CODE' => $product['MEASURE_CODE'],
            'MEASURE_NAME' => $product['MEASURE_NAME'],
            'TAX_RATE'     => $product['TAX_RATE'],
            'TAX_INCLUDED' => $product['TAX_INCLUDED']
        ];
    }
}

CCrmProductRow::SaveRows('T95', $newDeliveryId, $products);
