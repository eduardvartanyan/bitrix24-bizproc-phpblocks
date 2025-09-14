<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Обновляем статус

use Bitrix\Crm\Service\Container;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;

try {
    Loader::includeModule('crm');
} catch (LoaderException $e) { return; }

$positionId = '{{ID элемента}}';

$arElement = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => 20, 'ID' => $positionId],
    false,
    false,
    ['PROPERTY_JSON', 'PROPERTY_KOLICHESTVO'])->fetch();

if (!$arElement) { return; }

$instance     = Container::getInstance();
$orderFactory = $instance->getFactory(162);
$shipFactory  = $instance->getFactory(149);

$status   = '';
$prodQty  = $shipQty = $storageQty = $successQty = 0;
$prodFail = $shipFail = false;

foreach (json_decode($arElement['PROPERTY_JSON_VALUE'], true) as $orderId => $orderItem) {
    $arOrder = [];
    foreach ($orderFactory->getItems(['filter' => ['ID' => $orderId]]) as $obOrder) {
        $arOrder = $obOrder->getData();
    }

    if (!$arOrder) { continue; }

    if (
        !in_array($arOrder['STAGE_ID'], [
            'DT162_7:NEW', // Запуск в производство
            'DT162_7:UC_1I6G9R', // Проверка админом
    ])) {
        $prodQty += $orderItem['Q'];

        // Задержка производства
        if ($arOrder['STAGE_ID'] == 'DT162_7:CLIENT') {
            $prodFail = true;
        }
    }


    foreach ($orderItem['D'] as $item) {
        $arShip = [];
        foreach ($shipFactory->getItems(['filter' => ['ID' => $item['ID']]]) as $obShip) {
            $arShip = $obShip->getData();
        }

        if (!$arShip) { continue; }

        if ($arShip['STAGE_ID'] != 'DT149_10:NEW') {
            $shipQty += $item['Q'];

            // Задержка доставки
            if ($arShip['STAGE_ID'] == 'DT149_10:CLIENT') {
                $shipFail = true;
            }

            if (
                in_array($arShip['STAGE_ID'], [
                    'DT149_10:UC_8369SL', // Консолидационный склад
                    'DT149_10:UC_4ZRVEK' // Проектный склад
            ])) {
                $storageQty += $item['Q'];
            }

            if (
                in_array($arShip['STAGE_ID'], [
                    'DT149_10:UC_T9LMO2', // У заказчика
                    'DT149_10:SUCCESS' // Успех
            ])) {
                $successQty += $item['Q'];
            }
        }
    }
}

$dealQty = $arElement['PROPERTY_KOLICHESTVO_VALUE'];

if ($dealQty > 0) {
    if ($prodQty > 0) {
        $status = 'Производство';
        if ($prodFail) {
            $status .= '(!)';
        }

        if ($shipQty > 0) {
            if ($shipQty == $prodQty) {
                $status = 'Доставка';
            } else {
                $status .= ' + Доставка';
            }

            if ($shipFail) {
                $status .= '(!)';
            }

            if ($storageQty > 0) {
                if ($storageQty == $shipQty) {
                    $status = 'На складе';
                } else {
                    $status .= ' + На складе';
                }

                if ($successQty > 0) {
                    if ($successQty == $storageQty) {
                        $status = '✅ У заказчика';
                    } else {
                        $status .= ' + У заказчика';
                    }
                }
            } else {
                if ($successQty > 0) {
                    if ($successQty == $prodQty) {
                        $status = '✅ У заказчика';
                    } else {
                        $status .= ' + У заказчика';
                    }
                }
            }
        } else {
            if ($successQty > 0) {
                if ($successQty == $prodQty) {
                    $status = '✅ У заказчика';
                } else {
                    $status .= ' + У заказчика';
                }
            }
        }
    } else {
        $status = 'Запуск';
        if ($shipQty > 0) {
            $status .= ' + Производство + Доставка';
            if ($storageQty > 0) {
                if ($successQty > 0) {
                    $status .= ' + У заказчика';
                }
            }
        } elseif ($storageQty > 0) {
            $status .= ' + Производство + На складе';
            if ($successQty > 0) {
                $status .= ' + У заказчика';
            }
        } elseif ($successQty > 0) {
            $status .= ' + Производство + У заказчика';
        }
    }
} else {
    if ($prodQty > 0) {
        $status = 'Производство';
        if ($prodFail) {
            $status .= '(!)';
        }

        if ($shipQty > 0) {
            if ($shipQty == $prodQty) {
                $status = 'Доставка';
            } else {
                $status .= ' + Доставка';
            }

            if ($shipFail) {
                $status .= '(!)';
            }

            if ($storageQty > 0) {
                if ($storageQty == $shipQty) {
                    $status = 'На складе';
                } else {
                    $status .= ' + На складе';
                }

                if ($successQty > 0) {
                    if ($successQty == $storageQty) {
                        $status = '✅ У заказчика';
                    } else {
                        $status .= ' + У заказчика';
                    }
                }
            } else {
                if ($successQty > 0) {
                    if ($successQty == $prodQty) {
                        $status = '✅ У заказчика';
                    } else {
                        $status .= ' + У заказчика';
                    }
                }
            }
        } else {
            if ($successQty > 0) {
                if ($successQty == $prodQty) {
                    $status = '✅ У заказчика';
                } else {
                    $status .= ' + У заказчика';
                }
            }
        }
    } else {
        $status = 'Запуск';
    }
}

CIBlockElement::SetPropertyValues($positionId, 20, $status, 'STATUS');
