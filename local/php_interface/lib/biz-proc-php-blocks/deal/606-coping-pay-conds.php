<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Переносим условия оплаты

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$ID       = '{{ID}}';
$arFields = ['UF_CRM_1680508611' => '{=A23433_325_39580_11805:UF_CRM_1680508611}'];

(new CCrmDeal)->Update($ID, $arFields);