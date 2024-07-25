# docker.versions

unraid plugin to use open container labels to attempt to extract some changelogs when a new version is detected

At the moment github is only supported, happy to support more just need examples.

It should be noted the quality of the changelog is dependent on whatever is posted on the release by the maintainers.

For the best experience images need the following labels:

* `org.opencontainers.image.created`
* `org.opencontainers.image.source`
* `org.opencontainers.image.version`

If `org.opencontainers.image.source` is missing it will;

* check if its template has a project configured, if so use that as a release base.
* Otherwise do its best to guess the github repo based on the image registry.

If `org.opencontainers.image.created` is missing it will simply display all.

If `org.opencontainers.image.version` is present this will be used to subset images to preRelease only i.e dev/beta/alpha ect.

below are a few examples of what can happen

* All Labels
![All Labels](images/all.png)

* No labels, no guess
![No labels, no guess](images/none.png)

* No labels, successful guess
![No labels, successful guess](images/semi.png)
