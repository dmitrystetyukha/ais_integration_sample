<?php

namespace GGE\Ais\Service;

use Bitrix\Iblock\Elements\ElementSeminarsCitiesTable;
use Bitrix\Iblock\Elements\ElementSeminarsProgramTable;
use Bitrix\Iblock\Elements\ElementSeminarsTable;
use Bitrix\Iblock\Elements\EO_ElementSeminars;
use Bitrix\Iblock\Elements\EO_ElementSeminars_Collection;
use Bitrix\Iblock\Elements\EO_ElementSeminarsCities;
use Bitrix\Iblock\Elements\EO_ElementSeminarsProgram;
use Bitrix\Iblock\Elements\EO_ElementSeminarsProgram_Collection;
use Bitrix\Iblock\EO_PropertyEnumeration;
use Bitrix\Iblock\ORM\PropertyValue;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\EO_File;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectException;
use Bitrix\Main\SystemException;
use GGE\Ais\Utils\StringFormatTool;
use GGE\ApiCore\Trait\CacherTrait;
use GGE\ApiCore\Util\Cacher;
use GGE\ApiCore\Util\FileManager;
use OpenApi\Annotations as OA;
use RuntimeException;

class SeminarService
{
    use CacherTrait;

    private const TEN_HOURS_TTL = 36000;
    private Cacher $cacheManager;
    private string $cacheTag;
    private StringFormatTool $stringTool;

    /**
     * @throws RuntimeException
     */
    public function __construct()
    {
        try {
            Loader::includeModule('iblock');
        } catch (LoaderException $e) {
            throw new RuntimeException('Failed to load \'iblock\' module. ' . $e->getMessage());
        }

        $this->cacheManager = static::getNewInstanceCacher();
        try {
            [$this->cacheTag] = Cacher::getIblockCacheTags([SEMINARS_API_CODE]);
        } catch (ArgumentException | ObjectException | SystemException  $e) {
            throw new RuntimeException('Failed to get iblock cache tags. ' . $e->getMessage());
        }

        $this->stringTool = new StringFormatTool();
    }

    /**
     * @OA\Get()
     * @throws RuntimeException
     */
    public function getSeminars(): array
    {
        try {
            $seminars = $this->cacheManager->getCachedData(self::TEN_HOURS_TTL, $this->cacheTag);

            if (!$seminars) {
                $seminarsElements = $this->getSeminarsElements();
                foreach ($seminarsElements as $seminarElement) {
                    $seminars[] = $this->prepareSeminarElement($seminarElement);
                }
                $this->cacheManager->save($seminars, [$this->cacheTag]);
            }
        } catch (ObjectException | ArgumentException | SystemException $e) {
            throw new RuntimeException('Failed to get seminars. ' . $e->getMessage());
        }

        return $seminars ?? [];
    }

    /**
     * @throws RuntimeException
     */
    public function createOrUpdateSeminar(array $seminarData): array
    {
        if (
            !$seminarData['siteId'] ||
            $seminarElement = $this->getSeminarElementById((int)$seminarData['siteId']) !== null
        ) {
                $seminarElement = new EO_ElementSeminars();
        }

        $seminarElement->setName($seminarData['name']);
        $startDate = date_create($seminarData['date'])->format('Y-m-d H:i:s');
        $seminarElement->setDate($startDate);

        if ($seminarData['city'] && $seminarData['city'] !== '') {
            $cityName = $this->stringTool->recognizeCityName($seminarData['city']);
            if ($cityName === null) {
                throw new RuntimeException('Failed to recognize city name in seminar data array');
            }

            $cityElement = $this->getCityElementByName($cityName);
            if ($cityElement === null) {
                throw new RuntimeException('Specified city name not found');
            }

            $seminarElement->getCity()?->setElement($cityElement);
        }

        if ($seminarData['webinar'] === 'true') {
            $isWebinar = $this->getIsWebinarEnumByValue($seminarData['webinar']);
            if ($isWebinar) {
                $seminarElement->setIsWebinar($isWebinar->getId());
            }
        }

        if ($seminarData['fullName']) {
            $seminarElement->setPreviewText($seminarData['fullName']);
        }

        if ($seminarData['description']) {
            $seminarElement->setDetailText($seminarData['description']);
        }

        if ($seminarData['location']) {
            $seminarElement->setPlaceCoords($seminarData['location']);
        }


        if ($seminarData['timeStart']) {
            $seminarElement->setTimeStart($seminarData['timeStart']);
        }

        if ($seminarData['timeFinish']) {
            $seminarElement->setTimeFinish($seminarData['timeFinish']);
        }

        if ($seminarData['academicHours']) {
            $seminarElement->setAcademicHours($seminarData['academicHours']);
        }
        if ($seminarData['price']) {
            $seminarElement->setPrice($seminarData['price']);
        }
        if ($seminarData['tax']) {
            $seminarElement->setTax($seminarData['tax']);
        }
        if ($seminarData['taxAmount']) {
            $seminarElement->setTaxAmount($seminarData['taxAmount']);
        }

        if ($seminarData['program']) {
            $seminarElement->removeAllProgramParts();
            foreach ($this->getOrCreateProgramPartsElements($seminarData['program']) as $programPart) {
                $value = $programPart->getId();
                $seminarElement->addTo('PROGRAM_PARTS', new PropertyValue($value));
            }
        }

        if ($seminarData['file']) {
            $seminarElement->unsetProgramFile();
            $fileId = $this->getFileElement($seminarData['file']);
            if ($fileId) {
                $seminarElement->set(
                    'PROGRAM_FILE',
                    new PropertyValue($fileId)
                );
            }
        }

        if ($seminarData['contactPerson']) {
            $seminarElement->setContactFio($seminarData['contactPerson']);
        }
        if ($seminarData['contactPhone']) {
            $seminarElement->setContactPhone($seminarData['contactPhone']);
        }
        if ($seminarData['contactEmail']) {
            $seminarElement->setContactEmail($seminarData['contactEmail']);
        }

        if ($seminarData['contactPerson'] && $seminarData['contactPhone'] && $seminarData['contactEmail']) {
            $seminarElement->set(
                'SIGN_UP_SEMINAR_DESC_1',
                $this->stringTool->getFilledContactInfoTemplate(
                    $seminarData['contactPerson'],
                    $seminarData['contactEmail'],
                    $seminarData['contactPhone'],
                )
            );
        }

        if (!$seminarElement->save()->isSuccess()) {
            throw new RuntimeException('Failed to save seminar element');
        }

        return $this->prepareSeminarElement($seminarElement);
    }

