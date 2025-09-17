<?php

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


const DOMAIN = 'https://crm.a9systems.ru/';

$instance     = Container::getInstance();
$orderFactory = $instance->getFactory(162);
$shipFactory  = $instance->getFactory(149);

$shipIds = [];
$obShips = $shipFactory->getItems(['filter' => ['!STAGE_ID' => 'DT149_10:SUCCESS']]);
foreach ($obShips as $obShip) {
    $ship = $obShip->getData();
    $shipIds[] = $ship['ID'];
}

foreach ($shipIds as $shipId) {
    $products = [];

    $obPositions = CIBlockElement::GetList(
        [],
        ['PROPERTY_ISTORIYA_DOSTAVOK' => $shipId],
        false,
        false,
        ['ID', 'PROPERTY_KOLICHESTVO', 'PROPERTY_ZAKAZ_KLIENTA', 'PROPERTY_ZAKAZ_POSTAVSHCHIKU', 'PROPERTY_DOSTAVKA',
            'PROPERTY_PRIOBRETENIE', 'PROPERTY_REALIZATSIYA', 'PROPERTY_ID_TOVARA', 'PROPERTY_OTGRUZHENO', 'NAME']
    );
    while ($pos = $obPositions->Fetch()) {
        $id = $pos['ID'];
        $qty = $pos['PROPERTY_KOLICHESTVO_VALUE'];

        $pos['PROPERTY_DOSTAVKA_VALUE'] = $shipId;
        CIBlockElement::SetPropertyValues($id, 20, $pos['PROPERTY_DOSTAVKA_VALUE'], 'DOSTAVKA');

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

        if ($pos['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE']) {
            $orderId = $pos['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE'] ?? 0;

            $array = [];
            $array[$orderId] = [
                'Q' => $qty,
                'D' => []
            ];

            $array[$pos['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE']]['D'][] = [
                'ID' => $pos['PROPERTY_DOSTAVKA_VALUE'],
                'Q' => $qty,
                'PTU' => $pos['PROPERTY_PRIOBRETENIE_VALUE'] ?? '',
                'RTU' => $pos['PROPERTY_REALIZATSIYA_VALUE'] ?? ''
            ];
            $json = json_encode($array);
            CIBlockElement::SetPropertyValues($id, 20, $json, 'JSON');

            if ($obOrder = $orderFactory->getItem($pos['PROPERTY_ZAKAZ_POSTAVSHCHIKU_VALUE'])) {
                if ($order = $obOrder->getData()) {
                    $html = '<a href="' . DOMAIN . '/page/zakazy_postavshchiku/zakaz_postavshchiku/type/162/details/' . $order['ID'] . '/">'
                        . $order['TITLE'] . '</a> (ID ' . $order['ID'] . ') - ' . $qty . ' - ' . $qty . '<br><br>';
                    CIBlockElement::SetPropertyValues($id, 20, $html, 'ORDER_HTML');
                    CIBlockElement::SetPropertyValues($id, 20, $qty, 'ZAKAZANO_U_POSTAVSHCHIKA');
                }
            }
        }

        if ($obShip = $shipFactory->getItem($pos['PROPERTY_DOSTAVKA_VALUE'])) {
            if ($ship = $obShip->getData()) {
                $html = '<a href="' . DOMAIN . '/page/dostavki/dostavka/type/149/details/' .
                    $ship['ID'] . '/">' . $ship['TITLE'] . '</a> (ID ' . $ship['ID'] . ') - ' . $qty . '<br>';
                $result = CIBlockElement::SetPropertyValues($id, 20, $html, 'DELIVERY_HTML');
                $result = CIBlockElement::SetPropertyValues($id, 20, $qty, 'OTGRUZHENO');

                $products[] = [
                    'PRODUCT_ID' =>  $pos['PROPERTY_ID_TOVARA_VALUE'],
                    'PRODUCT_NAME' => $pos['NAME'],
                    'QUANTITY' => $pos['PROPERTY_OTGRUZHENO_VALUE'],
                    'PRICE' => 0,
                    'MEASURE_CODE' => '796',
                    'MEASURE_NAME' => 'шт',
                    'TAX_RATE' => 0,
                    'TAX_INCLUDED' => 'N'
                ];
            }
        }
    }

    if (count(CAllCrmProductRow::LoadRows('T95', $shipId)) > 0) { continue; }

    CCrmProductRow::SaveRows('T95', $shipId, $products);

    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/filling-positions.log', $shipId);
}