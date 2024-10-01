<?php
namespace DockerVersions\Helpers;
use DateTimeZone;
use DateTime;

class Generic
{

    /**
     * Summary of convertToDateString
     * @param string $dateString
     * @param bool $allowNow
     * @return string|int|null
     */
    public static function convertToDateString(string|int|null $dateString, bool $allowNow = false): string
    {
        if (!$dateString && !$allowNow) {
            return "";
        }
        $dateString = is_numeric($dateString) ? "@{$dateString}" : $dateString;
        return (new DateTime($dateString))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') ?? "";
    }
}