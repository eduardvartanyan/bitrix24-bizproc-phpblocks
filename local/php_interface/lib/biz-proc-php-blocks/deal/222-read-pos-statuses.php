<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Читаем статусы товарных позиций

$rsData = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_KLIENTA' => '{{ID}}'],
    false,
    false,
    ['PROPERTY_KOLICHESTVO', 'PROPERTY_OTGRUZHENO']
);

$hasAvailablePosition = 0;
while ($arItem = $rsData->fetch()) {
    if ($arItem['PROPERTY_OTGRUZHENO_VALUE'] < $arItem['PROPERTY_KOLICHESTVO_VALUE']) {
        $hasAvailablePosition = 1;
    }
}

$this->SetVariable('hasDealAvailablePosition', $hasAvailablePosition);
