<?php
namespace DockerVersions\Models;

use Exception;

class Release
{
    public const ALLOWED_TYPES = ['release', 'tag'];
    public string $type;
    public string $tagName;

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
            throw new Exception("Invalid type: $type. Allowed types are 'release' or 'tag'.");
        }
        $this->type = $type;
        $this->tagName = $tagName;
        $this->createdAt = $createdAt;
        $this->htmlUrl = $htmlUrl;
        $this->body = $body;
        $this->preRelease = $preRelease;
    }
}

?>