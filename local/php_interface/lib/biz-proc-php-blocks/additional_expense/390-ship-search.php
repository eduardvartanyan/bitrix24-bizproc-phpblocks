<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Ищем доставку

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$analStr = '{{Аналитика}}';

$shipId = '';
$arFilter = ['IBLOCK_ID' => 20];
if (strpos($analStr, 'S_') !== false) {
    $dealId = str_replace('S_', '', $analStr);
    $arFilter['PROPERTY_ZAKAZ_KLIENTA'] = $dealId;
} else {
    $ptuId = $analStr;
    $arFilter['PROPERTY_PRIOBRETENIE'] = $ptuId;
}

$obDealPositions = CIBlockElement::GetList([], $arFilter, false, false, ['PROPERTY_DOSTAVKA']);
while ($pos = $obDealPositions->fetch()) {
    if ($shipId) { break; }
    $shipId = $pos['PROPERTY_DOSTAVKA_VALUE'];
}

if ($shipId) {
    $this->SetVariable('shipId', $shipId);
}
