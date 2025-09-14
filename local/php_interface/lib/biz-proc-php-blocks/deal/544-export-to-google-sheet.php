<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Выгружаем данные в Google-таблицу

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$dealId    = '{{ID}}';
$dealTitle = '{{Название}}';

$values = [[
    'Сделка',
    'Ссылка на сделку',
    'Количество в сделке',
    'Наименование поставщика',
    'Договор поставщика',
    'Спецификация поставщика',
    'Номер счёта, дата, гарантийные письма',
    'Наименование товара',
    'Единица измерения',
    'Количество',
    'Ссылка на ЗП',
    'Ссылка на ПТУ'
]];

$obElementList = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'PROPERTY_ZAKAZ_KLIENTA' => $dealId, 'PROPERTY_PRODAZHA' => 1, 'PROPERTY_ZAKUPKA' => 1],
    false,
    false,
    ['ID', 'NAME', 'PROPERTY_KOLICHESTVO', 'PROPERTY_ID_TOVARA', 'PROPERTY_JSON']
);

while ($arElement = $obElementList->fetch()) {
    if ($arElement['PROPERTY_JSON_VALUE']) {
        $dealFirstRow = true;

        foreach (json_decode($arElement['PROPERTY_JSON_VALUE'], true) as $orderId => $orderItem) {
            $orderFirstRow = true;

            $factory = Container::getInstance()->getFactory(162);
            $obOrderItems = $factory->getItems([
                'filter' => ['ID' => $orderId],
                'select' => ['COMPANY_ID', 'UF_CRM_5_1681225886', 'UF_CRM_5_1675397794', 'UF_CRM_5_1738548463']
            ]);

            $arOrder = $obOrderItems[0]->getData();

            $supplierTitle = '';
            if ($arOrder['COMPANY_ID']) {
                $supplierTitle = CCrmCompany::GetByID($arOrder['COMPANY_ID'])['TITLE'];
            }

            $supplierContractTitle = '';
            // UF_CRM_5_1681225886 - Договор
            if ($arOrder['UF_CRM_5_1681225886']) {
                $factory = Container::getInstance()->getFactory(129);
                $rsContractList = $factory->getItems([
                    'filter' => ['ID' => $arOrder['UF_CRM_5_1681225886']],
                    'select' => ['TITLE']
                ]);

                if (count($rsContractList) > 0) {
                    $supplierContractTitle = $rsContractList[0]->getData()['TITLE'];
                }
            }

            $arProductList = CAllCrmProductRow::LoadRows('Ta2', $orderId);
            $array = [];
            foreach ($arProductList as $arProduct) {
                $array[$arProduct['PRODUCT_ID']] = $arProduct;
            }
            $arProductList = $array;

            $orderLink = 'https://crm.a9systems.ru/page/zakazy_postavshchiku/zakaz_postavshchiku/type/162/details/' . $orderId . '/';

            $ptuList = [];
            foreach ($orderItem['D'] as $item) {
                if ($item['PTU']) {
                    if (!in_array($item['PTU'], $ptuList)) {
                        $ptuList[] = $item['PTU'];
                    }
                }
            }


            if ($ptuList) {
                foreach ($ptuList as $ptuId) {
                    $purchaseLink = 'https://crm.a9systems.ru/page/dopolnitelnye_raskhody/priobretenie_tovarov_i_uslug/type/148/details/' . $ptuId . '/';

                    $values[] = [
                        $dealFirstRow  ? $dealTitle : '',
                        $dealFirstRow  ? 'https://crm.a9systems.ru/crm/deal/details/' . $dealId . '/' : '',
                        $dealFirstRow  ? $arElement['PROPERTY_KOLICHESTVO_VALUE'] : '',
                        $orderFirstRow ? $supplierTitle : '',
                        $orderFirstRow ? $supplierContractTitle : '',
                        $orderFirstRow ? $arOrder['UF_CRM_5_1675397794'] : '',
                        $orderFirstRow ? $arOrder['UF_CRM_5_1738548463'] : '',
                        $orderFirstRow ? $arProductList[$arElement['PROPERTY_ID_TOVARA_VALUE']]['PRODUCT_NAME'] : '',
                        $orderFirstRow ? $arProductList[$arElement['PROPERTY_ID_TOVARA_VALUE']]['MEASURE_NAME'] : '',
                        $orderFirstRow ? $orderItem['Q'] : '',
                        $orderFirstRow ? $orderLink : '',
                        $purchaseLink
                    ];

                    $dealFirstRow = $orderFirstRow = false;
                }
            } else {
                $values[] = [
                    $dealFirstRow ? $dealTitle : '',
                    $dealFirstRow ? 'https://crm.a9systems.ru/crm/deal/details/' . $dealId . '/' : '',
                    $dealFirstRow ? $arElement['PROPERTY_KOLICHESTVO_VALUE'] : '',
                    $supplierTitle,
                    $supplierContractTitle,
                    $arOrder['UF_CRM_5_1675397794'],
                    $arOrder['UF_CRM_5_1738548463'],
                    $arProductList[$arElement['PROPERTY_ID_TOVARA_VALUE']]['PRODUCT_NAME'],
                    $arProductList[$arElement['PROPERTY_ID_TOVARA_VALUE']]['MEASURE_NAME'],
                    $orderItem['Q'],
                    $orderLink,
                    ''
                ];

                $dealFirstRow = false;
            }
        }
    } else {
        $values[] = [
            $dealTitle,
            'https://crm.a9systems.ru/crm/deal/details/' . $dealId . '/',
            $arElement['PROPERTY_KOLICHESTVO_VALUE'],
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
    }
}

if (count($values) <= 1) { return; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/custom/google_api/google-api-php-client--PHP8.0/vendor/autoload.php';

putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $_SERVER['DOCUMENT_ROOT'] . '/local/custom/service_key.json');

$client = new Google_Client();
$client->useApplicationDefaultCredentials();
$client->addScope(['https://www.googleapis.com/auth/drive', 'https://www.googleapis.com/auth/spreadsheets']);
$service = new Google_Service_Sheets($client);
$spreadsheetProperties = new Google_Service_Sheets_SpreadsheetProperties();
$spreadsheetProperties->setTitle('export');
$spreadsheet = new Google_Service_Sheets_Spreadsheet();
$spreadsheet->setProperties($spreadsheetProperties);
$response = $service->spreadsheets->create($spreadsheet);
$spreadsheetId = $response->spreadsheetId;
$sheetTitle = 'Товарные позиции';
$spreadsheets = $service->spreadsheets->get($spreadsheetId);
$sheets = $spreadsheets->getSheets();
$sheetProperties = $sheets[0]->getProperties();
$sheetProperties->setTitle($sheetTitle);
$updateSheetRequests = new Google_Service_Sheets_UpdateSheetPropertiesRequest();
$updateSheetRequests->setProperties($sheetProperties);
$updateSheetRequests->setFields('title');
$sheetRequests = new Google_Service_Sheets_Request();
$sheetRequests->setUpdateSheetProperties($updateSheetRequests);
$requests = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
$requests->setRequests($sheetRequests);
$service->spreadsheets->BatchUpdate($spreadsheetId, $requests);

$valueRange = new Google_Service_Sheets_ValueRange();
$valueRange->setValues($values);
$options = ['valueInputOption' => 'USER_ENTERED'];
$service->spreadsheets_values->update($spreadsheetId, $sheetTitle . '!A1', $valueRange, $options);

$drive = new Google_Service_Drive($client);
$drivePermission = new Google_Service_Drive_Permission();
$drivePermission->setType('anyone');
$drivePermission->setRole('writer');
$drive->permissions->create($spreadsheetId, $drivePermission);

$this->SetVariable('spreadsheetId', $spreadsheetId);
