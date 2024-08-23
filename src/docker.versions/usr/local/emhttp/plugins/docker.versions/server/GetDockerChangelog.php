<?php

$configDir = "/boot/config/plugins/docker.versions";
$sourceDir = "/usr/local/emhttp/plugins/docker.versions";
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once("$documentRoot/webGui/include/Helpers.php");
require_once("$documentRoot/plugins/docker.versions/server/Releases.php");

use DockerVersions\Releases;

/**
 * Get the repository source from the container.
 * @param object $container
 * @return string
 */
function getRepositorySource(array $container): string
{
    $currentImage = $container["Image"];
    $dockerTemplates = new DockerTemplates();
    $repositorySource = str_replace(".git", "", $container["Labels"]["org.opencontainers.image.source"] ?? "");
    if (!$repositorySource) {
        echo "<h3>Warning no org.opencontainers.image.source label</h3>";
        echo "<div>Please request that org.opencontainers.image.source is added by image creator for the best experience.</div>";
        $repositorySource = $dockerTemplates->getTemplateValue($currentImage, "Project");

        if ($repositorySource && preg_match('/github\.com\/\w+\/\w+/', $repositorySource)) {
            echo "<div>Falling back to Project field of the unraid template</div>";
            echo "<a href=\"$repositorySource\" target=\"blank\">$repositorySource</a>";
        } else {
            echo "<div>Couldn't fall back to project url didn't look like a github repo</div>";
            echo "<div>$repositorySource</div>";
            $repoGuess = implode('/', array_reverse(array_slice(array_reverse(explode('/', explode(':', $currentImage)[0])), 0, 2)));
            $repositorySource = "https://github.com/{$repoGuess}";
            echo "<div>Falling back to a guess based on container image registry</div>";
            echo "<a href=\"$repositorySource\" target=\"blank\">$repositorySource</a>";
        }
    }
    return $repositorySource;
}

/**
 * Get the container change logs.
 * @return void
 */
function getContainerChangeLogs(): void
{
    $dockerClient = new DockerClient();
    $containers = array_filter($dockerClient->getDockerJSON("/containers/json?all=1"), function ($ct) {
        return in_array(str_replace("/", "", $ct['Names'][0]), $_GET["cts"]) && $ct['Labels']['net.unraid.docker.managed'];
    });

    foreach ($containers as $container) {
        $repositorySource = getRepositorySource($container);


        $releasesClient = new Releases($repositorySource);

        $currentImageSourceTag = $container["Labels"]["org.opencontainers.image.version"] ?? "";
        $currentImageCreatedAt = $container["Labels"]["org.opencontainers.image.created"] ?? "";

        if (str_contains($repositorySource, 'github')) {
            $releasesClient->pullReleases();

            if (!$releasesClient->hasReleases()) {
                echo "<p>Falling back to tags for information</p>";
                $releasesClient->pullTags();
            }

            $releasesUrl = $releasesClient->releasesUrl;

            $releasesClient->organiseReleases($currentImageSourceTag);

            if (!$releasesClient->hasReleases()) {
                echo '<pre style="overflow-y: scroll; height:400px;">';
                echo "<h3>Error no releases found!</h3>" .
                    "<a href=\"$releasesUrl\" target=\"blank\">$releasesUrl</a>" .
                    "<div>org.opencontainers.image.source=$repositorySource</div>" .
                    "<div>org.opencontainers.image.version=$currentImageSourceTag</div>" .
                    "<div>org.opencontainers.image.created_at=$currentImageCreatedAt</div>" .
                    "<br/>";
                echo "</pre>";
            } else {
                $firstRelease = reset($releasesClient->releases);
                $latestImageCreatedAt = (new DateTime($firstRelease->created_at))->format('Y-m-d H:i:s');

                if (!$currentImageCreatedAt) {
                    echo "<h3>WARNING: No org.opencontainers.image.created_at found</h3>";
                    echo "<p>Please request that org.opencontainers.image.created_at is added by image creator for the best experience.</p>";
                    echo "<p>Falling back to displaying all releases</p>";
                    $lastRelease = end($releasesClient->releases);
                    $currentImageCreatedAt = (new DateTime($lastRelease->created_at))->format('Y-m-d H:i:s');
                    $currentImageSourceTag = $lastRelease->tag_name;
                } else {
                    $currentImageCreatedAt = (new DateTime($currentImageCreatedAt))->format('Y-m-d H:i:s');
                }

                echo "<h3>$currentImageSourceTag ($currentImageCreatedAt) ---->  {$firstRelease->tag_name} ({$latestImageCreatedAt})</h3>";
                echo "<a href=\"$releasesUrl\" target=\"blank\">Used this url for changelog information</a>";

                echo '<pre style="overflow-y: scroll; height:400px;">';
                foreach ($releasesClient->releases as &$item) {
                    if ($latestImageCreatedAt <= $currentImageCreatedAt) {
                        continue;
                    }
                    $latestImageCreatedAt = (new DateTime($item->created_at))->format('Y-m-d H:i:s');
                    echo "<a target=\"blank\" href=\"{$item->html_url}\">{$item->tag_name} ($latestImageCreatedAt)</a>" .
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
try {
    getContainerChangeLogs();
} catch (Exception $e) {
    echo "<h3>Error: {$e->getMessage()}</h3>";
}

?>