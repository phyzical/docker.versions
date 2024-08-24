<?php

namespace DockerVersions\Services;
use Exception;
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/plugins/docker.versions/server/models/Release.php");
require_once("$documentRoot/plugins/docker.versions/server/GithubToken.php");
use DockerVersions\GithubToken;
use DockerVersions\Models\Release;

class Releases
{
    public string $githubURL;
    public string $releasesUrl;


    public function __construct(
        string $repositorySource
    ) {
        $this->githubURL = $this->getGithubURL($repositorySource);
    }

    public array $releases;

    public const BETA_TAGS = ["night", "dev", "beta", "alpha", "test"];


    /**
     * Get the releases.
     * @return Release[]
     */

    function getReleases(): array
    {
        return $this->releases;
    }

    /**
     * Get the first release.
     * @return Release
     */
    function first(): Release
    {
        return reset($this->releases);
    }

    /**
     * Get the last release.
     * @return Release
     */
    function last(): Release
    {
        return end($this->releases);
    }

    /**
     * Make a request to the github API.
     * @param string $url
     * @return array
     */
    function makeReq(string $url): array|object
    {
        $ch = getCurlHandle($url, 'GET');
        $headers = [
            "Accept: application/vnd.github+json",
            "User-Agent: docker.versions-unraid-plugin",
            "X-GitHub-Api-Version: 2022-11-28"
        ];
        $token = GithubToken::getGithubToken();
        if (!empty($token)) {
            $headers[] = "Authorization: Bearer $token";
        } else {
            echo "<h3> WARNING: Without a github token you may find that this just stops working</h3>";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error: $error_msg");
        }

        // Get HTTP status code
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check if the status code indicates an error
        if ($http_status >= 400) {
            throw new Exception("HTTP error: $http_status. Response: $body");
        }

        return json_decode($body);
    }

    /**
     * Check if the repository has releases.
     * @return bool
     */

    function hasReleases(): bool
    {
        return $this->releases && sizeof($this->releases) > 0;
    }

    /**
     * Get the github URL for the repository source.
     *
     * @param string $repositorySource
     * @return string
     */

    private function getGithubURL($repositorySource): string
    {
        $repositorySourceSegments = explode("/", $repositorySource);
        return "https://api.github.com/repos/" . implode("/", array_slice(
            $repositorySourceSegments,
            sizeof($repositorySourceSegments) - 2,
            2
        ));
    }

    /**
     * Organise releases and return an array of Release objects.
     *
     * @param string $currentImageSourceTag
     */
    function organiseReleases(string $currentImageSourceTag): void
    {
        // Use array_reduce to iterate over each element and check if it's contained in $currentImageSourceTag
        $isPrerelease = array_reduce(self::BETA_TAGS, function ($carry, $item) use ($currentImageSourceTag) {
            return $carry || str_contains($currentImageSourceTag, $item);
        }, false);

        // Filter if $isPrerelease
        $this->releases = array_filter($this->releases, function ($release) use ($isPrerelease) {
            return $release->prerelease == $isPrerelease;
        });

        // Sort by created_at
        usort($this->releases, function ($a, $b) {
            return strtotime($b->created_at) <=> strtotime($a->created_at);
        });
    }

    /**
     * Pull releases from the github API.
     */
    function pullReleases(): void
    {
        $releasesUrl = $this->githubURL . "/releases";
        $this->releasesUrl = $releasesUrl;

        $releases = $this->makeReq($releasesUrl);
        $this->releases = array_map(function ($release) {
            return new Release(
                "release",
                $release->tag_name,
                $release->created_at,
                $release->html_url,
                $release->body,
                $release->prerelease
            );
        }, $releases);

        if (!$this->hasReleases()) {
            echo "<h3>WARNING: no releases found! (<a href=\"$releasesUrl\" target=\"blank\">Releases</a>)</h3>";
        }
    }
    /**
     * Pull tags from the github API.
     */
    function pullTags(): void
    {
        $tagsUrl = $this->githubURL . "/tags";
        $this->releasesUrl = $tagsUrl;
        $tags = $this->makeReq($tagsUrl);

        $this->releases = array_map(function ($tag) {
            $tag_commit = $this->makeReq($tag->commit->url);

            return new Release(
                "tag",
                $tag->name,
                $tag_commit->commit->author->date ?? $tag_commit->commit->committer->date,
                $tag->commit->url,
                $tag_commit->commit->message ?? "No commit message sorry!",
                // We have no way of detecting this for a tag
                false
            );
        }, $tags);

        if (!$this->hasReleases()) {
            echo "<h3>WARNING: no tags found! (<a href=\"$tagsUrl\" target=\"blank\">Tags</a>)</h3>";
        }
    }
}

?>