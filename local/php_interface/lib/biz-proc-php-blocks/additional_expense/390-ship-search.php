<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Ищем доставку

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$analStr = 'S_3964';

$shipId = '';
if (strpos($analStr, 'S_') !== false) {
    $dealId = str_replace('S_', '', $analStr);
    $obDealPositions = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_KLIENTA' => $dealId],
        false,
        false,
        ['PROPERTY_DOSTAVKA']
    );
    while ($pos = $obDealPositions->fetch()) {
        if ($shipId) { break; }
        $shipId = $pos['PROPERTY_DOSTAVKA_VALUE'];
    }
} else {
    $ptuId = $analStr;
    $obDealPositions = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => 20, 'PROPERTY_PRIOBRETENIE' => $ptuId],
        false,
        false,
        ['PROPERTY_DOSTAVKA']
    );
    while ($pos = $obDealPositions->fetch()) {
        if ($shipId) { break; }
        $shipId = $pos['PROPERTY_DOSTAVKA_VALUE'];
    }
}

if ($shipId) {
    $this->SetVariable('shipId', $shipId);
}
