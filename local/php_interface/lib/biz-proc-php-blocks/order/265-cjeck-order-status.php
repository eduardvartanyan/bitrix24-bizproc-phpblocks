<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Проверяем, заказ поставщику полностью отгружен или нет

$orderId = '{{ID}}';

$obElementList = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_POSTAVSHCHIKU' => $orderId],
    false,
    false,
    ['PROPERTY_JSON']
);

$isFullyShipped = 1;
while ($arElement = $obElementList->fetch()) {
    $array = json_decode($arElement['PROPERTY_JSON_VALUE'], true);

    if (!array_key_exists($orderId, $array)) { continue; }

    $shipQuantity = 0;
    foreach ($array[$orderId]['D'] as $item) {
        $shipQuantity += $item['Q'];
    }

    if ($shipQuantity < $array[$orderId]['Q']) {
        $isFullyShipped = 0;
    }
}

$this->SetVariable('isFullyShipped', $isFullyShipped);
