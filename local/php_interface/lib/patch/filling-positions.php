<?php require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Crm\DealTable;
use Bitrix\Crm\Service\Container;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

const DOMAIN = 'https://ipvartanyan.ru';

$instance     = Container::getInstance();
$orderFactory = $instance->getFactory(162);
$shipFactory  = $instance->getFactory(149);

$obPositions = CIBlockElement::GetList(
    [],
    ['PROPERTY_JSON' => false],
    false,
    false,
    ['ID', 'PROPERTY_KOLICHESTVO', 'PROPERTY_ZAKAZ_KLIENTA', 'PROPERTY_ZAKAZ_POSTAVSHCHIKU', 'PROPERTY_DOSTAVKA',
        'PROPERTY_PRIOBRETENIE', 'PROPERTY_REALIZATSIYA']
);
while ($pos = $obPositions->Fetch()) {
    $id  = $pos['ID'];
    $qty = $pos['PROPERTY_KOLICHESTVO_VALUE'];

    if ($pos['PROPERTY_ZAKAZ_KLIENTA_VALUE']) {
        try {
            $obDeal = DealTable::getById($pos['PROPERTY_ZAKAZ_KLIENTA_VALUE']);
            if ($deal = $obDeal->fetch()) {
                $html = '<a href="' . DOMAIN . '/crm/deal/details/' . $deal['ID'] . '/">'
                    . $deal['TITLE'] . '</a> (ID ' . $deal['ID'] . ')<br><br>';
                CIBlockElement::SetPropertyValues($id, 20, $html, 'SPISOK_ZAKAZOV_KLIENTOV');
            }
        } catch (ObjectPropertyException|ArgumentException|SystemException $e) {
            echo 'Не удалось получить сделку: ' . $e->getMessage();
        }
    }

    if ($pos['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE'] || $pos['PROPERTY_DOSTAVKA_VALUE']) {
        $orderId = $pos['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE'] ?? 0;

        $array[$orderId] = [
            'Q' => $qty,
            'D' => []
        ];

        if ($pos['PROPERTY_DOSTAVKA_VALUE']) {
            $array[$pos['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE']]['D'][] = [
                'ID'  => $pos['PROPERTY_DOSTAVKA_VALUE'],
                'Q'   => $qty,
                'PTU' => $pos['PROPERTY_PRIOBRETENIE_VALUE'] ?? '',
                'RTU' => $pos['PROPERTY_REALIZATSIYA_VALUE'] ?? ''
            ];
        }
        $json = json_encode($array);
        CIBlockElement::SetPropertyValues($id, 20, $json, 'JSON');
    }

    if ($pos['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE']) {
        if ($obOrder = $orderFactory->getItem($pos['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE'])) {
            if ($order = $obOrder->getData()) {
                $html = '<a href="' . DOMAIN . '/page/zakazy_postavshchiku/zakaz_postavshchiku/type/162/details/' . $order['ID'] . '/">'
                    . $order['TITLE'] . '</a> (ID ' . $order['ID'] . ') - ' . $qty . ' - ' . $qty . '<br><br>';
                CIBlockElement::SetPropertyValues($id, 20, $html, 'ORDER_HTML');
            }
        }
    }

    if ($pos['PROPERTY_DOSTAVKA_VALUE']) {
        if ($obShip = $shipFactory->getItem($pos['PROPERTY_DOSTAVKA_VALUE'])) {
            if ($ship = $obShip->getData()) {
                $html = '<a href="' . DOMAIN . '/page/dostavki/dostavka/type/149/details/' .
                    $ship['ID'] . '/">' . $ship['TITLE'] . '</a> (ID ' . $ship['ID'] . ') - ' . $qty . '<br>';
                CIBlockElement::SetPropertyValues($id, 20, $html, 'PROPERTY_DELIVERY_HTML');
            }
        }
    }

    $log = 'Заполнена позиция ID ' . $id . PHP_EOL;
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/filling-positions.log', $log, FILE_APPEND);
}
