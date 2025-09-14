<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Обрабатываем товары, пришедшие из 1С

use Bitrix\Catalog\ProductTable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
    Loader::includeModule('catalog');
    Loader::includeModule('iblock');
} catch (LoaderException $e) { return; }

$orderId       = '{{ID}}';
$existingRows  = [];
$obElementList = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_POSTAVSHCHIKU' => $orderId],
    false,
    false,
    ['NAME', 'PROPERTY_ID_TOVARA', 'PROPERTY_KOLICHESTVO']
);
while ($arElement = $obElementList->fetch()) {
    $existingRows[] = [
        'PRODUCT_NAME' => $arElement['NAME'],
        'PRODUCT_ID'   => $arElement['PROPERTY_ID_TOVARA_VALUE'],
        'QUANTITY'     => $arElement['PROPERTY_KOLICHESTVO_VALUE']
    ];
}

$usedProductIds = array_column($existingRows, 'PRODUCT_ID');
$productRows = $sourceProductRows = CAllCrmProductRow::LoadRows('Ta2', $orderId);
$updatedRows = [];

// Сопоставляем товары заказа с его товарными позициями
foreach ($productRows as $k => $row) {
    $found = false;

    foreach ($existingRows as $key => $existingRow) {
        if (
            $row['PRODUCT_ID'] == $existingRow['PRODUCT_ID']
            && $row['QUANTITY'] == $existingRow['QUANTITY']
            && (
                $row['PRODUCT_NAME'] == $existingRow['PRODUCT_NAME']
                || strpos($row['PRODUCT_NAME'], $existingRow['PRODUCT_NAME']) !== false
                || strpos($existingRow['PRODUCT_NAME'], $row['PRODUCT_NAME']) !== false
            )
        ) {
            $found = true;
            $updatedRows[] = $row;
            unset($existingRows[$key]);
            unset($sourceProductRows[$k]);
            break;
        }
    }

    if ($found) { continue; }

    foreach ($existingRows as $key => $existingRow) {
        if (
            $row['QUANTITY'] == $existingRow['QUANTITY']
            && (
                $row['PRODUCT_NAME'] == $existingRow['PRODUCT_NAME']
                || strpos($row['PRODUCT_NAME'], $existingRow['PRODUCT_NAME']) !== false
                || strpos($existingRow['PRODUCT_NAME'], $row['PRODUCT_NAME']) !== false
            )
        ) {
            $row['PRODUCT_ID'] = $existingRow['PRODUCT_ID'];
            $updatedRows[] = $row;
            unset($existingRows[$key]);
            unset($sourceProductRows[$k]);
            break;
        }
    }
}

// Если не все товарные позиции были сопоставлены
foreach ($sourceProductRows as $row) {
    $productName = trim($row['PRODUCT_NAME']);
    $conflict    = true;
    $diff        = [];

    // Ищем товарную позицию с тем же именем с наболее близким количеством
    foreach ($existingRows as $key => $existingRow) {
        if (
            $row['PRODUCT_NAME'] == $existingRow['PRODUCT_NAME']
            || strpos($row['PRODUCT_NAME'], $existingRow['PRODUCT_NAME']) !== false
            || strpos($existingRow['PRODUCT_NAME'], $row['PRODUCT_NAME']) !== false
        ) {
            if (empty($diff)) {
                $diff = [
                    'PRODUCT_ID' => $existingRow['PRODUCT_ID'],
                    'INDEX'      => $key,
                    'VALUE'      => abs($row['QUANTITY'] - $existingRow['QUANTITY'])
                ];
            } else {
                if (abs($row['QUANTITY'] - $existingRow['QUANTITY']) < $diff['VALUE']) {
                    $diff = [
                        'PRODUCT_ID' => $existingRow['PRODUCT_ID'],
                        'INDEX'      => $key,
                        'VALUE'      => abs($row['QUANTITY'] - $existingRow['QUANTITY'])
                    ];
                }
            }
        }
    }

    if (!empty($diff)) {
        $row['PRODUCT_ID'] = $diff['PRODUCT_ID'];
        $conflict = false;
        unset($existingRows[$diff['INDEX']]);
    }


    // Если подходящая товарная позиция не нашлась
    if ($conflict && in_array($row['PRODUCT_ID'], $usedProductIds)) {
        // Ищем свободный аналог в каталоге
        $arAnalogue = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => [14, 15], 'ACTIVE' => 'Y', 'NAME' => $productName, '!ID' => $usedProductIds],
            false,
            false,
            ['*']
        )->fetch();

        // Если свободный аналог не найден, то создаем в каталоге новое предложение
        if (empty($arAnalogue)) {
            $cIBlockElement = new CIBlockElement;

            $arProduct = CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => 14, 'NAME' => $productName],
                false,
                false,
                ['ID']
            )->fetch();

            if (empty($arProduct)) {
                $mainProductId = $cIBlockElement->Add([
                    'IBLOCK_ID' => 14,
                    'NAME'      => $productName,
                    'TYPE'      => ProductTable::TYPE_SKU,
                    'ACTIVE'    => 'Y',
                    'AVAILABLE' => 'Y'
                ]);
            } else {
                $mainProductId = $arProduct['ID'];
            }

            $analogueId = $cIBlockElement->Add([
                'IBLOCK_ID'       => 15,
                'NAME'            => $productName,
                'TYPE'            => ProductTable::TYPE_OFFER,
                'VAT_ID'          => $row['VAT_ID'],
                'VAT_INCLUDED'    => $row['VAT_INCLUDED'],
                'MEASURE'         => $row['MEASURE'],
                'ACTIVE'          => 'Y',
                'AVAILABLE'       => 'Y',
                'PROPERTY_VALUES' => ['CML2_LINK' => $mainProductId]
            ]);

            if ($analogueId) {
                CCatalogProduct::Add(['ID' => $analogueId, 'QUANTITY' => 0]);
            }
        } else {
            $analogueId = $arAnalogue['ID'];
        }

        $row['PRODUCT_ID'] = $analogueId;
    }

    $usedProductIds[] = $row['PRODUCT_ID'];
    $updatedRows[] = $row;
}

CCrmProductRow::SaveRows('Ta2', $orderId, $updatedRows);
