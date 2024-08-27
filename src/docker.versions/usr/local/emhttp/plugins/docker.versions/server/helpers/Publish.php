<?php
namespace DockerVersions\Helpers;
$docroot = '/usr/local/emhttp';
require_once "$docroot/webGui/include/publish.php";

class Publish
{

    /**
     * Publish a message to the changeLog topic.
     * @param string $message
     */
    public static function message(string $message): void
    {
        publish('changelog', $message);
    }
}