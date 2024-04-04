<?php

use Bitrix\Main\Routing\RoutingConfigurator;
use GGE\Ais\Controller\SeminarController;

return static function (RoutingConfigurator $router) {
    $router->get('seminars', [SeminarController::class, 'getSeminarsAction']);
    $router->post('seminars', [SeminarController::class, 'addSeminarAction']);
};
