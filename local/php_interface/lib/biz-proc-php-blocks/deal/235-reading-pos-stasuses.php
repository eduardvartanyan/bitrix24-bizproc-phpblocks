<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Читаем статусы товарных позиций

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$instance = Container::getInstance();
$factory = $instance->getFactory(162);

$obPositions = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_KLIENTA' => 3966],
    false,
    false,
    ['PROPERTY_STATUS', 'PROPERTY_ZAKAZ_POSTAVSHCHIKU','PROPERTY_PRIOBRETENIE', 'PROPERTY_PRODAZHA',
        'PROPERTY_ZAKAZANO_U_POSTAVSHCHIKA', 'PROPERTY_KOLICHESTVO', 'PROPERTY_ZAKUPKA']
);

$allDelivered = 1;
while ($arPosition = $obPositions->fetch()) {
    $ordersData = $factory->getItems([
        'filter' => ['ID' => $arPosition['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE']]
    ]);

    $orderStage = '';
    if (count($ordersData) > 0) {
        $order = $ordersData[0]->getData();
        $orderStage = $order['STAGE_ID'];
    }

    if (!(
        ($arPosition['PROPERTY_STATUS_VALUE'] == '✅ У заказчика' && $arPosition['PROPERTY_PRODAZHA_VALUE'] == 1)
        || ($arPosition['PROPERTY_PRIOBRETENIE_VALUE'] != '' && $arPosition['PROPERTY_PRODAZHA_VALUE'] == 0)
        || $orderStage == 'DT162_7:UC_ZAY9F3'
    )) {
        $allDelivered = 0;
    }
}

$this->SetVariable('allDelivered', $allDelivered);
