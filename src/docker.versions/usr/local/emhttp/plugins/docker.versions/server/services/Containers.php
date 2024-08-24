<?php
namespace DockerVersions\Services;

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once("$documentRoot/plugins/docker.versions/server/Releases.php");
require_once("$documentRoot/plugins/docker.versions/server/models/Container.php");

use DockerVersions\Models\Container;

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
            return in_array(str_replace("/", "", $ct['Names'][0]), $_GET["cts"]) && $ct['Labels']['net.unraid.docker.managed'];
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
            $repositorySource = $container->repositorySource;
            $releases = new Releases($repositorySource);

            if ($container->isGithubRepository()) {
                $currentImageSourceTag = $container->imageVersion;
                $currentImageCreatedAt = $container->imageCreatedAt;
                $releases->pullReleases();

                if (!$releases->hasReleases()) {
                    echo "<p>Falling back to tags for information</p>";
                    $releases->pullTags();
                }

                $releasesUrl = $releases->releasesUrl;

                $releases->organiseReleases($currentImageSourceTag);

                if (!$releases->hasReleases()) {
                    echo '<pre style="overflow-y: scroll; height:400px;">';
                    echo "<h3>Error no releases found!</h3>" .
                        "<a href=\"$releasesUrl\" target=\"blank\">$releasesUrl</a>" .
                        "<div>" . Container::LABELS->source . "=$repositorySource</div>" .
                        "<div>" . Container::LABELS->version . "=$currentImageSourceTag</div>" .
                        "<div>" . Container::LABELS->createdAt . "=$currentImageCreatedAt</div>" .
                        "<br/>";
                    echo "</pre>";
                } else {
                    $firstRelease = $releases->first();
                    $latestImageCreatedAt = (new DateTime($firstRelease->createdAt))->format('Y-m-d H:i:s');

                    if (!$currentImageCreatedAt) {
                        echo "<h3>WARNING: No " . Container::LABELS->createdAt . " image label found</h3>";
                        echo "<p>Please request that " . Container::LABELS->createdAt . " is added by image creator for the best experience.</p>";
                        echo "<p>Falling back to displaying all " . $firstRelease->type . "s</p>";
                        $lastRelease = $releases->last();
                        $currentImageCreatedAt = (new DateTime($lastRelease->createdAt))->format('Y-m-d H:i:s');
                        $currentImageSourceTag = $lastRelease->tagName;
                    } else {
                        $currentImageCreatedAt = (new DateTime($currentImageCreatedAt))->format('Y-m-d H:i:s');
                    }

                    echo "<h3>$currentImageSourceTag ($currentImageCreatedAt) ---->  {$firstRelease->tagName} ({$latestImageCreatedAt})</h3>";
                    echo "<a href=\"$releasesUrl\" target=\"blank\">Used this url for changelog information</a>";

                    echo '<pre style="overflow-y: scroll; height:400px;">';
                    foreach ($releases->releases as &$item) {
                        if ($latestImageCreatedAt <= $currentImageCreatedAt) {
                            continue;
                        }
                        $latestImageCreatedAt = (new DateTime($item->createdAt))->format('Y-m-d H:i:s');
                        echo "<a target=\"blank\" href=\"{$item->htmlUrl}\">{$item->tagName} ($latestImageCreatedAt)</a>" .
                            "<div>{$item->body}</div>" .
                            "<br/>";
                    }
                    echo "</pre>";
                }
            } else {
                echo "<h3>Error only github repositories are supported at this time!</h3>";
            }
        }
    }
}

?>