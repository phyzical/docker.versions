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

        // Publish::message(json_encode($dockerClient->getDockerJSON("/containers/json?all=1")));

        $containers = array_filter($dockerClient->getDockerJSON("/containers/json?all=1"), function ($ct) use ($containers) {
            return in_array(str_replace("/", "", $ct['Names'][0]), $containers) && $ct['Labels'][Container::$LABELS["unraidManaged"]];
        });

        return array_map(function ($container) {
            return new Container($container);
        }, $containers);
    }

    /**
     * Summary of pullContainerReleases
     * @param Container $container
     * 
     * @return Releases[]
     */
    static function pullContainerReleases(Container $container)
    {
        $releaseSourceTypes = array_merge([$container->imageSourceType], explode(",", $container->sourceType));
        $releaseSources = array_merge([$container->repositorySource], explode(",", $container->repositorySecondarySource));

        array_walk($releaseSources, function ($releaseSource, $index) use ($container, $releaseSourceTypes, &$releaseSources) {
            if (empty($releaseSource)) {
                $releaseSources[$index] = null;
            } else {
                $releases = new Releases($container, $releaseSource, $releaseSourceTypes[$index] ?? '');
                $releases->pullAllReleases();
                $releaseSources[$index] = $releases;
            }
        });

        $releaseSources = array_filter($releaseSources);

        $hasReleases = false;
        foreach ($releaseSources as $release) {
            if ($release->hasReleases()) {
                $hasReleases = true;
                break;
            }
        }

        if (!$hasReleases) {
            foreach ($releaseSources as $release) {
                Publish::message("<li class='warnings'>No releases found for '" . $release->repositorySource . "' !</li>");
                Publish::message("<li class='warnings'><a href=\"$release->releasesUrl\" target=\"blank\">$release->releasesUrl</a></li>");
            }
            Publish::message("<li class='warnings'>" . Container::$LABELS["source"] . "=" . $releaseSources[0]->repositorySource . "</li>");
            Publish::message("<li class='warnings'>" . Container::$LABELS["version"] . "=" . $container->imageVersion . "</li>");
            Publish::message("<li class='warnings'>" . Container::$LABELS["created"] . "=" . $container->imageCreatedAt . "</li>");
            Publish::message("<li class='warnings'>" . Container::$LABELS["secondarySource"] . "=" . $container->repositorySecondarySource . "</li>");
            return [];
        }

        return array_filter($releaseSources, function ($releases) {
            $hasReleases = $releases->hasReleases();
            if (!$hasReleases) {
                Publish::message("<li class='warnings'>No releases found for '" . $releases->repositorySource . "' !</li>");
            }
            return $hasReleases;
        });
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
            Publish::message("<details style='display: none' class='warningsInfo' open><summary>All Warnings:</summary><ul></ul></details>");

            $releases = self::pullContainerReleases($container);

            if (empty($releases)) {
                continue;
            }

            $firstReleases = array_shift($releases);
            $firstRelease = $firstReleases->first();
            $lastRelease = $firstReleases->last();
            $currentImageSourceTag = $container->imageVersion;
            $currentImageCreatedAt = $container->imageCreatedAt;

            if (!$currentImageCreatedAt) {
                Publish::message("<li class='warnings'>No " . Container::$LABELS["created"] . " image label found</li>");
                Publish::message("<li class='warnings'>Please request that " . Container::$LABELS["created"] . " is added by image creator for the best experience.</li>");
                if ($container->containerCreatedDate) {
                    Publish::message("<li class='warnings'>Falling back to created date! ({$container->containerCreatedDate})</li>");
                    $currentImageCreatedAt = $container->containerCreatedDate;
                }

                if (!$currentImageCreatedAt && !empty($firstReleases->releases)) {
                    Publish::message("<li class='warnings'>No org.opencontainers.image.created, Falling back to displaying all " . $firstRelease->type . "s</li>");
                    $currentImageCreatedAt = $lastRelease->createdAt;
                    $currentImageSourceTag = $lastRelease->tagName;
                }
            }

            if (!empty($firstReleases->releases)) {
                $latestImageCreatedAt = $firstRelease->createdAt;

                Publish::message("<h3>Container: $container->name</h3>");
                Publish::message("<h3>$currentImageSourceTag ($currentImageCreatedAt) ---->  {$firstRelease->tagName} ({$latestImageCreatedAt})</h3>");
                Publish::message("<a href=\"$firstReleases->releasesUrl\" target=\"blank\">Url for changelog information id:1</a>");
                $index = 2;
                foreach ($releases as $release) {
                    Publish::message("<br><a href=\"$release->releasesUrl\" target=\"blank\">Url for changelog information id:$index</a>");
                    $index++;
                }
                Publish::message('<pre class="releases" style="display: none; overflow-y: scroll; height:400px; border: 2px solid #000; padding: 10px;border-radius: 5px;background-color: #f9f9f9; "></pre>');

                $primaryReleases = self::filterReleasesByDate($firstReleases->releases, $currentImageCreatedAt);

                foreach ($primaryReleases as $primaryRelease) {
                    $detailsChunks = [
                        "<details style='text-wrap:wrap;' class='releasesInfo' open>",
                        "<summary><a target=\"blank\" href=\"{$primaryRelease->htmlUrl}\">{$primaryRelease->tagName} ($primaryRelease->createdAt)</a></summary>",
                    ];
                    if (!empty($primaryRelease->extraReleases)) {
                        $filteredDuplicates = array_filter($primaryRelease->extraReleases, function ($extraRelease) use ($currentImageCreatedAt, $currentImageSourceTag) {
                            return strtotime($extraRelease->createdAt) > strtotime($currentImageCreatedAt) && $extraRelease->tagName != $currentImageSourceTag;
                        });
                        if (!empty($filteredDuplicates)) {
                            $detailsChunks = array_merge(
                                $detailsChunks,
                                [
                                    "<details open>",
                                    "<summary>Duplicate changelogs</summary>"
                                ],
                                array_map(
                                    function ($extraRelease) {
                                        return "<a target=\"blank\" href=\"{$extraRelease->htmlUrl}\">{$extraRelease->tagName} ({$extraRelease->createdAt})</a>";
                                    },
                                    $filteredDuplicates
                                ),
                                ["</details>"]
                            );
                        }
                    }

                    $detailsChunks = array_merge(
                        $detailsChunks,
                        [
                            "<details open>",
                            "<summary>Changelog Notes id:1</summary>",
                            "<div>{$primaryRelease->getBody()}</div>",
                            "</details>"
                        ]
                    );

                    $index = 2;
                    foreach ($releases as $release) {
                        $secondaryReleaseMatches = self::filterSecondaryReleases($release->releases, $primaryRelease);
                        if (!empty($secondaryReleaseMatches)) {
                            $detailsChunks = array_merge(
                                $detailsChunks,
                                [
                                    "<details open>",
                                    "<summary>Secondary Source Changelogs id:$index</summary>"
                                ],
                            );

                            foreach ($secondaryReleaseMatches as $secondaryRelease) {
                                if (!empty($secondaryRelease->extraReleases)) {
                                    $detailsChunks = array_merge(
                                        $detailsChunks,
                                        [
                                            "<details open>",
                                            "<summary>Duplicate Secondary changelogs id:$index</summary>"
                                        ],
                                        array_map(
                                            function ($extraRelease) {
                                                return "<a target=\"blank\" href=\"{$extraRelease->htmlUrl}\">{$extraRelease->tagName} ({$extraRelease->createdAt})</a>";
                                            },
                                            $secondaryRelease->extraReleases
                                        ),
                                        ["</details>"]
                                    );
                                }

                                $detailsChunks = array_merge(
                                    $detailsChunks,
                                    [
                                        "<a target=\"blank\" href=\"{$secondaryRelease->htmlUrl}\">{$secondaryRelease->tagName} (" . $secondaryRelease->createdAt . ")</a>",
                                        "<div>{$secondaryRelease->getBody()}</div>",
                                    ]
                                );

                            }

                            $detailsChunks = array_merge(
                                $detailsChunks,
                                [
                                    "</details>"
                                ]
                            );
                        }
                        $index++;
                    }

                    $detailsChunks = array_merge(
                        $detailsChunks,
                        [
                            "</details>",
                            "<hr>"
                        ]
                    );
                    Publish::message(implode("<br>", $detailsChunks));
                }
            }
        }
    }

    /**
     * Filter down releases by date
     * 
     * @return Release[]
     * @param Release[] $releases
     * @param string $date
     */
    //  TODO: if we can ever get the a good fallback for createed date lets filterByRelease in organise and then we can improve the pull flow to fallback better
    private static function filterReleasesByDate(array $releases, string $date): array
    {
        $allFilteredReleases = array_filter($releases, function ($release) use ($date) {
            return strtotime($date) < strtotime($release->createdAt);
        });

        if (empty($allFilteredReleases)) {
            Publish::message("<li class='warnings'>No releases found given the source date of {$date}, Falling back to last 6 months of releases</li>");
            $allFilteredReleases = array_filter($releases, function ($release) {
                return (new DateTime())->modify('-6 months')->getTimestamp() < strtotime($release->createdAt);
            });
        }

        if (empty($allFilteredReleases)) {
            Publish::message("<li class='warnings'>No releases found in last 6 months, Falling back to all releases</li>");
            $allFilteredReleases = $releases;
        }

        return $allFilteredReleases;
    }

    /**
     * Filter down secondary releases
     * 
     * @return Release[]
     * @param Release[] $secondaryReleases
     * @param Release $primaryRelease
     * @param string $date
     */
    private static function filterSecondaryReleases(array $secondaryReleases, Release $primaryRelease): array
    {
        return array_filter($secondaryReleases, function (Release $secondaryRelease) use ($primaryRelease) {
            $primaryTag = filter_var($primaryRelease->tagName, FILTER_SANITIZE_NUMBER_INT);
            $secondaryTag = filter_var($secondaryRelease->tagName, FILTER_SANITIZE_NUMBER_INT);
            $tagsAreClose = str_contains($primaryTag, $secondaryTag) || str_contains($secondaryTag, $primaryTag);

            $allDates = [
                ...array_map(
                    function ($extraRelease) {
                        return $extraRelease->createdAt;
                    },
                    $primaryRelease->extraReleases
                ),
                $primaryRelease->createdAt
            ];

            $within7daysMatch = !empty(array_filter(
                $allDates,
                function ($createdAt) use ($secondaryRelease) {
                    return abs((strtotime($createdAt) - strtotime($secondaryRelease->createdAt))) < (60 * 60 * 24 * 7);
                }
            ));

            $within2daysMatch = !empty(array_filter(
                $allDates,
                function ($createdAt) use ($secondaryRelease) {
                    return abs((strtotime($createdAt) - strtotime($secondaryRelease->createdAt))) < (60 * 60 * 24 * 2);
                }
            ));

            // Titles are close and within 7 days and not the same release or within 2 days 
            return ($tagsAreClose && $within7daysMatch) || $within2daysMatch;
        });
    }
}

?>