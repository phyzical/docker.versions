<?php
namespace DockerVersions\Models;

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp';
require_once("$documentRoot/plugins/docker.versions/server/helpers/Generic.php");

use Exception;

use DockerVersions\Helpers\Generic;

class Release
{
    public const ALLOWED_TYPES = ['release', 'tag', 'changelog'];
    public string $type;
    public string $tagName;

    /**
     * @var Release[]
     */
    public array $extraReleases = [];

    public string $createdAt;
    public string $htmlUrl;
    public string $body;


    public bool $preRelease;

    /**
     * Release constructor.
     * @param string $type
     * @param string $tagName
     * @param string $createdAt
     * @param string $htmlUrl
     * @param string $body
     * @param bool $preRelease
     * @throws Exception
     */
    public function __construct(
        string $type,
        string $tagName,
        string $createdAt,
        string $htmlUrl,
        string $body,
        bool $preRelease
    ) {
        if (!in_array($type, self::ALLOWED_TYPES)) {
            throw new Exception("Invalid type: $type. Allowed types are 'release', 'tag' or 'changelog'.");
        }
        $this->type = $type;
        $this->tagName = $tagName;
        $this->createdAt = Generic::convertToDateString($createdAt);
        $this->htmlUrl = $htmlUrl;
        $this->body = gzcompress($body);
        $this->preRelease = $preRelease;
    }

    /**
     * Get the body of the release.
     * @return string
     */
    public function getBody(): string
    {
        return gzuncompress($this->body);
    }
}

?>