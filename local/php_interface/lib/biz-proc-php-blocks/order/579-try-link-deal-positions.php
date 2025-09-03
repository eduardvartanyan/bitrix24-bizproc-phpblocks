<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Пытаемся привязать товарные позиции сделки

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$dealId  = (int) '{{Основание}}';
$orderId = (int) '{{ID}}';
if ($dealId === 0 || $orderId === 0) { return; }

$dealProdRows = [];
foreach (CAllCrmProductRow::LoadRows('D', $dealId) as $row) {
    $dealProdRows[$row['PRODUCT_ID']] = $row;
}

$posIds = [];
foreach (CAllCrmProductRow::LoadRows('Ta2', $orderId) as $orderProdRow) {
    if (!in_array($orderProdRow['PRODUCT_ID'], array_keys($dealProdRows))) { continue; }

    $obDealProdPositions = CIBlockElement::GetList(
        ['PROPERTY_KOLICHESTVO' => 'ASC'],
        [
            'IBLOCK_ID'              => 20,
            'PROPERTY_ID_TOVARA'     => $orderProdRow['PRODUCT_ID'],
            'PROPERTY_ZAKAZ_KLIENTA' => $dealId
        ],
        false,
        false,
        ['ID', 'PROPERTY_JSON', 'PROPERTY_KOLICHESTVO']
    );
    while ($dealProdPos = $obDealProdPositions->Fetch()) {
        if ($dealProdPos['PROPERTY_KOLICHESTVO_VALUE'] < $orderProdRow['QUANTITY']) { continue; }

        $orderIds = [];
        if (empty($dealProdPos['PROPERTY_JSON_VALUE'])) {
            $posArray = [$orderId => ['Q' => $orderProdRow['QUANTITY'], 'D' => []]];
            $orderIds = [$orderId];
        } else {
            $posArray = json_decode($dealProdPos['PROPERTY_JSON_VALUE'], true);
            $qty = array_sum(array_column($posArray, 'Q'));

            if ($dealProdPos['PROPERTY_KOLICHESTVO_VALUE'] - $qty >= $orderProdRow['QUANTITY']) {
                $posArray[$orderId] = ['Q' => $orderProdRow['QUANTITY'], 'D' => []];
                $orderIds = array_keys($posArray);
            } else { continue; }
        }

        $posJson = json_encode($posArray, JSON_UNESCAPED_UNICODE);
        CIBlockElement::SetPropertyValues($dealProdPos['ID'], 20, $posJson, 'JSON');
        CIBlockElement::SetPropertyValues($dealProdPos['ID'], 20, $orderIds, 'ZAKAZ_POSTAVSHCHIKU');

        $posIds[] = $dealProdPos['ID'];
    }
}

$this->SetVariable('positionIds', $posIds);
