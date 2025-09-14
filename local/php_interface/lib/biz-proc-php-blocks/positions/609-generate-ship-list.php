<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Формируем список доставок

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$deliveryList = [];
$qty = 0;
foreach (json_decode('{{JSON}}', true) as $orderItem) {
    foreach ($orderItem['D'] as $value) {
        if (!array_key_exists($value['ID'], $deliveryList)) {
            $deliveryList[$value['ID']] = $value['Q'];
        }
        $qty += $value['Q'];
    }
}

$instance = Container::getInstance();
$factory = $instance->getFactory(149);

$html = '';
foreach ($deliveryList as $id => $q) {
    $title = $id;
    foreach ($factory->getItems(['filter' => ['ID' => $id]]) as $rsItem) {
        $title = $rsItem->getData()['TITLE'];
    }
    $html .= '<a href="https://ipvartanyan.ru/page/dostavki/dostavka/type/149/details/'
        . $id . '/">' . $title . '</a> (ID ' . $id . ') - ' . $q . '<br>';
}

CIBlockElement::SetPropertyValues('{{ID элемента}}', 20, $qty, 'OTGRUZHENO');
CIBlockElement::SetPropertyValues('{{ID элемента}}', 20, $html, 'DELIVERY_HTML');
