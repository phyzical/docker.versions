<?php

namespace DockerVersions\Services;
use Exception;
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/plugins/docker.versions/server/models/Release.php");
require_once("$documentRoot/plugins/docker.versions/server/models/Container.php");
require_once("$documentRoot/plugins/docker.versions/server/helpers/Publish.php");
require_once("$documentRoot/plugins/docker.versions/server/config/GithubToken.php");
use DockerVersions\Config\GithubToken;
use DockerVersions\Models\Release;
use DockerVersions\Models\Container;
use DockerVersions\Helpers\Publish;

class Releases
{
    public string $repositorySource;
    public string $releasesUrl;
    const perPage = "100";

    public Container $container;

    /**
     * Releases constructor.
     * @param Container $container
     */
    public function __construct(
        Container $container,
        string $repositorySource
    ) {
        $this->container = $container;
        $this->repositorySource = $repositorySource;
    }

    /**
     * @var Release[]
     */
    public array $releases = [];

    public const BETA_TAGS = ["night", "dev", "beta", "alpha", "preview", "previous", "unstable", "rc"];


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
    function makeReq(string $url, string $secondaryMessage = null): array|object|string
    {
        Publish::loadingMessage("Loading " . ($secondaryMessage ?? $url));
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
            Publish::message("<li class='warnings'>Without a github token you may find that this just stops working</li>");
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
            Publish::message("<li class='warnings'>HTTP error: $http_status. Response: $body</li>");
            $body = "[]";
        }

        Publish::loadingMessage("");

