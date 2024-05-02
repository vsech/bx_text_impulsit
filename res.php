<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Check if the request is valid
if (!check_bitrix_sessid() || empty($_POST['ip'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$ip = htmlspecialcharsbx($_POST['ip']);

// Include your component class
CBitrixComponent::includeComponentClass('vsech:bx-text-impulsit');

// Create an instance of the component class
$component = new GeoIPSearchComponent();

// Set the parameters
$component->arParams = $_POST['arParams'];

// Call the method to handle the POST request
$component->handlePostRequest($ip);

// Get the result
$arResult = $component->arResult;

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode($arResult);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
die();
