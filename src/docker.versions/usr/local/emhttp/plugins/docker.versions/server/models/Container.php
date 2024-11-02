<?php
namespace DockerVersions\Models;

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/plugins/dynamix.docker.manager/include/DockerClient.php");
require_once("$documentRoot/plugins/docker.versions/server/helpers/Publish.php");
require_once("$documentRoot/plugins/docker.versions/server/helpers/Generic.php");

use DockerVersions\Helpers\Publish;
use DockerVersions\Helpers\Generic;
use DockerVersions\Services\Releases;
use DateTime;
use DockerTemplates;

class Container
{
    public static $LABELS = [
        "version" => "org.opencontainers.image.version",
        "created" => "org.opencontainers.image.created",
        "source" => "org.opencontainers.image.source",
        "secondarySource" => "docker.versions.source",
        "tagIgnorePrefixes" => "docker.versions.tagIgnorePrefixes",
        "unraidManaged" => "net.unraid.docker.managed",
        "sourceType" => "docker.versions.sourceType",
        "imageSourceType" => "docker.versions.imageSourceType",
    ];
    public string $imageVersion;
    public string $name;
    public string $imageCreatedAt;
    public string $containerCreatedDate;
    public string $repositorySource;
    public string $repositorySecondarySource;
    public string $sourceType;
    public string $imageSourceType;
    /**
     * array of tagIgnorePrefixes
     * @var string[]
     */
    public array $tagIgnorePrefixes;
    public bool $isPreRelease;

    public function __construct(
        array $containerPayload,
    ) {
        $this->name = str_replace("/", "", $containerPayload['Names'][0]);
        $this->isPreRelease = Releases::isPreRelease($containerPayload["Image"]) ?? false;
        $this->processLabels($containerPayload);
        // $this->containerCreatedDate = Generic::convertToDateString($containerPayload["Created"]);
        // Lets just hardcode to the last 2 months this date isn't the best as it updates every change
        $this->containerCreatedDate = Generic::convertToDateString((new DateTime())->modify('-2 months')->getTimestamp());
    }

    /**
     * process the labels from the container payload.
     * @param array $containerPayload
     * @return void
     */
    private function processLabels(array $containerPayload)
    {
        $labels = $containerPayload["Labels"];
        $this->repositorySource = $this->getRepositorySource($containerPayload);
        $this->repositorySecondarySource = $labels[self::$LABELS["secondarySource"]] ?? "";
        $this->imageVersion = $labels[self::$LABELS["version"]] ?? "";
        $this->tagIgnorePrefixes = array_filter(explode(",", $labels[self::$LABELS["tagIgnorePrefixes"]])) ?? [];
        $this->imageCreatedAt = Generic::convertToDateString($labels[self::$LABELS["created"]]) ?? "";
        $this->sourceType = $labels[self::$LABELS["sourceType"]] ?? "";
        $this->imageSourceType = $labels[self::$LABELS["imageSourceType"]] ?? "";
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
     * @param array $containerPayload
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