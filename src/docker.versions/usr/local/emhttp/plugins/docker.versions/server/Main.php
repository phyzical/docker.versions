<?php

$configDir = "/boot/config/plugins/docker.versions";
$sourceDir = "/usr/local/emhttp/plugins/docker.versions";
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/webGui/include/Helpers.php");
require_once("$documentRoot/plugins/docker.versions/server/services/Containers.php");

use DockerVersions\Services\Containers;

try {
    Containers::getChangeLogs();
} catch (Exception $e) {
    echo "<h3>Error: {$e->getMessage()}</h3>";
}

?>