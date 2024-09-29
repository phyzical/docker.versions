<?php
namespace DockerVersions\Models;

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once("$documentRoot/plugins/docker.versions/server/helpers/Publish.php");
require_once("$documentRoot/plugins/docker.versions/server/helpers/Generic.php");

use DockerVersions\Helpers\Publish;
use DockerVersions\Helpers\Generic;
use DockerVersions\Services\Releases;
use DockerTemplates;

class Container
{
    public static $LABELS = [
        "version" => "org.opencontainers.image.version",
        "created" => "org.opencontainers.image.created",
        "source" => "org.opencontainers.image.source",
        "secondarySource" => "docker.versions.source",
        "tagIgnorePrefixes" => "docker.versions.tagIgnorePrefixes",
        "unraidManaged" => "net.unraid.docker.managed"
    ];
    public string $imageVersion;
    public string $name;
    public string $imageCreatedAt;
    public string $containerCreatedDate;
    public string $repositorySource;
    public string $repositorySecondarySource;
    /**
     * array of tagIgnorePrefixes
     * @var string[]
     */
    public array $tagIgnorePrefixes;
    public bool $isPreRelease;



    public function __construct(
        array $containerPayload,
    ) {
        $this->repositorySource = $this->getRepositorySource($containerPayload);
        $this->name = str_replace("/", "", $containerPayload['Names'][0]);
        $this->repositorySecondarySource = $containerPayload["Labels"][self::$LABELS["secondarySource"]] ?? "";
        $this->imageVersion = $containerPayload["Labels"][self::$LABELS["version"]] ?? "";
        $this->isPreRelease = Releases::isPreRelease($containerPayload["Image"]) ?? false;
        $this->tagIgnorePrefixes = array_filter(explode(",", $containerPayload["Labels"][self::$LABELS["tagIgnorePrefixes"]])) ?? [];
        $this->imageCreatedAt = Generic::convertToDateString($containerPayload["Labels"][self::$LABELS["created"]]) ?? "";
        $this->containerCreatedDate = Generic::convertToDateString($containerPayload["Created"]);
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
            Publish::message("<li class='warnings'>no " . self::$LABELS["source"] . " label</li>");
            Publish::message("<li class='warnings'>Please request that " . self::$LABELS["source"] . " is added by image creator for the best experience Or simply add the label yourself to the running container.</li>");

            if ($repositorySource && preg_match('/github\.com\/\w+\/\w+/', $repositorySource)) {
                Publish::message("<li class='warnings'>Falling back to Project field of the unraid template</li>");
                Publish::message("<li><a class='warnings'href=\"$repositorySource\" target=\"blank\">$repositorySource</a></li>");
            } else {
                Publish::message("<li class='warnings'>Couldn't fall back to project url didn't look like a github repo</li>");
                Publish::message("<li class='warnings'>$repositorySource</div>");
                $repoGuess = implode('/', array_reverse(array_slice(array_reverse(explode('/', explode(':', $currentImage)[0])), 0, 2)));
                $repositorySource = "https://github.com/{$repoGuess}";
                Publish::message("<li class='warnings'>Falling back to a guess based on container image registry</li>");
                Publish::message("<li><a class='warnings' href=\"$repositorySource\" target=\"blank\">$repositorySource</a></li>");
            }
        }
        return $repositorySource;
    }
}

?>