    /**
     * @return EO_ElementSeminars_Collection
     * @throws ArgumentException
     * @throws ObjectException
     * @throws SystemException
     */
    private function getSeminarsElements(): EO_ElementSeminars_Collection
    {
        return ElementSeminarsTable::query()
            ->setSelect(
                [
                    'ID',
                    'NAME',
                    'CITY.ELEMENT.ID',
                    'CITY.ELEMENT.NAME',
                    'FORMAT.ELEMENT.ID',
                    'FORMAT.ELEMENT.NAME',
                    'TEACHER_LINK.ELEMENT.ID',
                    'TEACHER_LINK.ELEMENT.NAME',
                    'SERVICES_LINK.ELEMENT.ID',
                    'SERVICES_LINK.ELEMENT.NAME',
                    'DATE',
                    'PLACE.VALUE',
                    'TIME_START.VALUE',
                    'TIME_FINISH.VALUE',
                    'ACADEMIC_HOURS.VALUE',
                    'PROGRAM_PARTS.ELEMENT.ID',
                    'PROGRAM_PARTS.ELEMENT.NAME',
                    'PROGRAM_PARTS.ELEMENT.TIME.VALUE',
                    'PLACE_COORDS.VALUE',
                    'PRICE.VALUE',
                    'TAX.VALUE',
                    'TAX_AMOUNT.VALUE',
                    'PROGRAM_FILE.FILE',
                    'SIGN_UP_SEMINAR_DESC_1.VALUE',
                    'CONTACT_FIO.VALUE',
                    'CONTACT_PHONE.VALUE',
                    'CONTACT_EMAIL.VALUE',
                ]
            )
            ->where('ACTIVE', 'Y')
            ->fetchCollection();
    }

    private function prepareSeminarElement(mixed $seminarsElement): array
    {
        /** @var bool $isWebinar */
        $isWebinar = filter_var(
            $seminarsElement?->getIsWebinar()?->getItem()?->getValue(),
            FILTER_VALIDATE_BOOLEAN
        );

        $startSeminarTime = $this->stringTool->reformatTime($seminarsElement?->getTimeStart()?->getValue());
        $finishSeminarTime = $this->stringTool->reformatTime($seminarsElement?->getTimeFinish()?->getValue());

        return [
            'siteId' => $seminarsElement->getId(),
            'name' => $seminarsElement->getName(),
            'fullName' => $seminarsElement->getPreviewText(),
            'description' => $seminarsElement->getDetailText(),
            'city' => $seminarsElement->getCity()?->getElement()?->getName(),
            'webinar' => $isWebinar ?? null,
            'location' => $seminarsElement->getPlaceCoords()?->getValue(),
            'date' => $this->stringTool->reformatDate($seminarsElement->getDate()?->getValue()),
            'startTime' => $startSeminarTime,
            'finishTime' => $finishSeminarTime,
            'academicHours' => $seminarsElement->getAcademicHours()?->getValue(),
            'price' => $seminarsElement->getPrice()?->getValue(),
            'tax' => $seminarsElement->getTax()?->getValue(),
            'taxAmount' => $seminarsElement->getTaxAmount()?->getValue(),
            'program' => $this->prepareProgramParts($seminarsElement->getProgramParts()) ?? [],
            'files' => $this->prepareFiles($seminarsElement->getProgramFile()) ?? [],
            'contactPerson' => $seminarsElement->getContactFio()?->getValue(),
            'contactPhone' => $seminarsElement->getContactPhone()?->getValue(),
            'contactEmail' => $seminarsElement->getContactEmail()?->getValue(),
        ];
    }


