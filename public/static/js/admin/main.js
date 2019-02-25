$(function ($) {
    var hashPath = location.hash.substring(1);
    if (hashPath.length === 0) {
        route('Dashboard', true);
    } else {
        route(hashPath, true);
    }
    $('a[data-href]').bind('click', function () {
        var url = $(this).attr('data-href');
        route(url, false);
    });
    $('.exit').click(function () {
        $.getJSON('/auth/admin/exit', function (data) {
            swal({
                title:'',
                text: data['msg'],
                showConfirmButton: false,
                timer: 1500,
                type: 'success'
            });
            setTimeout(function () {
                window.location.href = baseUrl + 'admin/Login';
            }, 1500);
        });
    });
    feather.replace();
});

function route(url, isFirst, args, isGetPageData) {
    isGetPageData = isGetPageData !== undefined;
    var html = '';
    if (!isGetPageData) {
        var sidebarDom = $('.sidebar-sticky');
        var clickDom = sidebarDom.find('a[data-href="' + url + '"]');
        if (clickDom.is('.active') && !isFirst) {
            return;
        }
        location.hash = url;
        sidebarDom.find('a.active').removeClass('active');

        clickDom.addClass('active');
    }
    $.ajax({
        url: baseUrl + 'admin/' + url,
        async: !isGetPageData,
        type: 'get',
        cache: true,
        success: function (data) {
            if (isGetPageData) {
                html = data;
                return true;
            }

            window.history.pushState(null, null, baseUrl + 'admin/Index#' + url);
            //增加历史地址
            $('#main').html(data);
            feather.replace();
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            if (isGetPageData)
                return undefined;
            switch (XMLHttpRequest['status']) {
                case 404:
                    if (isFirst) {
                        route('Dashboard');
                    } else {
                        swal('页面未找到', '页面正在抓紧施工中。。。', 'error');
                    }
                    break;
                case 500:
                    if (isFirst) {
                        route('Dashboard');
                    } else {
                        swal('服务器异常', '当您看到这个提示时请截图相关信息给管理员', 'error');
                    }
                    break;
                default:
                    if (isFirst) {
                        route('Dashboard');
                    } else {
                        swal('请求页面错误', '很抱歉页面请求错误了...', 'error');
                    }
                    break;
            }
        }
    });
    if (isGetPageData)
        return html;
}