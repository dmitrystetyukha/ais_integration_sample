<?php

namespace GGE\Ais\Utils;

class StringFormatTool
{
    private const DATE_FORMAT = 'd.m.Y';
    private const SIGN_UP_SEMINAR_DESC_1_TEMPLATE = <<<'TEPMLATE'
<p>
	 Контакты:<br>
	 %s<br>
 <a href="mailto:%s">%s</a><br>
	 %s
</p>
TEPMLATE;

    /**
     * @param string $target
     * @return string|null
     */
    public function recognizeCityName(string $target): ?string
    {
        preg_match('/\b[А-ЯЁ][а-яё\-]*\b/u', $target, $matches);

        return $matches[0] ?? null;
    }

    public function reformatDate(string $rawDate): ?string
    {
        $date = date_create($rawDate);

        return date_format($date, self::DATE_FORMAT);
    }

    public function reformatTime(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        if (!preg_match('/^(\d{2}):(\d{2})$/', $time)) {
            [$hours, $minutes] = explode('.', $time);
            $time = sprintf('%02d:%02d', $hours, $minutes);
        }

        return $time;
    }

    /**
     * разбивает строку типа "H.i-H.i"
     * на 2 строки типа "H:i"
     *
     * @param string|null $time
     * @return array
     */
    public function getPreparedTime(?string $time): array
    {
        $explodedTime = explode('-', $time);

        $startTime = $this->reformatTime($explodedTime[0]);
        $finishTime = $this->reformatTime($explodedTime[1]);

        return [$startTime, $finishTime];
    }

    /**
     * @param string $name
     * @param string $email
     * @param string $phone
     * @return string
     */
    public function getFilledContactInfoTemplate(string $name, string $email, string $phone): string
    {
        return sprintf(self::SIGN_UP_SEMINAR_DESC_1_TEMPLATE, $name, $email, $email, $phone);
    }
}
