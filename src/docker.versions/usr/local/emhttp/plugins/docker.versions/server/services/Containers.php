<?php
namespace DockerVersions\Services;

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once("$documentRoot/plugins/docker.versions/server/services/Releases.php");
require_once("$documentRoot/plugins/docker.versions/server/models/Container.php");
require_once("$documentRoot/plugins/docker.versions/server/helpers/Publish.php");

use DockerVersions\Models\Container;
use DockerVersions\Services\Releases;
use DockerVersions\Helpers\Publish;
use DockerClient;
use DateTime;

class Containers
{
    /**
     * Summary of getAll
     * @param string[] $containers
     * @return Container[]
     */
    static function getAll($containers)
    {
        $dockerClient = new DockerClient();

        $containers = array_filter($dockerClient->getDockerJSON("/containers/json?all=1"), function ($ct) use ($containers) {
            return in_array(str_replace("/", "", $ct['Names'][0]), $containers) && $ct['Labels'][Container::$LABELS["unraidManaged"]];
        });

        return array_map(function ($container) {
            return new Container($container);
        }, $containers);
    }

    /**
     * Get the container change logs.
     * @return void
     * @param string[] $containers
     */
    static function getChangeLogs($containers): void
    {
        $containers = self::getAll($containers);

        foreach ($containers as $container) {
            $releases = new Releases($container);

            if ($container->isGithubRepository()) {
                $currentImageSourceTag = $container->imageVersion;
                $currentImageCreatedAt = $container->imageCreatedAt;

                if (!$currentImageCreatedAt) {
                    $releases->noCreatedAtHTML();
                }

                $releases->pullReleases();

                if (!$releases->hasReleases()) {
                    Publish::message("<p>Falling back to last 30 tags for information</p>");
                    $releases->pullTags();
                }

                $releases->organiseReleases();

                if (!$releases->hasReleases()) {
                    $releases->noReleasesHTML();
                } else {
                    if (!$currentImageCreatedAt) {
                        Publish::message("<p>Falling back to displaying all " . $releases->first()->type . "s</p>");
                        $currentImageCreatedAt = (new DateTime($releases->last()->createdAt))->format('Y-m-d H:i:s');
                        $currentImageSourceTag = $releases->last()->tagName;
                    } else {
                        $currentImageCreatedAt = (new DateTime($currentImageCreatedAt))->format('Y-m-d H:i:s');
                    }

                    $releases->releasesHTML($currentImageSourceTag, $currentImageCreatedAt);
                }
            } else {
                Publish::message("<h3>Error only github repositories are supported at this time!</h3>");
            }
        }
    }
}

?>