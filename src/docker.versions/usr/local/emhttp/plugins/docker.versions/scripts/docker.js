$(document).ready(function () {
    setTimeout(function () {
        $('.updatecolumn:not(.folder-update)').each(function () {
            const updateButton = $(this).children('[onclick*="updateContainer"]')
            if (updateButton.length) {
                const containerName = updateButton.attr("onclick").split("'")[1]
                $(this).prepend('<div class="changelog orange-text" style="display: inline-block; margin-right: 10px; cursor: pointer; color: #007bff;"><i class="fa fa-list fa-fw"></i>Change Log</div>');
                $(this).find('.changelog').first().on('click', () => showChangeLog(containerName));
            }
        });
    }, 1000)
})

function showChangeLog(container) {
    var title = _('Changelog for ') + container;
    var cmd = '/plugins/docker.versions/server/GetDockerChangelog.php?cts[]=' + encodeURIComponent(container);
    popupWithIframe(title, cmd, false, 'loadlist');
}

