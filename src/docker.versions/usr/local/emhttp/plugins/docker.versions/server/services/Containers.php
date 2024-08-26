<?php
namespace DockerVersions\Services;

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once("$documentRoot/plugins/docker.versions/server/Releases.php");
require_once("$documentRoot/plugins/docker.versions/server/models/Container.php");

use DockerVersions\Models\Container;
use DateTime;

class Containers
{
    /**
     * Summary of getAll
     * @return Container[]
     */
    static function getAll()
    {
        $dockerClient = new DockerClient();

        $containers = array_filter($dockerClient->getDockerJSON("/containers/json?all=1"), function ($ct) {
            return in_array(str_replace("/", "", $ct['Names'][0]), $_GET["cts"]) && $ct['Labels'][Container::LABELS->unraidManaged];
        });

        return array_map(function ($container) {
            return new Container($container);
        }, $containers);
    }

    /**
     * Get the container change logs.
     * @return void
     */
    static function getChangeLogs(): void
    {
        $containers = self::getAll();

        foreach ($containers as $container) {
            $releases = new Releases($container);

            if ($container->isGithubRepository()) {
                $currentImageSourceTag = $container->imageVersion;
                $currentImageCreatedAt = $container->imageCreatedAt;
                $releases->pullReleases();

                if (!$releases->hasReleases()) {
                    echo "<p>Falling back to tags for information</p>";
                    $releases->pullTags();
                }

                $releases->organiseReleases();

                if (!$releases->hasReleases()) {
                    echo $releases->noReleasesHTML();
                } else {
                    if (!$currentImageCreatedAt) {
                        echo $releases->noCreatedAtHTML();
                        $currentImageCreatedAt = (new DateTime($releases->last()->createdAt))->format('Y-m-d H:i:s');
                        $currentImageSourceTag = $releases->last()->tagName;
                    } else {
                        $currentImageCreatedAt = (new DateTime($currentImageCreatedAt))->format('Y-m-d H:i:s');
                    }

                    echo $releases->releasesHTML($currentImageSourceTag, $currentImageCreatedAt);
                }
            } else {
                echo "<h3>Error only github repositories are supported at this time!</h3>";
            }
        }
    }
}

?>