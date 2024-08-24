<?php

namespace DockerVersions\Config;

class GithubToken
{
    public const CONFIG_PATH = "/boot/config/docker.versions/";

    /**
     * Save the GitHub token to a file.
     * @return string
     */
    static function formSubmit(): string
    {
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Get the GitHub token from the form input
            $githubToken = trim($_POST["github_token"]);
            // Validate the token (basic validation)
            if (!empty($githubToken)) {
                // Save the token to a file (you can change this to save to a database if needed)
                mkdir(GithubToken::CONFIG_PATH, 0755, true);
                file_put_contents(GithubToken::CONFIG_PATH . "/github_token.txt", $githubToken);
                return "GitHub token saved successfully! (" . $githubToken . ")";
            } else {
                return "Please enter a valid GitHub token.";
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
        return file_get_contents(self::CONFIG_PATH . "/github_token.txt");
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
                        <input type="text" id="github_token" name="github_token" required>
                        <button type="submit">Save Token</button>
                    </form>
            HTML;
        // Display the message if set
        if (isset($message)) {
            echo "<p>$message</p>";
        }
        echo "</div><hr>";
    }
}

?>