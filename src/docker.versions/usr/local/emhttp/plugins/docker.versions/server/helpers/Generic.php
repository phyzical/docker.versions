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
     * @return string|null
     */
    public static function convertToDateString(string $dateString, bool $allowNow = false): string
    {
        $dateString = is_numeric($dateString) ? "@{$dateString}" : $dateString;
        if (!$dateString && !$allowNow) {
            return "";
        }
        return (new DateTime($dateString))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') ?? "";
    }
}