<?php

use Bitrix\Main\ModuleManager;

defined('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or exit;

class gge_ais extends CModule
{
    public $MODULE_ID;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_GROUP_RIGHTS;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];

        include __DIR__ . '/version.php';

        $this->MODULE_NAME = 'Интеграция АИС';
        $this->MODULE_DESCRIPTION = '';
        $this->MODULE_ID = 'gge.ais';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_GROUP_RIGHTS = 'N';
        $this->PARTNER_NAME = 'Webpractik';
        $this->PARTNER_URI = 'https://webpractik.ru';
    }

    /**
     * @return bool
     */
    public function DoInstall(): bool
    {
        global $APPLICATION;
        $APPLICATION->throwException('Этот модуль нельзя устанавливать через административную часть сайта, для установки модуля обратитесь к разработчику');

        return true;
    }

    /**
     * @return bool
     */
    public function DoUninstall(): bool
    {
        global $APPLICATION;
        $APPLICATION->throwException('Этот модуль нельзя удалять через административную часть сайта, для удаления модуля обратитесь к разработчику');
        return false;
    }
}
