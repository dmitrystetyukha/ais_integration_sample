<?php

namespace GGE\Ais\Controller;

use Bitrix\Main\Engine\ActionFilter\Csrf;
use Bitrix\Main\Engine\ActionFilter\HttpMethod;
use Bitrix\Main\Engine\Response\Json;
use Exception;
use GGE\Ais\Middleware\RequestFieldsValidator;
use GGE\Ais\Service\SeminarService;
use GGE\ApiCore\Controller\BaseController;
use GGE\ApiCore\Middleware\BasicAuth;
use Webpractik\Logger\Log;

/**
 *
 */
class SeminarController extends BaseController
{
    /**
     * @return Json
     */
    public function getSeminarsAction(): Json
    {
        try {
            $seminars = (new SeminarService())->getSeminars();
            return $this->sendResponse(200, ['data' => $seminars]);
        } catch (Exception $e) {
            Log::channel('ais')
                ->error(
                    'Failed to get seminars',
                    [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTrace(),
                        'request' => $this->getRequest(),
                    ]
                );
        }

        return $this->sendResponse(500, [], 'Failed to get seminars. More details in the logs.');
    }

    public function addSeminarAction(): Json
    {
        $seminar = $this->getRequest()->getPostList()->toArray();
        $seminarRequestFiles = $this->getRequest()?->getFileList()->toArray()['file'];
        $seminar['file'][] = $seminarRequestFiles; // добавлено как ассоциативный для корректной работы FileManager

        try {
            $resultData = (new SeminarService())->createOrUpdateSeminar($seminar);

            return $this->sendResponse(
                200,
                [
                    'data' => [
                        'siteId' => $resultData['siteId']
                    ]
                ]
            );
        } catch (Exception $e) {
            Log::channel('ais')
                ->error(
                    'Failed to add/update seminars',
                    [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTrace(),
                        'request' => $this->getRequest(),
                    ]
                );
        }
        return $this->sendResponse(500, [], 'Failed to add/update seminars. More details in the logs.');
    }

    protected function getDefaultPreFilters(): array
    {
        return [
            new RequestFieldsValidator(),
            new BasicAuth(getenv('AIS_BASIC_LOGIN'), getenv('AIS_BASIC_PASSWORD')),
            new HttpMethod([
                HttpMethod::METHOD_GET,
                HttpMethod::METHOD_POST,
                'OPTIONS',
            ]),
            new Csrf(false),
        ];
    }
}
