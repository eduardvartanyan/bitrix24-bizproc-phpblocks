<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Привязываем свободные товарные позиции к доставке

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use EVartanyan\BizProcHelper;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$orderId    = '{{ID}}';
$deliveryId = '{=Template:delivery}';

if (!BizProcHelper::hasOrderPositionForDelivery($orderId)) {
    $this->SetVariable('message', 'Нет неотгруженных товарных позиций');
}

$deliveryPositions = BizProcHelper::getDeliveryPositions($deliveryId);
$orderPositions = BizProcHelper::getOrderPositions($orderId);

$orderProducts = [];
foreach (CAllCrmProductRow::LoadRows('Ta2', $orderId) as $p) {
    $orderProducts[$p['PRODUCT_ID']] = $p;
}

$hasDuplicate = false;
$positionIds = [];
foreach ($orderPositions as $orderPosition) {
    if (!isset($orderPosition['JSON'][$orderId])) { continue; }

    $shipQty = array_sum(array_column($orderPosition['JSON'][$orderId]['D'], 'Q'));
    $availableQty = $orderPosition['JSON'][$orderId]['Q'] - $shipQty;

    if ($availableQty <= 0) { continue; }

    $productForDelivery = $orderProducts[$orderPosition['ID_TOVARA']] ?? null;

    $duplicateName = '';
    foreach ($deliveryPositions as $deliveryPosition) {
        if ($orderPosition['ID_TOVARA'] === $deliveryPosition['ID_TOVARA']) {
            $duplicateName = $deliveryPosition['NAME'];
            break;
        }
    }

    if ($duplicateName != '') {
        $duplicatesId = [];
        foreach ($orderProducts as $product) {
            if ($product['PRODUCT_NAME'] == $duplicateName) {
                if (!in_array($product['PRODUCT_ID'], $duplicatesId)) {
                    $duplicatesId[] = $product['PRODUCT_ID'];
                }
            }
        }

        foreach ($deliveryPositions as $deliveryPosition) {
            if ($deliveryPosition['NAME'] == $duplicateName) {
                if (!in_array($deliveryPosition['ID_TOVARA'], $duplicatesId)) {
                    $duplicatesId[] = $deliveryPosition['ID_TOVARA'];
                }
            }
        }

        $analogues = [];
        $analogueForUseId = '';
        $analoguesData = CCatalogProduct::GetList(
            ['ID' => 'ASC'],
            ['ELEMENT_NAME' => $duplicateName],
            false,
            false,
            ['ID', 'VAT_ID', 'VAT_INCLUDED', 'MEASURE']
        );
        while (($analogue = $analoguesData->Fetch())) {
            if (!in_array($analogue['ID'], $duplicatesId) && $analogueForUseId === '') {
                $analogueForUseId = $analogue['ID'];
            }
            $analogues[] = $analogue;
        }

        if ($analogueForUseId === '') {
            $ciBlockElement = new CIBlockElement;

            $analogueForUseId = $ciBlockElement->Add([
                'IBLOCK_ID' => 15,
                'NAME' => $duplicateName,
                'TYPE' => 4,
                'VAT_ID' => $analogues[0]['VAT_ID'],
                'VAT_INCLUDED' => $analogues[0]['VAT_INCLUDED'],
                'MEASURE' => $analogues[0]['MEASURE'],
                'ACTIVE' => 'Y',
                'AVAILABLE' => 'Y',
                'PROPERTY_VALUES' => ['CML2_LINK' => $analogues[0]['ID']]
            ]);

            CCatalogProduct::Add(['ID' => $analogueForUseId, 'QUANTITY' => 0]);
        }

        foreach ($orderProducts as $key => $product) {
            if ($product['PRODUCT_ID'] == $orderPosition['ID_TOVARA']) {
                $hasDuplicate = true;
                $orderProducts[$key]['PRODUCT_ID'] = $analogueForUseId;
                $productForDelivery = $product;
            }
        }

        CIBlockElement::SetPropertyValues($orderPosition['ID'], 20, $analogueForUseId, 'ID_TOVARA');

        if ($orderPosition['ZAKAZ_KLIENTA'] != '') {
            $dealProducts = CAllCrmProductRow::LoadRows('Ta2', $orderPosition['ZAKAZ_KLIENTA']);

            $newDealProducts = [];
            foreach ($dealProducts as $product) {
                if ($product['PRODUCT_ID'] === $orderPosition['ID_TOVARA']) {
                    $product['PRODUCT_ID'] = $analogueForUseId;
                }
            }

            CCrmProductRow::SaveRows('Ta2', $orderPosition['ZAKAZ_KLIENTA'], $newDealProducts);
        }
    }

    $deliveryIds = [];
    $shipQty = 0;
    foreach ($orderPosition['JSON'] as $orderItem) {
        foreach ($orderItem['D'] as $item) {
            $deliveryIds[] = $item['ID'];
            $shipQty += $item['Q'];
        }
    }
    $deliveryIds[] = $deliveryId;
    \CIBlockElement::SetPropertyValues($orderPosition['ID'], 20, $deliveryIds, 'DOSTAVKA');

    $ready = false;
    foreach ($orderPosition['JSON'][$orderId]['D'] as $item) {
        if ($item['ID'] == $deliveryId) {
            $item['Q'] += $availableQty;
            $ready = true;
            break;
        }
    }
    if (!$ready) {
        $orderPosition['JSON'][$orderId]['D'][] = ['ID' => $deliveryId, 'Q' => $availableQty, 'PTU' => [], 'RTU' => []];
    }
    \CIBlockElement::SetPropertyValues($orderPosition['ID'], 20, json_encode($orderPosition['JSON']), 'JSON');

    $shipQty += $availableQty;
    \CIBlockElement::SetPropertyValues($orderPosition['ID'], 20, $shipQty, 'OTGRUZHENO');

    BizProcHelper::updateDeliveryListInPosition($orderPosition['ID']);

    $products = [];
    $sum = 0;
    $deliveryProducts = CAllCrmProductRow::LoadRows('T95', $deliveryId);
    $deliveryProducts[] = $productForDelivery;
    foreach ($deliveryProducts as $product) {
        $products[] = [
            'PRODUCT_ID'   =>  $product['PRODUCT_ID'],
            'PRODUCT_NAME' => $product['PRODUCT_NAME'],
            'QUANTITY'     => $product['QUANTITY'],
            'PRICE'        => $product['PRICE'],
            'MEASURE_CODE' => $product['MEASURE_CODE'],
            'MEASURE_NAME' => $product['MEASURE_NAME'],
            'TAX_RATE'     => $product['TAX_RATE'],
            'TAX_INCLUDED' => $product['TAX_INCLUDED']
        ];

        $sum += ((float) $product['PRICE_NETTO'] * (float) $product['QUANTITY']) * (1 + $product['TAX_RATE'] / 100);
    }
    CCrmProductRow::SaveRows('T95', $deliveryId, $products);

    $factory = Container::getInstance()->getFactory(149);
    $rsItems = $factory->getItems(['filter' => ['ID' => $deliveryId]]);
    foreach ($rsItems as $rsItem) {
        $rsItem->set('OPPORTUNITY', $sum);
        $rsItem->save();
        $saveOperation = $factory->getUpdateOperation($rsItem);
        $operationResult = $saveOperation->launch();
    }
}

if ($hasDuplicate) {
    CCrmProductRow::SaveRows('Ta2', $orderId, $orderProducts);
}

$positionIds[] = $orderPosition['ID'];

$this->SetVariable('message', 'Сделка успешно привязана к доставке');
$this->SetVariable('positionIds', $positionIds);
