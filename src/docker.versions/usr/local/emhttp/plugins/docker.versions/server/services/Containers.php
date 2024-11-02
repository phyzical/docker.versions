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
     * Get the container change logs.
     * @return void
     * @param string[] $containers
     */
    static function getChangeLogs($containers): void
    {
        $containers = self::getAll($containers);

        foreach ($containers as $container) {
            if ($container->isGithubRepository()) {
                // TODO: we can make it support more than 2 sources by adjusting the logic here to be an array of releaseSources
                $releases = new Releases($container, $container->repositorySource, $container->imageSourceType);
                $releases->pullAllReleases();
                $secondaryReleases = null;
                if (!empty($container->repositorySecondarySource)) {
                    $secondaryReleases = new Releases($container, $container->repositorySecondarySource, $container->sourceType);
                    $secondaryReleases->pullAllReleases();
                }

                $currentImageSourceTag = $container->imageVersion;
                $currentImageCreatedAt = $container->imageCreatedAt;

                Publish::message("<details style='display: none' class='warningsInfo' open><summary>All Warnings:</summary><ul></ul></details>");

                if (!$currentImageCreatedAt) {
                    Publish::message("<li class='warnings'>No " . Container::$LABELS["created"] . " image label found</li>");
                    Publish::message("<li class='warnings'>Please request that " . Container::$LABELS["created"] . " is added by image creator for the best experience.</li>");
                    if ($container->containerCreatedDate) {
                        Publish::message("<li class='warnings'>Falling back to created date! ({$container->containerCreatedDate})</li>");
                        $currentImageCreatedAt = $container->containerCreatedDate;
                    }
                }

                $hasPrimaryReleases = $releases->hasReleases();
                $hasSecondaryReleases = $secondaryReleases && $secondaryReleases->hasReleases();
                $allReleases = $hasPrimaryReleases ? $releases->releases : [];
                $allSecondaryReleases = $secondaryReleases ? $secondaryReleases->releases : [];
                $firstRelease = $hasPrimaryReleases ? $releases->first() : null;
                $lastRelease = $hasPrimaryReleases ? $releases->last() : null;
                $releasesUrl = $hasPrimaryReleases ? $releases->releasesUrl : "";

                if (!$hasPrimaryReleases && !$hasSecondaryReleases) {
                    Publish::message("<li class='warnings'>Error no releases found!</li>");
                    if ($secondaryReleases) {
                        Publish::message("<li class='warnings'>Error no secondary releases found either!!</li>");
                    }
                    Publish::message("<li class='warnings'><a href=\"$releases->releasesUrl\" target=\"blank\">$releases->releasesUrl</a></li>");
                    Publish::message("<li class='warnings'>" . Container::$LABELS["source"] . "=" . $releases->repositorySource . "</li>");
                    Publish::message("<li class='warnings'>" . Container::$LABELS["version"] . "=" . $container->imageVersion . "</li>");
                    Publish::message("<li class='warnings'>" . Container::$LABELS["created"] . "=" . $container->imageCreatedAt . "</li>");
                    Publish::message("<li class='warnings'>" . Container::$LABELS["secondarySource"] . "=" . $container->repositorySecondarySource . "</li>");
                    continue;
                }

                // If no primary found make secondary primary
                if (!$hasPrimaryReleases && $hasSecondaryReleases) {
                    Publish::message("<li class='warnings'>No primary source releases found, falling back to secondary</li>");
                    $firstRelease = $secondaryReleases->first();
                    $lastRelease = $secondaryReleases->last();
                    $releasesUrl = $secondaryReleases->releasesUrl;
                    $allReleases = $secondaryReleases->releases;
                    $allSecondaryReleases = [];
                }

                if (!$currentImageCreatedAt && !empty($allReleases)) {
                    Publish::message("<li class='warnings'>No org.opencontainers.image.created, Falling back to displaying all " . $firstRelease->type . "s</li>");
                    $currentImageCreatedAt = $lastRelease->createdAt;
                    $currentImageSourceTag = $lastRelease->tagName;
                }

                if (!empty($allReleases)) {
                    $latestImageCreatedAt = $firstRelease->createdAt;

                    Publish::message("<h3>Container: $container->name</h3>");
                    Publish::message("<h3>$currentImageSourceTag ($currentImageCreatedAt) ---->  {$firstRelease->tagName} ({$latestImageCreatedAt})</h3>");
                    Publish::message("<a href=\"$releasesUrl\" target=\"blank\">Url for changelog information</a>");
                    if (!empty($allSecondaryReleases)) {
                        Publish::message("<br><a href=\"$secondaryReleases->releasesUrl\" target=\"blank\">Url for secondary changelog information</a>");
                    }

                    Publish::message('<pre class="releases" style="display: none; overflow-y: scroll; height:400px; border: 2px solid #000; padding: 10px;border-radius: 5px;background-color: #f9f9f9; "></pre>');

                    $releases = self::filterReleasesByDate($allReleases, $currentImageCreatedAt);

                    foreach ($releases as $primaryRelease) {
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
                                "<summary>Changelog Notes</summary>",
                                "<div>{$primaryRelease->getBody()}</div>",
                                "</details>"
                            ]
                        );

                        if (!empty($allSecondaryReleases)) {
                            $secondaryReleaseMatches = self::filterSecondaryReleases($allSecondaryReleases, $primaryRelease);

                            if (!empty($secondaryReleaseMatches)) {
                                $detailsChunks = array_merge(
                                    $detailsChunks,
                                    [
                                        "<details open>",
                                        "<summary>Secondary Source Changelogs</summary>"
                                    ],
                                );

                                foreach ($secondaryReleaseMatches as $secondaryRelease) {
                                    if (!empty($secondaryRelease->extraReleases)) {
                                        $detailsChunks = array_merge(
                                            $detailsChunks,
                                            [
                                                "<details open>",
                                                "<summary>Duplicate Secondary changelogs</summary>"
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
            } else {
                Publish::message("<h3>Error only github repositories are supported at this time!</h3>");
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