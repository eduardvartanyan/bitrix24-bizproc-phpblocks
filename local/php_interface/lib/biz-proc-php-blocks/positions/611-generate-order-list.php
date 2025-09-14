<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Формируем список заказов поставщикам

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

const DOMAIN = 'https://crm.a9systems.ru';

$instance = Container::getInstance();
$factory = $instance->getFactory(162);

$posId    = '{{ID элемента}}';
$html     = '';
$orderQty = 0;

foreach (json_decode('{{JSON}}', true) as $id => $orderItem) {
    if ($id != 0) {
        $deliveryQty = 0;
        foreach ($orderItem['D'] as $item) {
            $deliveryQty += $item['Q'];
        }

        $title = $id;
        foreach ($factory->getItems(['filter' => ['ID' => $id]]) as $rsItem) {
            $title = $rsItem->getData()['TITLE'];
        }

        $html .= '<a href="' . DOMAIN . '/page/zakazy_postavshchiku/zakaz_postavshchiku/type/162/details/' . $id . '/"';
        if ($orderItem['Q'] > $deliveryQty) {
            $html .= ' style="background: #fdfdae;"';
        }
        $html .= '>' . $title . '</a> (ID ' . $id . ') - ' . $orderItem['Q'] . ' - ' . $deliveryQty . '<br><br>';

        $orderQty += $orderItem['Q'];
    }
}

CIBlockElement::SetPropertyValues($posId, 20, $html, 'ORDER_HTML');
CIBlockElement::SetPropertyValues($posId, 20, $orderQty, 'ZAKAZANO_U_POSTAVSHCHIKA');
