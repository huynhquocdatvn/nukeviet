/**
 * NukeViet Content Management System
 * @version 4.x
 * @author VINADES.,JSC <contact@vinades.vn>
 * @copyright (C) 2009-2021 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 * @see https://github.com/nukeviet The NukeViet CMS GitHub project
 */

$(function() {
    // Hiển thị chi tiết ứng dụng
    let mdEd = $('#mdExtDetail');
    if (mdEd.length) {
        $('.ex-detail').on('click', function(e) {
            e.preventDefault();
            let btn = $(this);
            let icon = $('i', btn);
            if (icon.is('.fa-spinner')) {
                return;
            }
            $('#mdExtDetailLabel').html(btn.data('title'));
            icon.removeClass(icon.data('icon')).addClass('fa-spinner fa-spin-pulse');
            $.ajax({
                type: 'GET',
                url: btn.data('url') + '&popup=1&nocache=' + new Date().getTime(),
                success: function(res) {
                    icon.removeClass('fa-spinner fa-spin-pulse').addClass(icon.data('icon'));
                    $('.modal-body', mdEd).html(res);
                    $('[data-bs-toggle="tooltip"]', mdEd).each(function() {
                        new bootstrap.Tooltip(this);
                    });
                    bootstrap.Modal.getOrCreateInstance(mdEd[0]).show();
                },
                error: function(xhr, text, err) {
                    icon.removeClass('fa-spinner fa-spin-pulse').addClass(icon.data('icon'));
                    nvToast(err, 'error');
                    console.log(xhr, text, err);
                }
            });
        });
    }

    // Tải về file cài đặt ứng dụng
    function downloadExt() {
        let ctn = $('[data-toggle="checkExtCtnDownload"]');
        let icon = $('i', ctn);
        let indicator = $('[data-toggle="checkExtIndicate"]');

        ctn.removeClass('d-none');
        setTimeout(() => {
            $.ajax({
                type: 'POST',
                url: script_name,
                data: nv_lang_variable + '=' + nv_lang_data + '&' + nv_name_variable + '=' + nv_module_name + '&' + nv_fc_variable + '=download&data=' + ctn.data('jsonencode'),
                success: function(e) {
                    e = e.split('|');
                    if (e[0] == 'OK') {
                        icon.removeClass('fa-spinner fa-spin-pulse').addClass('fa-circle-check');
                        ctn.addClass('text-success');
                        indicator.removeClass('text-bg-primary').addClass('text-bg-success');
                        $('span', ctn).html(ctn.data('lang-ok'));
                        setTimeout(() => {
                            window.location = script_name + '?' + nv_lang_variable + '=' + nv_lang_data + '&' + nv_name_variable + '=extensions&' + nv_fc_variable + '=upload&uploaded=1';
                        }, 3000);
                        return;
                    }

                    icon.removeClass('fa-spinner fa-spin-pulse').addClass('fa-circle-xmark');
                    ctn.addClass('text-danger');
                    indicator.removeClass('text-bg-primary').addClass('text-bg-danger');
                    $('span', ctn).html(e[1]);
                },
                error: function(xhr, text, err) {
                    icon.removeClass('fa-spinner fa-spin-pulse').addClass('fa-circle-xmark');
                    ctn.addClass('text-danger');
                    indicator.removeClass('text-bg-primary').addClass('text-bg-danger');
                    nvToast(err, 'error');
                    console.log(xhr, text, err);
                }
            });
        }, 500);
    }

    // Xác nhận cài ứng dụng
    $('[data-toggle="checkExtConfirm"]').on('click', function(e) {
        e.preventDefault();
        $('[data-toggle="checkExtWarning"]').addClass('d-none');
        $('[data-toggle="checkExtIndicate"]').removeClass('text-bg-warning').addClass('text-bg-primary');
        downloadExt();
    });

    // Tự động tải về
    let autoDownExt = $('[data-toggle="checkExtAutoDownload"]');
    if (autoDownExt.length) {
        downloadExt();
    }
});
