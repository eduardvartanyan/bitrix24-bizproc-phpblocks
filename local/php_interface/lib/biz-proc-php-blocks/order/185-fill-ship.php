<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Привязываем к доставке товарные позиции из заказа поставщику

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

$orderId       = '{{ID}}';
$newDeliveryId = '{=A74197_44486_47102_59035:ItemId}';
$positionList  = $availableProducts = [];

$positionsData = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_POSTAVSHCHIKU' => $orderId],
    false,
    false,
    ['ID', 'PROPERTY_KOLICHESTVO', 'PROPERTY_OTGRUZHENO', 'PROPERTY_KOLICHESTVO', 'PROPERTY_JSON', 'PROPERTY_ID_TOVARA']
);

while ($arElement = $positionsData->fetch()) {
    $array = json_decode($arElement['PROPERTY_JSON_VALUE'], true);

    if (array_key_exists($orderId, $array)) {
        $shipQuantity = 0;
        foreach ($array[$orderId]['D'] as $item) {
            $shipQuantity += $item['Q'];
        }

        if ($shipQuantity < $array[$orderId]['Q']) {
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

            CIBlockElement::SetPropertyValues($arElement['ID'], 20, $deliveryList, 'DOSTAVKA');

            $array[$orderId]['D'][] = [
                'ID' => $newDeliveryId,
                'Q' => $array[$orderId]['Q'] - $shipQuantity,
                'PTU' => '',
                'RTU' => ''
            ];

            CIBlockElement::SetPropertyValues($arElement['ID'], 20, json_encode($array), 'JSON');

            $positionList[] = $arElement['ID'];
            $availableProducts[$arElement['PROPERTY_ID_TOVARA_VALUE']] = $array[$orderId]['Q'] - $shipQuantity;
        }
    }
}

$this->SetVariable('positionList', $positionList);

if (!$availableProducts) { return; }

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$products = [];
foreach (CAllCrmProductRow::LoadRows('Ta2', $orderId) as $product) {
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
