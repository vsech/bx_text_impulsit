<?php
// /local/components/geoipsearch/component.php

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\HttpClient;

class GeoIPSearchComponent extends CBitrixComponent
{
    private $hlblockId;
    private $geoService;

    /**
     * Include component language file and the language file for the parent class.
     *
     * @see \CBitrixComponent::includeComponentLang()
     */
    public function onIncludeComponentLang()
    {
        $this->includeComponentLang(basename(__FILE__));
        \CBitrixComponent::includeComponentLang('class.php');
    }
    /**
     * Handles POST request from AJAX.
     *
     * Gets IP address from the POST request, checks if it's not empty, calls the method to get geo data
     * for the given IP address and passes the result to the template.
     * If the IP address is empty, sets an error message to the template.
     */
    public function handlePostRequest()
    {
        // Get IP address from the POST request
        $ip = $this->request->getPost('ip');
        // Get component parameters from the POST request
        $ajaxParams = $this->request->getPost('arParams');
        // Get HL_BLOCK_ID and GEO_SERVICE parameters from the POST request
        $this->hlblockId = (int)$ajaxParams['HL_BLOCK_ID'];
        $this->geoService = $ajaxParams['GEO_SERVICE'];
        if (!empty($ip)) {
            // If IP address is not empty, call the method to get geo data for it
            $geoData = $this->getGeoData($ip);
            // Pass the data to the template
            $this->arResult['GEO_DATA'] = $geoData;
        } else {
            // If IP address is empty, set an error message to the template
            $this->arResult['ERROR'] = GetMessage('IP_NOT_PROVIDED');
        }
    }

    /**
     * Prepares component parameters before they are passed to the component template.
     *
     * @param array $arParams
     *
     * @return array Prepared component parameters
     */
    public function onPrepareComponentParams($arParams)
    {
        // HL_BLOCK_ID - ID of the Highloadblock to use for storing geo data
        $this->hlblockId = (int)$arParams['HL_BLOCK_ID'];

        // GEO_SERVICE - GeoIP service to use for fetching geo data
        $this->geoService = $arParams['GEO_SERVICE'];

        return $arParams;
    }

