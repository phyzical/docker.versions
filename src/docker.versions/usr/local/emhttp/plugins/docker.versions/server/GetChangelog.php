<?php

$configDir = "/boot/config/plugins/docker.versions";
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
$sourceDir = "$documentRoot/plugins/docker.versions";
require_once("$documentRoot/webGui/include/Helpers.php");
require_once("$sourceDir/server/services/Containers.php");
require_once("$sourceDir/server/helpers/Publish.php");

use DockerVersions\Services\Containers;
use DockerVersions\Helpers\Publish;

try {
    Containers::getChangeLogs($_GET["cts"]);
} catch (Exception $e) {
    Publish::message("<h3>Error: {$e->getMessage()}</h3>");
}

?>