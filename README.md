# docker.versions

Until i workout how to add the plugin to CA install via <https://raw.githubusercontent.com/phyzical/docker.versions/main/docker.versions.plg>

Unraid plugin to use open container labels to attempt to extract some changelogs when a new version is detected

At the moment github is only supported, happy to support more just need some good examples.

It should be noted the quality of the changelog is dependent on whatever is posted on the release by the maintainers.

It is highly suggested to generate a github token, this can be set in the settings page for docker.verions and has instructions on this page.

For the best experience images need the following labels;

* `org.opencontainers.image.created`
* `org.opencontainers.image.source`
* `org.opencontainers.image.version`

If `org.opencontainers.image.source` is missing it will;

* check if its template has a project configured, if so use that as a release base.
* Otherwise do its best to guess the github repo based on the image registry.

If `org.opencontainers.image.created` is missing it will simply display all.

If `org.opencontainers.image.version` is present this will be used to subset images to preRelease only i.e dev/beta/alpha ect.

If no releases are found it will fall back to trying to pull the tags;

You can also manually add a label of `docker.versions.changelog_url`, this will be used to populate a separate list of changelog information, assumes there are chucks of changelogs separated by either tag names or dates prepended by md titles i.e `# 2012-12-12` or `# v1.2.3`

below are a few examples of what can happen

* All Labels
![All Labels](images/all.png)

* No labels, no guess
![No labels, no guess](images/none.png)

* No labels, successful guess
![No labels, successful guess](images/semi.png)

* No Releases, fallback to using tags
![No Releases, fallback to using tags](images/tags.png)

<!-- * Changelog url label -->
<!-- ![TODO](images/changelog.png) -->