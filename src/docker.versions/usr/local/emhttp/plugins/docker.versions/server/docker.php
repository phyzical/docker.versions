<?php
$configDir = "/boot/config/plugins/docker.versions";
$sourceDir = "/usr/local/emhttp/plugins/docker.versions";
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once ("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once ("$documentRoot/webGui/include/Helpers.php");


function readInfo(string $type): array
{
    $info = [];
    global $dockerManPaths;
    global $driver;
    global $host;
    $dockerClient = new DockerClient();
    $DockerUpdate = new DockerUpdate();
    $cts = $dockerClient->getDockerJSON("/containers/json?all=1");
    foreach ($cts as $key => &$ct) {
        $ct['info'] = $dockerClient->getContainerDetails($ct['Id']);

        $ct['info']['Name'] = substr($ct['info']['Name'], 1);
        $ct['info']['Config']['Image'] = DockerUtil::ensureImageTag($ct['info']['Config']['Image']);
        $ct['info']['State']['Updated'] = $DockerUpdate->getUpdateStatus($ct['info']['Config']['Image']);
        $ct['info']['State']['manager'] = $ct['Labels']['net.unraid.docker.managed'] ?? false;
    }
    return $info;
}

?>