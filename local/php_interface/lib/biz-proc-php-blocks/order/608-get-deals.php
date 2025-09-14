<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Получаем связанные сделки

$obPositions = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_POSTAVSHCHIKU' => '{{ID}}', '!PROPERTY_ZAKAZ_KLIENTA' => false],
    false,
    false,
    ['PROPERTY_ZAKAZ_KLIENTA']
);

$dealIds = [];
while ($arElement = $obPositions->fetch()) {
    if (!in_array($arElement['PROPERTY_ZAKAZ_KLIENTA_VALUE'], $dealIds)) {
        $dealIds[] = $arElement['PROPERTY_ZAKAZ_KLIENTA_VALUE'];
    }
}

$this->SetVariable('dealIds', $dealIds);
