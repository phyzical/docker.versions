$(document).ready(function () {
    const addChangelogButton = function (element) {
        const elementRef = $(element)
        const updateButton = elementRef.children('[onclick*="updateContainer"]')
        const changelogExists = elementRef.children('.changelog')
        if (updateButton.length && elementRef.text().includes('update ready') && !changelogExists.length) {
            const containerName = updateButton.attr("onclick").split("'")[1]
            elementRef.prepend('<div class="changelog orange-text" style="display: inline-block; margin-right: 10px; cursor: pointer; color: #007bff;"><i class="fa fa-list fa-fw"></i>Change Log</div>');
            elementRef.find('.changelog').first().on('click', () => showChangeLog(containerName));
        }
    }
    setTimeout(function () {
        $('.updatecolumn:not(.folder-update)').each(function () { addChangelogButton(this) });
    }, 1000)

    // TODO: can we make it more majic ratehr than every 15 seconds?
    // i.e updating a container removes the changelog button so we have to rerun
    setInterval(function () {
        $('.updatecolumn:not(.folder-update)').each(function () { addChangelogButton(this) });
    }, 5000)
})
let changeLog_nchan
function showChangeLog(container) {
    var title = _('Changelog for ') + container;
    var url = '/plugins/docker.versions/server/GetChangelog.php?cts[]=' + encodeURIComponent(container);
    popup(title, container, url);
}

function popup(title, container, url) {

    if (!changeLog_nchan) {
        changeLog_nchan = new NchanSubscriber('/sub/changelog');
        changeLog_nchan.on('message', function (data) {
            const iframeDocument = $('#myIframe')[0].contentDocument
            if (data.includes("class='loadingInfo'")) {
                $(iframeDocument).find('.loading').css('display', 'block');
                $(iframeDocument).find('.loading').html(data);
            } else if (data.includes("class='warnings'")) {
                $(iframeDocument).find('.warningsInfo').css('display', 'block');
                $(iframeDocument).find('.warningsInfo ul').append(data);
            } else if (data.includes("class='releasesInfo'")) {
                $(iframeDocument).find('.releases').css('display', 'block');
                $(iframeDocument).find('.releases').append(data);
            } else {
                const box = $(iframeDocument).find('body')
                box.css('background-color', 'white')
                box.append(data)
            }
        });
    }
    changeLog_nchan.start();


    swal({
        title,
        text: '<iframe id="myIframe" frameborder="0" scrolling="yes" width="100%" height="99%"></iframe>',
        html: true,
        closeOnConfirm: true,
        showCancelButton: true,
        allowOutsideClick: true,
    }, function (isApproved) {
        $(".sweet-alert").removeClass("change-log-summary");
        swal.close(); // Close the SweetAlert dialog
        if (isApproved) {
            $('div.spinner.fixed').show();
            setTimeout(() => {
                $('div.spinner.fixed').hide();
                updateContainer(container);
            }, 500);
        }
        changeLog_nchan.stop()
    });

    $(".sweet-alert").addClass("change-log-summary");
    $('#myIframe').parent().addClass("change-log-iframe-container");

    $.get(url, {}, function (data) { });
}