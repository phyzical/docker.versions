<?php

namespace DockerVersions\Config;

class GithubToken
{
    public const CONFIG_PATH = "/boot/config/docker.versions";
    public const TOKEN_PATH = GithubToken::CONFIG_PATH . "/github_token.txt";

    /**
     * Save the GitHub token to a file.
     * @return string
     */
    static function formSubmit(): string|null
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $githubToken = trim($_POST["github_token"]);
            if (!empty($githubToken)) {
                mkdir(GithubToken::CONFIG_PATH, 0755, true);
                file_put_contents(GithubToken::TOKEN_PATH, $githubToken);
                return "GitHub token saved successfully!";
            }
        }
        return null;
    }

    /**
     * Get the GitHub token from the config file.
     * @return string
     */
    static function getGithubToken(): string
    {
        if (file_exists(self::TOKEN_PATH)) {
            return file_get_contents(self::TOKEN_PATH);
        }
        return "";
    }

    static function generateForm(): void
    {
        $message = self::formSubmit();
        echo <<<HTML
                <h2>Github Token</h2>
                <p>a github token is required simply due to the amount of api calls processed</p>
                <p>Create a github account then</p>
                <p><href="https://github.com/settings/tokens/new">Generate a token</href></p>
                <p>no permissions are required, unless you would like changelogs from private repos.</p>
                <div>
                    <form method="post" action="">
                        <label for="github_token">GitHub Token:</label>
            HTML;
        echo '<input type="password" id="github_token" name="github_token" value="' . self::getGithubToken() . '">';
        echo <<<HTML
                        <input type="checkbox" id="show_password" onclick="togglePassword()"> Show Token
                        <button type="submit">Save Token</button>
                    </form>
                    <script>
                        function togglePassword() {
                            var x = document.getElementById("github_token");
                            if (x.type === "password") {
                                x.type = "text";
                            } else {
                                x.type = "password";
                            }
                        }
                    </script>
            HTML;
        // Display the message if set
        if (isset($message)) {
            echo "<p>$message</p>";
        }
        echo "</div><hr>";
    }
}

?>