    /**
     * @param $programPartsElements
     * @return array
     */
    private function prepareProgramParts($programPartsElements): array
    {
        foreach ($programPartsElements as $programPart) {
            $programPart = $programPart->getElement();

            $rawTime = $programPart?->getTime()?->getValue();

            [$startTime, $finishTime] = $this->stringTool->getPreparedTime($rawTime) ?? [null, null];

            $programParts[] = [
                'eventId' => $programPart->getId(),
                'startTime' => $startTime,
                'finishTime' => $finishTime,
                'event' => $programPart->getName(),
            ];
        }

        return $programParts ?? [];
    }


    /**
     * @param $documentElements
     * @return array
     */
    private function prepareFiles($documentElements): array
    {
        foreach ($documentElements as $document) {
            $files[] = FileManager::getInstance()->makeFileSrc($document->getFile(), true);
        }

        return $files ?? [];
    }


    /**
     * @param $elementId
     * @return EO_ElementSeminars|null
     * @throws RuntimeException
     */
    public function getSeminarElementById($elementId): ?EO_ElementSeminars
    {
        try {
            return ElementSeminarsTable::getById($elementId)->fetchObject();
        } catch (ObjectException | ArgumentException | SystemException $e) {
            throw new RuntimeException('Failed to get seminar element. ' . $e->getMessage());
        }
    }

    /**
     * @param string $name
     * @return EO_ElementSeminarsCities|null
     * @throws RuntimeException
     */
    public function getCityElementByName(string $name): ?EO_ElementSeminarsCities
    {
        try {
            return ElementSeminarsCitiesTable::query()
                ->setSelect([
                    'ID',
                    'NAME',
                ])
                ->where('NAME', $name)
                ->fetchObject();
        } catch (ObjectException | ArgumentException | SystemException $e) {
            throw new RuntimeException('Failed to get seminars city element. ' . $e->getMessage());
        }
    }


    /**
     * @param array $programData
     * @return EO_ElementSeminarsProgram_Collection|null
     * @throws RuntimeException
     */
    public function getOrCreateProgramPartsElements(array $programData): ?EO_ElementSeminarsProgram_Collection
    {
        if (!$programData) {
            throw new RuntimeException('Failed to get program parts elements: \'programData\' array empty');
        }

        try {
            $programPartsElements = ElementSeminarsProgramTable::query()
                ->addSelect('ID')
                ->whereIn('ID', array_column($programData, 'eventId'))
                ->fetchCollection();
        } catch (ObjectException | ArgumentException | SystemException $e) {
            throw new RuntimeException('Failed to get program parts. ' . $e->getMessage());
        }

        foreach ($programData['program'] as $programPart) {
            if (!in_array($programPart['eventId'], array_values($programPartsElements->getIdList()), true)) {
                $programPartElement = new EO_ElementSeminarsProgram();
                $programPartElement->setTime($programPart['startTime'] . '-' . $programPart['finishTime']);
                $programPartElement->setName($programPart['event']);

                $programPartsElements->add($programPartElement);

                if (!$programPartElement->save()->isSuccess()) {
                    throw new RuntimeException('Failed to save program part. ');
                }
            }
        }

        return $programPartsElements ?? null;
    }

    /**
     * @param $fileData
     * @return EO_File|null
     * @throws RuntimeException
     */
    public function getFileElement($fileData): ?int
    {
        $fileIds = array_column(FileManager::getInstance()->saveFilesFromArray($fileData), 'ID');
        return $fileIds[0] ?? null;
    }


    /**
     * @param $webinar
     * @return EO_PropertyEnumeration|null
     * @throws RuntimeException
     */
    public function getIsWebinarEnumByValue($webinar): ?EO_PropertyEnumeration
    {
        try {
            return PropertyEnumerationTable::query()
                ->setSelect(['ID', 'XML_ID'])
                ->where('XML_ID', $webinar)
                ->fetchObject();
        } catch (ObjectException | ArgumentException | SystemException $e) {
            throw new RuntimeException('Failed to get file element. ' . $e->getMessage());
        }
    }
}
