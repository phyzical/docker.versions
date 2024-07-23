<?php
$configDir = "/boot/config/plugins/docker.versions";
$sourceDir = "/usr/local/emhttp/plugins/docker.versions";
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once ("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once ("$documentRoot/webGui/include/Helpers.php");


$dockerClient = new DockerClient();
$dockerUpdate = new DockerUpdate();

$containers = array_filter($dockerClient->getDockerContainers(), function ($ct) {
    return array_find($_GET['cts'], function ($value) use ($ct) {
        return $value == $ct['Name'];
    });
});

echo "testing";
echo implode(",", array_map(function ($ct) {
    return $ct['Name'];
}, $containers));


?>