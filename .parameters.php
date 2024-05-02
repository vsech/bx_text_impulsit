<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

if (!Loader::includeModule("highloadblock")) {
    return;
}

// Получаем список доступных HL блоков
$hlblocks = [];
$hlblockDb = HighloadBlockTable::getList();
while ($hlblock = $hlblockDb->fetch()) {
    $hlblocks[$hlblock['ID']] = '[' . $hlblock['ID'] . '] ' . $hlblock['NAME'];
}
$hlblocks['create'] = GetMessage("CREATE_NEW_HL_BLOCK");
$arComponentParameters = [
    "GROUPS" => [],
    "PARAMETERS" => [
        "HL_BLOCK_ID" => [
            "PARENT" => "BASE",
            "NAME" => GetMessage("HL_BLOCK_ID"),
            "TYPE" => "LIST",
            "VALUES" => $hlblocks,
            "REFRESH" => "Y",
        ],
        "GEO_SERVICE" => [
            "PARENT" => "BASE",
            "NAME" => GetMessage("GEO_SERVICE"),
            "TYPE" => "LIST",
            "VALUES" => [
                "local" => "local",
                "sypexgeo.net" => "sypexgeo.net",
                "ipapi.co" => "ipapi.co",
            ],
            "DEFAULT" => "local",
        ],
    ],
];
