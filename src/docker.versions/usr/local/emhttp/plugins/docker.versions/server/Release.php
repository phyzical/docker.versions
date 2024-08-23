<?php

namespace DockerVersions {
    use Exception;

    class Release
    {
        public const ALLOWED_TYPES = ['release', 'tag'];
        public string $type;
        public string $tag_name;

        public string $created_at;
        public string $html_url;
        public string $body;
        public bool $prerelease;
        public function __construct(
            string $type,
            string $tag_name,
            string $created_at,
            string $html_url,
            string $body,
            bool
            $prerelease
        ) {
            if (!in_array($type, self::ALLOWED_TYPES)) {
                throw new Exception("Invalid type: $type. Allowed types are 'release' or 'tag'.");
            }
            $this->type = $type;
            $this->tag_name = $tag_name;
            $this->created_at = $created_at;
            $this->html_url = $html_url;
            $this->body = $body;
            $this->prerelease = $prerelease;
        }
    }
}

?>