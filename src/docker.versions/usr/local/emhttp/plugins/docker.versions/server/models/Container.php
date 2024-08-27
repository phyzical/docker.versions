<?php
namespace DockerVersions\Models;

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once("$documentRoot/plugins/docker.versions/server/helpers/Publish.php");

use DockerVersions\Helpers\Publish;

class Container
{
    public static $LABELS = [
        "version" => "org.opencontainers.image.version",
        "created" => "org.opencontainers.image.created",
        "source" => "org.opencontainers.image.source",
        "changelog" => "docker.versions.changelog_url",
        "unraidManaged" => "net.unraid.docker.managed"
    ];
    public string $imageVersion;
    public string $imageCreatedAt;
    public string $repositorySource;
    public string $changelogUrl;

    public function __construct(
        array $containerPayload,
    ) {
        $this->repositorySource = $this->getRepositorySource($containerPayload);
        $this->imageVersion = $containerPayload["Labels"][self::$LABELS["version"]] ?? "";
        $this->imageCreatedAt = $containerPayload["Labels"][self::$LABELS["created"]] ?? "";
        $this->changelogUrl = $containerPayload["Labels"][self::$LABELS["changelog"]] ?? "";
    }

    /**
     * Check if the repository source is a github repository.
     * @return string
     */
    public function isGithubRepository(): string
    {
        return str_contains($this->repositorySource, 'github');
    }

    /**
     * Get the repository source from the container.
     * @param object $containerPayload
     * @return string
     */
    private function getRepositorySource(array $containerPayload): string
    {
        $repositorySource = str_replace(".git", "", $containerPayload["Labels"][self::$LABELS["source"]] ?? "");
        if (!$repositorySource) {
            $currentImage = $containerPayload["Image"];
            $repositorySource = (new DockerTemplates())->getTemplateValue($currentImage, "Project");
            Publish::message("<h3>Warning no " . self::$LABELS["source"] . " label</h3>");
            Publish::message("<div>Please request that " . self::$LABELS["source"] . " is added by image creator for the best experience.</div>");

            if ($repositorySource && preg_match('/github\.com\/\w+\/\w+/', $repositorySource)) {
                Publish::message("<div>Falling back to Project field of the unraid template</div>");
                Publish::message("<a href=\"$repositorySource\" target=\"blank\">$repositorySource</a>");
            } else {
                Publish::message("<div>Couldn't fall back to project url didn't look like a github repo</div>");
                Publish::message("<div>$repositorySource</div>");
                $repoGuess = implode('/', array_reverse(array_slice(array_reverse(explode('/', explode(':', $currentImage)[0])), 0, 2)));
                $repositorySource = "https://github.com/{$repoGuess}";
                Publish::message("<div>Falling back to a guess based on container image registry</div>");
                Publish::message("<a href=\"$repositorySource\" target=\"blank\">$repositorySource</a>");
            }
        }
        return $repositorySource;
    }
}

?>