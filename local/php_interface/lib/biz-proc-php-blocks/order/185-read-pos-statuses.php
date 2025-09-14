<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Читаем статусы товарных позиций

$orderId = '{{ID}}';

$rsData = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_POSTAVSHCHIKU' => $orderId],
    false,
    false,
    ['PROPERTY_OTGRUZHENO', 'PROPERTY_JSON']
);

$hasAvailablePosition = 0;
while ($arItem = $rsData->fetch()) {
    $array = json_decode($arItem['PROPERTY_JSON_VALUE'], true);

    if (array_key_exists($orderId, $array)) {
        $shipQty = 0;
        foreach ($array[$orderId]['D'] as $item) {
            $shipQty += $item['Q'];
        }

        if ($shipQty < $array[$orderId]['Q']) {
            $hasAvailablePosition = 1;
            break;
        }
    }
}

$this->SetVariable('hasAvailablePosition', $hasAvailablePosition);
