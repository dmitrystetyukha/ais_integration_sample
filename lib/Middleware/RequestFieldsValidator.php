<?php

namespace GGE\Ais\Middleware;

use Bitrix\Main\Context;
use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Response\Json;
use Bitrix\Main\Error;
use Bitrix\Main\Event;

class RequestFieldsValidator extends Base
{
    private const SEMINAR_REQIRED_POST_FIELDS = [
        'name',
        'date',
    ];
    private const BAD_REQUEST_CODE = 400;
    private const BAD_REQUEST_STATUS_PHRASE = '400 Bad Request';

    /**
     * @param Event $event
     * @return null
     */
    public function onBeforeAction(Event $event)
    {
        if (Context::getCurrent()?->getRequest()->getRequestMethod() === 'GET') {
            return null;
        }

        $fields = Context::getCurrent()?->getRequest()->getPostList()->toArray();

        if (!$fields) {
            $error = new Error('Request body missing', self::BAD_REQUEST_CODE);
            $this->sendResponse($error);
        }

        foreach (self::SEMINAR_REQIRED_POST_FIELDS as $fieldName) {
            if (!array_key_exists($fieldName, $fields) || empty($fields[$fieldName])) {
                $error = new Error("Required field '$fieldName' missing or empty", self::BAD_REQUEST_CODE);
                $this->sendResponse($error);
            }
        }

        return null;
    }

    protected function sendResponse(Error $error): void
    {
        $response = new Json(
            [
                'error' => $error->getMessage(),
            ]
        );
        $response->setStatus(self::BAD_REQUEST_STATUS_PHRASE);
        $response->writeHeaders();

        $this->addError($error);

        $response->send();
    }
}
