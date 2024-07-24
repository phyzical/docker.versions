<?php
$configDir = "/boot/config/plugins/docker.versions";
$sourceDir = "/usr/local/emhttp/plugins/docker.versions";
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once ("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once ("$documentRoot/webGui/include/Helpers.php");


$dockerClient = new DockerClient();
$dockerUpdate = new DockerUpdate();

$containers = array_filter($dockerClient->getDockerJSON("/containers/json?all=1"), function ($ct) {
    return in_array(str_replace("/", "", $ct['Names'][0]), $_GET["cts"]) && $ct['Labels']['net.unraid.docker.managed'];
});

$dockerImages = $dockerClient->getDockerImages();

foreach ($containers as $container) {
    $repositorySource = str_replace(".git", "", $container["Labels"]["org.opencontainers.image.source"] ?? "");
    $currentImageSourceTag = $container["Labels"]["org.opencontainers.image.version"] ?? "org.opencontainers.image.version label missing";
    $currentImageCreatedAt = (new DateTime($container["Labels"]["org.opencontainers.image.created"]))->format('Y-m-d H:i:s') ?? "org.opencontainers.image.created label missing";

    $releases = [];
    if (!$repositorySource) {
        echo "<h3>Error no org.opencontainers.image.source label</h3>";
    } else if (str_contains($repositorySource, 'github')) {
        $repositorySourceSegments = explode("/", $repositorySource);
        $releasesUrl = "https://api.github.com/repos/" . implode("/", array_slice($repositorySourceSegments, sizeof($repositorySourceSegments) - 2, 2)) . "/releases";
        $ch = getCurlHandle($releasesUrl, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Accept: application/vnd.github+json",
            "User-Agent: docker.versions-unraid-plugin",
            // "Authorization: Bearer <YOUR-TOKEN>",
            "X-GitHub-Api-Version: 2022-11-28"
        ]);
        $releases = json_decode(curl_exec($ch));

        $substrArray = ["night", "dev", "beta", "alpha", "test"];

        // Use array_reduce to iterate over each element and check if it's contained in $currentImageSourceTag
        $isPrerelease = array_reduce($substrArray, function ($carry, $item) use ($currentImageSourceTag) {
            return $carry || str_contains($currentImageSourceTag, $item);
        }, false);

        $releases = array_filter($releases, function ($release) use ($isPrerelease) {
            return $release->prerelease == $isPrerelease;
        });

        if (!sizeof($releases)) {
            echo '<pre style="overflow-y: scroll; height:400px;">';
            echo "<h3>Error no releases found!</h3>" .
                "<div>org.opencontainers.image.source=$repositorySource</div>" .
                "<br/>" .
                "<div>org.opencontainers.image.version=$currentImageSourceTag</div>" .
                "<br/>" .
                "<div>org.opencontainers.image.created_at=$currentImageCreatedAt</div>" .
                "<br/>";
            echo "</pre>";
        } else {
            $firstRelease = reset($releases);
            $imageCreatedAt = (new DateTime($firstRelease->created_at))->format('Y-m-d H:i:s');

            echo "<h3>$currentImageSourceTag ($currentImageCreatedAt) ---->  {$firstRelease->tag_name} ({$imageCreatedAt})</h3>";
            if (!$currentImageSourceTag && !$currentImageCreatedAt) {
                echo "<br/>";
                echo "<h3>WARNING: No org.opencontainers.image.version or org.opencontainers.image.created_at found, displaying all releases</h3>";
                echo "<br/>";
                echo "<h3>Please request that these are added by the image creator for the best experience.</h3>";
            }
            echo '<pre style="overflow-y: scroll; height:400px;">';
            foreach ($releases as &$item) {
                if ($item->tag_name <= $currentImageSourceTag || $imageCreatedAt <= $currentImageCreatedAt) {
                    continue;
                }
                $imageCreatedAt = (new DateTime($item->created_at))->format('Y-m-d H:i:s');
                echo "<a target=\"blank\" href=\"{$item->html_url}\">{$item->tag_name} ($imageCreatedAt)</a>" .
                    "<div>{$item->body}</div>" .
                    "<br/>";
            }
            echo "</pre>";
        }
    } else {
        echo "<h3>Error only github repositories are supported at this time!</h3>";
    }
}

?>