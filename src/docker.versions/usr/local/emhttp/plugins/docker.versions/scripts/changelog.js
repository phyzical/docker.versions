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
let changeLog_nchan
function showChangeLog(container) {
    var title = _('Changelog for ') + container;
    var url = '/plugins/docker.versions/server/GetChangelog.php?cts[]=' + encodeURIComponent(container);
    popup(title, url);
}


function popup(title, url) {
    $('#iframe-popup').html('<iframe id="myIframe" frameborder="0" scrolling="yes" width="100%" height="99%"></iframe>');

    // Append HTML into an element within the iframe

    if (!changeLog_nchan) {
        changeLog_nchan = new NchanSubscriber('/sub/changelog');
        changeLog_nchan.on('message', function (data) {
            const iframeDocument = $('#myIframe')[0].contentDocument
            if (data.includes("class='pullInfo'")) {
                $(iframeDocument).find('.pullTags').html(data);
            } else if (data.includes("class='releasesInfo'")) {
                $(iframeDocument).find('.releases').append(data);
            } else {
                const box = $(iframeDocument).find('body')
                box.append(data).scrollTop(box[0].scrollHeight);
            }
        });
    }
    changeLog_nchan.start();

    $('#iframe-popup').dialog({
        autoOpen: true,
        title,
        draggable: true,
        width: Math.min(Math.max(window.innerWidth / 2, 900), 1600),
        height: Math.max(window.innerHeight * 3 / 5, 600),
        resizable: true,
        modal: true,
        show: { effect: 'fade', duration: 250 },
        hide: { effect: 'fade', duration: 250 },
        open: function (ev, ui) {
            $.get(url, {}, function (data) { });
        },
        close: function (event, ui) {
            changeLog_nchan.stop();
            // location = window.location.href;
        }
    });
    $(".ui-dialog .ui-dialog-titlebar").addClass('menu');
    $('.ui-dialog .ui-dialog-titlebar-close').text('X').prop('title', _('Close'));
    $(".ui-dialog .ui-dialog-title").css({ 'text-align': 'center', 'width': '100%' });
    $(".ui-dialog .ui-dialog-content").css({ 'padding-top': '15px', 'vertical-align': 'bottom' });
}