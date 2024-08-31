<?php
namespace DockerVersions\Services;

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once("$documentRoot/plugins/docker.versions/server/services/Releases.php");
require_once("$documentRoot/plugins/docker.versions/server/models/Release.php");
require_once("$documentRoot/plugins/docker.versions/server/models/Container.php");
require_once("$documentRoot/plugins/docker.versions/server/helpers/Publish.php");

use DockerVersions\Models\Container;
use DockerVersions\Services\Releases;
use DockerVersions\Models\Release;
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
            if ($container->isGithubRepository()) {
                $releases = new Releases($container, $container->repositorySource);
                $releases->pullReleases();
                $secondaryReleases = null;
                if (!empty($container->repositorySecondarySource)) {
                    $secondaryReleases = new Releases($container, $container->repositorySecondarySource);
                    $secondaryReleases->pullReleases();
                }

                $currentImageSourceTag = $container->imageVersion;
                $currentImageCreatedAt = $container->imageCreatedAt;

                if (!$currentImageCreatedAt) {
                    Publish::message("<h3>WARNING: No " . Container::$LABELS["created"] . " image label found</h3>");
                    Publish::message("<p>Please request that " . Container::$LABELS["created"] . " is added by image creator for the best experience.</p>");
                }

                if (!$releases->hasReleases()) {
                    Publish::message("<p>Falling back to last 30 tags for information for $container->repositorySource</p>");
                    $releases->pullTags();
                }

                if ($secondaryReleases) {
                    if (!$secondaryReleases->hasReleases() && !empty($container->repositorySecondarySource)) {
                        Publish::message("<p>Falling back to last 30 tags for information for $container->repositorySecondarySource </p>");
                        $secondaryReleases->pullTags();
                    }
                    $secondaryReleases->organiseReleases();
                }

                $releases->organiseReleases();

                if (!$releases->hasReleases()) {
                    $html = '<pre class="error" style="overflow-y: scroll; height:400px;">';
                    $html .= "<h3>Error no releases found!</h3>" .
                        "<a href=\"$releases->releasesUrl\" target=\"blank\">$releases->releasesUrl</a>" .
                        "<div>" . Container::$LABELS["source"] . "=" . $releases->repositorySource . "</div>" .
                        "<div>" . Container::$LABELS["version"] . "=" . $container->imageVersion . "</div>" .
                        "<div>" . Container::$LABELS["created"] . "=" . $container->imageCreatedAt . "</div>" .
                        "<br/>";
                    $html .= "</pre>";
                    Publish::message($html);
                } else {
                    $firstRelease = $releases->first();

                    if (!$currentImageCreatedAt) {
                        Publish::message("<p>Falling back to displaying all " . $firstRelease->type . "s</p>");
                        $currentImageCreatedAt = (new DateTime($releases->last()->createdAt))->format('Y-m-d H:i:s');
                        $currentImageSourceTag = $releases->last()->tagName;
                    } else {
                        $currentImageCreatedAt = (new DateTime($currentImageCreatedAt))->format('Y-m-d H:i:s');
                    }

                    $latestImageCreatedAt = (new DateTime($firstRelease->createdAt))->format('Y-m-d H:i:s');

                    Publish::message("<h3>$container->name</h3>");
                    Publish::message("<h3>$currentImageSourceTag ($currentImageCreatedAt) ---->  {$firstRelease->tagName} ({$latestImageCreatedAt})</h3>");
                    Publish::message("<a href=\"$releases->releasesUrl\" target=\"blank\">Url for primary changelog information</a>");
                    if ($secondaryReleases) {
                        Publish::message("<br><a href=\"$secondaryReleases->releasesUrl\" target=\"blank\">Url for secondary changelog information</a>");
                    }

                    Publish::message('<pre class="releases" style="overflow-y: scroll; height:400px; border: 2px solid #000; padding: 10px;border-radius: 5px;background-color: #f9f9f9; "></pre>');
                    // TODO: fall back to secondary if no releases
                    foreach ($releases->releases as $release) {
                        $releaseCreatedAt = (new DateTime($release->createdAt))->format('Y-m-d H:i:s');
                        if (strtotime($currentImageCreatedAt) >= strtotime($releaseCreatedAt)) {
                            continue;
                        }
                        Publish::message("<hr><h3 class='releasesInfo'>Primary Source</h3>");
                        Publish::message("<a class='releasesInfo' target=\"blank\" href=\"{$release->htmlUrl}\">{$release->tagName} ($releaseCreatedAt)</a><br><br>");
                        if (!empty($release->extraReleases)) {
                            Publish::message("<h3 class='releasesInfo'>Duplicate changelogs</h3>");
                        }
                        foreach ($release->extraReleases as $extraRelease) {
                            Publish::message("<a class='releasesInfo' target=\"blank\" href=\"{$extraRelease->htmlUrl}\">{$extraRelease->tagName} (" .
                                (new DateTime($extraRelease->createdAt))->format('Y-m-d H:i:s') . ")</a><br><br>");
                        }
                        Publish::message("<div class='releasesInfo'>{$release->getBody()}</div><br>");

                        if ($secondaryReleases) {
                            $secondaryReleaseMatches = array_filter($secondaryReleases->releases, function (Release $secondaryRelease) use ($release) {
                                $tagA = filter_var($release->tagName, FILTER_SANITIZE_NUMBER_INT);
                                $tagB = filter_var($secondaryRelease->tagName, FILTER_SANITIZE_NUMBER_INT);

                                var_dump($tagA, $tagB);

                                return str_contains($tagA, $tagB) || str_contains($tagB, $tagA) ||
                                    (abs(strtotime($release->createdAt) - strtotime($secondaryRelease->createdAt)) < (60 * 60 * 6));
                            });
                            // TODO: sonarr only found a amtch for first release double check mayber we needa be mroe aggressive 

                            if (!empty($secondaryReleaseMatches)) {
                                Publish::message("<h3 class='releasesInfo'>Secondary Source</h3>");
                            }

                            foreach ($secondaryReleaseMatches as $secondaryRelease) {
                                if (!empty($secondaryRelease->extraReleases)) {
                                    Publish::message("<h3 class='releasesInfo'>Duplicate changelogs</h3>");
                                }
                                foreach ($secondaryRelease->extraReleases as $extraRelease) {
                                    Publish::message("<a class='releasesInfo' target=\"blank\" href=\"{$extraRelease->htmlUrl}\">{$extraRelease->tagName} (" .
                                        (new DateTime($extraRelease->createdAt))->format('Y-m-d H:i:s') . ")</a><br><br>");
                                }
                                Publish::message("<a class='releasesInfo' target=\"blank\" href=\"{$secondaryRelease->htmlUrl}\">{$secondaryRelease->tagName} (" .
                                    (new DateTime($secondaryRelease->createdAt))->format('Y-m-d H:i:s') . ")</a><br><br>");
                                Publish::message("<div class='releasesInfo'>{$secondaryRelease->getBody()}</div><br>");
                            }
                        }
                    }
                }
            } else {
                Publish::message("<h3>Error only github repositories are supported at this time!</h3>");
            }
        }
    }
}

?>