    /**
     * Main component method.
     *
     * Checks if required modules are installed, processes POST request, gets geo data and includes component template.
     */
    public function executeComponent()
    {
        // Check if all required modules are installed
        if (!$this->checkRequirements()) {
            // If some module is not installed, show an error message
            $this->arResult['ERROR'] = GetMessage('REQUIRED_MODULES_NOT_INSTALLED');
            // Include component template to show the error message
            $this->includeComponentTemplate();
            // And do not continue execution of the method
            return;
        }
        if ($this->request->isPost() && check_bitrix_sessid()) {
            $this->handlePostRequest();
        }
        // If it's a POST request and bitrix_sessid is valid
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && check_bitrix_sessid()) {
            // Get posted IP address
            $ip = $_POST['ip'];

            // Validate IP address format
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                // If IP address is invalid, show an error message
                $this->arResult['ERROR'] = GetMessage('INVALID_IP_FORMAT');
            } else {
                // If IP address is valid, get the geo data for it
                $geoData = $this->getGeoData($ip);
                // And save it to the template
                $this->arResult['GEO_DATA'] = $geoData;
            }
        }
        // Include component template to show the result
        $this->includeComponentTemplate();
    }

    /**
     * Checks if all required modules are installed.
     *
     * Checks if Highloadblock module is installed.
     * If the module is not installed, shows an error message.
     * If the module is installed, tries to create a Highloadblock with necessary fields if it doesn't exist.
     *
     * @return bool True if all required modules are installed, false otherwise
     */
    private function checkRequirements()
    {
        // Check if Highloadblock module is installed
        if (!Loader::includeModule('highloadblock')) {
            return false;
        }
        if ($this->hlblockId == 'create') {
            // Try to create a Highloadblock with necessary fields if it doesn't exist
            $this->createHLBlockIfNeeded();
        }

        return true;
    }



    /**
     * Gets geo data for the given IP address.
     *
     * First, tries to get geo data from the Highloadblock storage.
     * If there is no data for the given IP address, tries to get geo data from the service and saves it to the Highloadblock storage.
     *
     * @param string $ip IP address
     *
     * @return array Geo data for the given IP address
     */
    private function getGeoData(string $ip): array
    {
        $geoData = $this->getGeoDataFromHLBlock($ip);
        // If there is no data for the given IP address in HL block storage, try to get it from service and save it to HL block storage
        if (empty($geoData)) {
            $geoData = $this->getGeoDataFromService($ip);
            if (!empty($geoData['location'])) {
                $this->saveGeoDataToHLBlock($ip, $geoData);
            }
        }
        return $geoData;
    }

    /**
     * Gets geo data from the Highloadblock storage.
     *
     * @param string $ip IP address
     *
     * @return array Geo data from the Highloadblock storage
     */
    private function getGeoDataFromHLBlock(string $ip): array
    {
        $geoData = [];

        // Check if Highloadblock module is installed
        if (!Loader::includeModule('highloadblock')) {
            return $geoData;
        }

        // Get access to the HL block table
        $hlblock = HighloadBlockTable::getById($this->hlblockId)->fetch();
        if (!$hlblock) {
            return $geoData;
        }

        // Get the name of the entity for the HL block table
        $entity = HighloadBlockTable::compileEntity($hlblock);
        $entityDataClass = $entity->getDataClass();

        // Find data by IP address in HL block table
        $rsData = $entityDataClass::getList([
            'select' => ['*'],
            'filter' => ['=UF_IP' => $ip],
            'limit' => 1
        ]);
        if ($geo = $rsData->fetch()) {
            // If data is found, get the geo data from it
            $geoData['city'] = $geo['UF_CITY'];
            $geoData['region'] = $geo['UF_REGION'];
            $geoData['country'] = $geo['UF_COUNTRY'];
            $geoData['location'] = $geo['UF_CITY'] . ', ' . $geo['UF_REGION'] . ', ' . $geo['UF_COUNTRY'];
        }

        return $geoData;
    }


    /**
     * Gets geo data from the service.
     *
     * @param string $ip IP address
     *
     * @return array Geo data from the service
     */
    private function getGeoDataFromService(string $ip): array
    {
        $geoData = [];

        // Select geo service URL based on the current configuration
        switch ($this->geoService) {
            case 'sypexgeo.net':
                $url = 'http://api.sypexgeo.net/json/' . $ip;
                break;
            case 'ipapi.co':
                $url = 'https://ipapi.co/' . $ip . '/json/';
                break;
            case 'local':
                $geoDataResult = \Bitrix\Main\Service\GeoIp\Manager::getDataResult($ip, "ru");
                echo '<pre>';
                print_r($geoDataResult);
                echo '</pre>';
                if (!empty($geoDataResult) && $geoDataResult->isSuccess()) {
                    $geoData['city'] = $geoDataResult->getCityName();
                    $geoData['region'] = $geoDataResult->getRegionName();
                    $geoData['country'] = $geoDataResult->getCountryName();
                    $geoData['location'] = $geoDataResult->getCityName() . ', ' . $geoDataResult->getRegionName() . ', ' . $geoDataResult->getCountryName();
                }
                return $geoData;
            default:
                return $geoData;
        }

        // Send GET request to the service
        $httpClient = new HttpClient();
        $response = $httpClient->get($url);

        if ($response !== false) {
            // Decode the response from JSON
            $data = json_decode($response, true);
            // If data is received and not empty
            if (!empty($data)) {
                // Select the required data based on the selected service
                switch ($this->geoService) {
                    case 'sypexgeo.net':
                        $geoData['city'] = $data['city']['name_ru'];
                        $geoData['region'] = $data['region']['name_ru'];
                        $geoData['country'] = $data['country']['name_ru'];
                        $geoData['location'] = $data['city']['name_ru'] . ', ' . $data['region']['name_ru'] . ', ' . $data['country']['name_ru'];
                        break;
                    case 'ipapi.co':
                        $geoData['city'] = $data['city'];
                        $geoData['region'] = $data['region'];
                        $geoData['country'] = $data['country_name'];
                        $geoData['location'] = $data['city'] . ', ' . $data['region'] . ', ' . $data['country_name'];
                        break;
                    default:
                        break;
                }
            }
        }

        return $geoData;
    }



    /**
     * Saves geo data to the Highloadblock storage.
     *
     * @param string $ip IP address
     * @param array $geoData Geo data to save
     */
    private function saveGeoDataToHLBlock(string $ip, array $geoData)
    {
        // Check if the Highloadblock module is installed
        if (!Loader::includeModule('highloadblock')) {
            return;
        }

        // Get the Highloadblock entity by its ID
        $hlblock = HighloadBlockTable::getById($this->hlblockId)->fetch();

        // If there is no such entity, do not save geo data
        if (!$hlblock) {
            return;
        }

        // Compile Highloadblock entity
        $entity = HighloadBlockTable::compileEntity($hlblock);

        // Get the class name of the Highloadblock data class
        $entityDataClass = $entity->getDataClass();

        // Check if there is already a record with the given IP in the storage
        $existingData = $entityDataClass::getList([
            'select' => ['ID'],
            'filter' => ['=UF_IP' => $ip],
            'limit' => 1
        ])->fetch();

        if ($existingData) {
            // If the record exists, update its data
            $result = $entityDataClass::update($existingData['ID'], [
                'UF_CITY' => $geoData['city'],
                'UF_REGION' => $geoData['region'],
                'UF_COUNTRY' => $geoData['country'],
            ]);
        } else {
            // If there is no such record, create a new one
            $result = $entityDataClass::add([
                'UF_IP' => $ip,
                'UF_CITY' => $geoData['city'],
                'UF_REGION' => $geoData['region'],
                'UF_COUNTRY' => $geoData['country'],
            ]);
        }

        // If there was an error saving data, handle it
        if (!$result->isSuccess()) {
            // TODO: Handle the error
        }
    }

    /**
     * Creates Highloadblock if it doesn't exist with necessary fields.
     *
     * @return int|null ID of the Highloadblock or null if cannot create it
     */
    private function createHLBlockIfNeeded()
    {
        // Check if Highloadblock module is installed
        if (!Loader::includeModule('highloadblock')) {
            return null;
        }

        // Check if there is already a Highloadblock with the necessary fields
        $hlblock = HighloadBlockTable::getList([
            'filter' => [
                '=NAME' => 'GeoData', // HL block name
            ]
        ])->fetch();

        if (!$hlblock) {
            // If there is no such Highloadblock, create it
            $result = HighloadBlockTable::add([
                'NAME' => 'GeoData',
                'TABLE_NAME' => 'geo_data', // Highloadblock table name
            ]);

            if ($result->isSuccess()) {
                $hlblockId = $result->getId();

                // Add necessary fields to the Highloadblock
                $fields = [
                    ['FIELD_NAME' => 'UF_IP', 'USER_TYPE_ID' => 'string'],
                    ['FIELD_NAME' => 'UF_CITY', 'USER_TYPE_ID' => 'string'],
                    ['FIELD_NAME' => 'UF_REGION', 'USER_TYPE_ID' => 'string'],
                    ['FIELD_NAME' => 'UF_COUNTRY', 'USER_TYPE_ID' => 'string'],
                ];

                // Check if the HL block was created successfully
                if ($hlblockId) {
                    foreach ($fields as $field) {
                        $field['ENTITY_ID'] = 'HLBLOCK_' . $hlblockId;
                        $oUserTypeEntity = new CUserTypeEntity();
                        $fieldId = $oUserTypeEntity->Add($field);
                        if (!$fieldId) {
                            // TODO: Handle the error
                        }
                    }
                    $this->hlblockId = $hlblockId;
                    return $hlblockId;
                } else {
                    // TODO: Handle the error
                    return null;
                }
            } else {
                // TODO: Handle the error
                return null;
            }
        } else {
            // If Highloadblock already exists, return its ID
            $this->hlblockId = $hlblock['ID'];
            return $hlblock['ID'];
        }
    }
}
