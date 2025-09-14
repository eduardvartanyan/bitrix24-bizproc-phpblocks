<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Пробуем привязать товарные позиции из связанной сделки

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$dealId  = '{{Основание}}';
$orderId = '{{ID}}';

$arOrderRows = CAllCrmProductRow::LoadRows('Ta2', $orderId);
$arDealRows = CAllCrmProductRow::LoadRows('D', $dealId);

$array = [];
foreach ($arDealRows as $arDealRow) {
    $array[$arDealRow['PRODUCT_ID']] = $arDealRow;
}
$arDealRows = $array;
unset($array);

$positionIds = [];
foreach ($arOrderRows as $arOrderRow) {
    if (!in_array($arOrderRow['PRODUCT_ID'], array_keys($arDealRows))) { continue; }

    $obElementList = CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => 20,
            'PROPERTY_ID_TOVARA' => $arOrderRow['PRODUCT_ID'],
            'PROPERTY_ZAKAZ_POSTAVSHCHIKU' => $orderId,
            'PROPERTY_KOLICHESTVO' => $arOrderRow['QUANTITY']
        ],
        false,
        false,
        ['ID']
    );
    if ($obElementList->Fetch()) { continue; }

    $obElementList = CIBlockElement::GetList(
        ['PROPERTY_KOLICHESTVO' => 'ASC'],
        [
            'IBLOCK_ID' => 20,
            'PROPERTY_ID_TOVARA' => $arOrderRow['PRODUCT_ID'],
            'PROPERTY_ZAKAZ_KLIENTA' => $dealId
        ],
        false,
        false,
        ['ID', 'PROPERTY_JSON', 'PROPERTY_KOLICHESTVO']
    );
    while ($arElement = $obElementList->Fetch()) {
        if ($arElement['PROPERTY_KOLICHESTVO_VALUE'] < $arOrderRow['QUANTITY']) { continue; }

        if (empty($arElement['PROPERTY_JSON_VALUE'])) {
            $array = [
                $orderId => [
                    'Q' => $arOrderRow['QUANTITY'],
                    'D' => []
                ]
            ];

            $orderIds = [$orderId];
        } else {
            $array = json_decode($arElement['PROPERTY_JSON_VALUE'], true);

            $qty = array_sum(array_column($array, 'Q'));

            if ($arElement['PROPERTY_KOLICHESTVO_VALUE'] - $qty >= $arOrderRow['QUANTITY']) {
                $array[$orderId] = [
                    'Q' => $arOrderRow['QUANTITY'],
                    'D' => []
                ];

                $orderIds = array_keys($array);
                $orderIds[] = $orderId;
            } else { continue; }
        }

        $json = json_encode($array, JSON_UNESCAPED_UNICODE);
        CIBlockElement::SetPropertyValues($arElement['ID'], 20, $json, 'JSON');
        CIBlockElement::SetPropertyValues($arElement['ID'], 20, $orderIds, 'ZAKAZ_POSTAVSHCHIKU');

        $positionIds[] = $arElement['ID'];
    }
}

$this->SetVariable('positionIds', $positionIds);
