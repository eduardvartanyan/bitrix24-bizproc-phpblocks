<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Формируем список заказов клиентов

use Bitrix\Crm\DealTable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

const DOMAIN = 'https://crm.a9systems.ru';

$obElementList = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'ID' => '{{ID элемента}}'],
    false,
    false,
    ['PROPERTY_ZAKAZ_KLIENTA']
);

$html = '';
while ($arElement = $obElementList->fetch()) {
    $arDeal = DealTable::getById($arElement['PROPERTY_ZAKAZ_KLIENTA_VALUE'])->fetch();
    $html .= '<a href="' . DOMAIN . '/crm/deal/details/' . $arElement['PROPERTY_ZAKAZ_KLIENTA_VALUE'] . '/">'
        . $arDeal['TITLE'] . '</a> (ID ' . $arElement['PROPERTY_ZAKAZ_KLIENTA_VALUE'] . ')<br><br>';
}

$this->SetVariable('html', $html);