        return json_decode($body) ?? $body;
    }

    /**
     * Check if the repository has releases.
     * @return bool
     */

    function hasReleases(): bool
    {
        return $this->releases && count($this->releases) > 0;
    }

    /**
     * Get the github URL for the repository source.
     *
     * @return string
     */

    private function githubURL(): string
    {
        $trimmedSource = str_replace("https://github.com/", "", $this->repositorySource);
        $repositorySourceSegments = explode("/", $trimmedSource);
        return "https://api.github.com/repos/" . implode("/", array_slice(
            $repositorySourceSegments,
            0,
            2
        ));
    }



    /**
     * Check if the tag is ignorable.
     * @param string $tagName
     * @return bool
     */
    private function isIgnorable(string $tagName): bool
    {
        return !empty($this->container->tagIgnorePrefixes) &&
            preg_match("/" . implode("|", $this->container->tagIgnorePrefixes) . "/", $tagName);
    }

    /**
     * Check if the tag is a pre-release.
     * @param string $tagName
     * @return bool
     */
    static function isPreRelease(string $tagName): bool
    {
        return array_reduce(self::BETA_TAGS, function ($carry, $item) use ($tagName) {
            return $carry || str_contains($tagName, $item);
        }, false);
    }

    /**
     * Organise releases and return an array of Release objects.
     */
    function organiseReleases(): void
    {
        Publish::loadingMessage("Organising releases");
        $processedReleases = [];
        while (!empty($this->releases)) {
            // we pop items to save on memory
            $release = array_shift($this->releases);
            // skip all ignoredPrefixes
            if (
                $this->isIgnorable($release->tagName)
            ) {
                continue;
            }
            // skip all prereleases
            if (
                $release->preRelease && !$this->container->isPreRelease
            ) {
                continue;
            }

            $add = true;

            foreach ($processedReleases as $processedRelease) {
                if (
                    $release->tagName != $processedRelease->tagName && $release->body == $processedRelease->body
                ) {
                    $processedRelease->extraReleases[] = $release;
                    $add = false;
                }
            }
            if ($add || empty($processedReleases)) {
                $processedReleases[] = $release;
            }
        }

        $this->releases = $processedReleases;

        // Sort by created_at
        usort($this->releases, function ($a, $b) {
            return strtotime($b->createdAt) <=> strtotime($a->createdAt);
        });
        Publish::loadingMessage("");
    }

    /**
     * Check if the repository source is a changelog URL.
     * @return bool
     */

    function isChangelogUrl(): bool
    {
        return str_contains($this->repositorySource, ".md") || str_contains(strtolower($this->repositorySource), "changelog");
    }

    /**
     * Handle the fallback releases.
     */
    function pullFallbackReleases(): void
    {
        // if source is an md file
        if ($this->isChangelogUrl()) {
            Publish::message("<li class='warnings'>Falling back to changelog for information for $this->repositorySource</li>");
            $this->parseChangelogFile();
        } else {
            Publish::message("<li class='warnings'>Falling back to last 30 tags for information for $this->repositorySource</li>");
            $this->pullTags();
        }
    }

    /**
     * Parse the changelog file.
     */
    function parseChangelogFile(): void
    {
        //replace github.com with raw.githubusercontent.com and replace blob with refs/heads
        $releasesUrl = str_replace(
            ["github.com", "blob"],
            ["raw.githubusercontent.com", "refs/heads"],
            $this->repositorySource
        );

        $this->releasesUrl = $releasesUrl;
        $changelogString = $this->makeReq($releasesUrl);
        $changelogLines = explode("\n", $changelogString);

        // split into chunks by lines that contain 1 to many # and a date string
        // (\d{4}[-\/]\d{2}[-\/]\d{2}): Matches YYYY-MM-DD or YYYY/MM/DD.
        // (\d{2}[-\/]\d{2}[-\/]\d{4}): Matches MM-DD-YYYY or MM/DD/YYYY.
        // (\d{4}[-\/]\d{1,2}[-\/]\d{1,2}): Matches YYYY-M-D or YYYY/M/D.
        // (\d{1,2}[-\/]\d{1,2}[-\/]\d{4}): Matches D-M-YYYY or D/M/YYYY.
        // ([A-Za-z]{3} [A-Za-z]{3} \d{1,2}(st|nd|rd|th)?,? \d{4}):  Matches Mon Jan 15th 2024 or similar.
        $dateRegex = "/(\d{4}[-\/]\d{2}[-\/]\d{2})|(\d{2}[-\/]\d{2}[-\/]\d{4})|(\d{4}[-\/]\d{1,2}[-\/]\d{1,2})|(\d{1,2}[-\/]\d{1,2}[-\/]\d{4})|([A-Za-z]{3} [A-Za-z]{3} \d{1,2}(st|nd|rd|th)?,? \d{4})/";

        // for each line matching the date regex get the following line until the next date regex
        $groupedChangeLogs = [];
        $currentContent = "";
        foreach ($changelogLines as $line) {
            if (preg_match($dateRegex, $line)) {
                $groupedChangeLogs[] = $currentContent;
                $currentContent = $line;
            } else {
                $currentContent = "$currentContent\n$line";
            }
        }

        $this->releases = array_filter(
            array_map(function ($changelog) use ($releasesUrl, $dateRegex) {
                $body = explode("\n", $changelog);
                $title = array_shift($body);
                $body = implode("\n", $body);

                // find the first string that looks like a date string
                preg_match($dateRegex, $title, $dates);

                $date = reset($dates);

                if (!$date) {
                    return false;
                }

                // remove any date like strings
                $tag = trim(
                    str_replace(
                        ["[", "]", "(", ")", "*", "-", "#", $date, "<", ">"],
                        "",
                        $title
                    )
                );

                return new Release(
                    "changelog",
                    $tag,
                    $date,
                    $releasesUrl,
                    $body,
                    // We have no way of detecting this for a tag
                    false
                );
            }, $groupedChangeLogs)
        );

        if (!$this->hasReleases()) {
            Publish::message("<li class='warnings'>No changelogs found! (<a href=\"$releasesUrl\" target=\"blank\">Changelogs</a>)</li>");
        }
    }

    /**
     * Pull releases from the github API.
     */
    function pullReleases(): void
    {
        if ($this->isChangelogUrl()) {
            return;
        }
        $releasesUrl = $this->githubURL() . "/releases?per_page=" . self::perPage;
        $this->releasesUrl = $releasesUrl;

        $releases = $this->makeReq($releasesUrl);
        // $page = 1;
        // $releases = [];
        // do {
        //     $releases = array_merge($releases, $this->makeReq("$releasesUrl&page=$page"));
        //     $page++;
        // } while (count($releases) % 100 == 0);

        $this->releases = array_map(function ($release) {
            $tagName = $release->tag_name;
            return new Release(
                "release",
                $tagName,
                $release->created_at,
                $release->html_url,
                $release->body ?? "No release notes sorry!",
                $release->prerelease || $this->isPreRelease($tagName)
            );
        }, $releases);

        if (!$this->hasReleases()) {
            Publish::message("<li class='warnings'>No releases found! (<a href=\"$releasesUrl\" target=\"blank\">Releases</a>)</li>");
        }
    }
    /**
     * Pull tags from the github API.
     */
    function pullTags(): void
    {
        $tagsUrl = $this->githubURL() . "/tags?per_page=" . self::perPage;
        $this->releasesUrl = $tagsUrl;
        $tags = $this->makeReq($tagsUrl);

        // $page = 1;
        // $tags = [];
        // do {
        //     $tags = array_merge($tags, $this->makeReq("$tagsUrl&page=$page"));
        //     $page++;
        // } while (count($tags) % 100 == 0);

        $this->releases = array_map(function ($tag) {
            $tag_commit = $this->makeReq($tag->commit->url, $tag->name);

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

    /**
     * Pull commits from the github API.
     */
    function pullCommits(): void
    {
        $url = $this->githubURL() . "/commits?per_page=" . self::perPage;
        $this->releasesUrl = $url;
        $commits = $this->makeReq($url);

        // $page = 1;
        // $commits = [];
        // do {
        //     $commits = array_merge($commits, $this->makeReq("$url&page=$page"));
        //     $page++;
        // } while (count($commits) % 100 == 0);

        $this->releases = array_map(function ($commit) {
            return new Release(
                "commit",
                $commit->sha,
                $commit->commit->author->date ?? $commit->commit->committer->date,
                $commit->commit->url,
                $commit->commit->message ?? "No commit message sorry!",
                // We have no way of detecting this for a commits
                false
            );
        }, $commits);

        if ($this->hasReleases()) {
            Publish::message("<li class='warnings'>pulled last " . count($this->releases) . " commits for information for $this->repositorySource</li>");
        } else {
            Publish::message("<li class='warnings'>No commits found! (<a href=\"$url\" target=\"blank\">$url</a>)</li>");
        }
        $this->organiseReleases();
    }
}

?>