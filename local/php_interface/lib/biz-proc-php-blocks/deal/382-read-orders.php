<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Читаем заказы поставщикам

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$factory = Container::getInstance()->getFactory(162);

$rsElements = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_KLIENTA' => '{{ID}}', 'PROPERTY_PRODAZHA' => '0'],
    false,
    false,
    ['PROPERTY_ZAKAZ_POSTAVSHCHIKU']
);

$supplierIds = [];
while ($arElement = $rsElements->fetch()) {
    $rsItems = $factory->getItems(['filter' => ['ID' => $arElement['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE']]]);

    foreach ($rsItems as $rsItem) {
        $arItem = $rsItem->getData();

        if (in_array($arItem['COMPANY_ID'], $supplierIds)) { continue; }

        $supplierIds[] = $arItem['COMPANY_ID'];
    }
}

$this->SetVariable('suppliers', $supplierIds);
