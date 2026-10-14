/**
 * WPCalibrate HTML Bridge - Admin JavaScript Controller
 */
(function ($) {
    'use strict';

    const config = window.wpcalibrateHtmlBridge || {};

    $(document).ready(function () {
        initWizard();
        initHistoryActions();
        initDemoActions();
    });

    function initWizard() {
        const $wizard = $('#wpcalibrate-wizard');
        if (!$wizard.length) return;

        const $fileInput = $('#wpcalibrate-file-input');
        const $dropzone = $('#wpcalibrate-upload-form');
        const $filenameDisplay = $('#wpcalibrate-selected-filename');
        const $uploadProgress = $('#wpcalibrate-upload-progress');
        const $uploadErrors = $('#wpcalibrate-upload-errors');

        // Check if resuming from server-provided manifest data
        if (window.wpcalibrateResumeData) {
            populatePreview(window.wpcalibrateResumeData);
            goToStep(2);
        }

        // Drag & Drop
        $dropzone.on('dragover dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $dropzone.addClass('dragover');
        });

        $dropzone.on('dragleave drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $dropzone.removeClass('dragover');
        });

        $dropzone.on('drop', function (e) {
            const files = e.originalEvent.dataTransfer.files;
            if (files.length) {
                $fileInput[0].files = files;
                handleFileUpload(files[0]);
            }
        });

        $fileInput.on('change', function () {
            if (this.files.length) {
                handleFileUpload(this.files[0]);
            }
        });

        function handleFileUpload(file) {
            $filenameDisplay.text(file.name);
            $uploadErrors.addClass('hidden').find('p').empty();
            $uploadProgress.removeClass('hidden');

            const formData = new FormData();
            formData.append('action', 'wpcalibrate_html_bridge_upload');
            formData.append('nonce', config.nonce);
            formData.append('package', file);

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function () {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function (evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = Math.round((evt.loaded / evt.total) * 90);
                            $uploadProgress.find('.wpcalibrate-progress-fill').css('width', percentComplete + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function (res) {
                    $uploadProgress.addClass('hidden');
                    if (res.success && res.data) {
                        populatePreview(res.data);
                        goToStep(2);
                    } else {
                        showUploadError(res.data ? res.data.message : config.i18n.genericError);
                    }
                },
                error: function (xhr) {
                    $uploadProgress.addClass('hidden');
                    let msg = config.i18n.genericError;
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = xhr.responseJSON.data.message;
                    }
                    showUploadError(msg);
                }
            });
        }

        function showUploadError(message) {
            $uploadErrors.removeClass('hidden').find('p').text(message);
        }

        function populatePreview(data) {
            $('#wpcalibrate-import-id').val(data.import_id);
            const m = data.manifest;

            $('#metric-pages').text(Object.keys(m.pages || {}).length);
            $('#metric-assets').text((m.images || []).length + (m.css_files || []).length + (m.js_files || []).length + (m.fonts || []).length);
            $('#metric-internal-links').text(m.internal_links_count || 0);
            $('#metric-external-links').text(m.external_links_count || 0);
            $('#metric-forms').text((m.forms || []).length);

            // Warnings
            const $warnPanel = $('#wpcalibrate-scan-warnings');
            const $warnList = $('#wpcalibrate-warnings-list').empty();
            if (data.warnings && data.warnings.length) {
                data.warnings.forEach(w => $warnList.append($('<li>').text(w)));
                $warnPanel.removeClass('hidden');
            } else {
                $warnPanel.addClass('hidden');
            }

            // Populate Pages Table
            const $tbody = $('#wpcalibrate-pages-tbody').empty();
            const pages = m.pages || {};
            const homepageKey = m.homepage || '';

            Object.keys(pages).forEach(relPath => {
                const p = pages[relPath];
                const isHome = (relPath === homepageKey);
                const $row = $('<tr>').html(`
                    <td><input type="checkbox" name="pages[${escapeHtml(relPath)}][included]" value="1" checked /></td>
                    <td><code>${escapeHtml(relPath)}</code></td>
                    <td><input type="text" name="pages[${escapeHtml(relPath)}][title]" value="${escapeHtml(p.title)}" class="regular-text" style="width: 100%;" /></td>
                    <td><input type="text" name="pages[${escapeHtml(relPath)}][slug]" value="${escapeHtml(p.slug)}" class="regular-text" style="width: 100%;" /></td>
                    <td style="text-align: center;"><input type="radio" name="homepage" value="${escapeHtml(relPath)}" ${isHome ? 'checked' : ''} /></td>
                `);
                $tbody.append($row);
            });
        }

        // Mode switching
        $('input[name="import_mode"]').on('change', function () {
            if ($(this).val() === 'theme') {
                $('#wpcalibrate-theme-config').removeClass('hidden');
                $('#wpcalibrate-pages-config').addClass('hidden');
            } else {
                $('#wpcalibrate-theme-config').addClass('hidden');
                $('#wpcalibrate-pages-config').removeClass('hidden');
            }
        });

        // Select all pages toggle
        $('#wpcalibrate-select-all-pages').on('change', function () {
            const checked = $(this).is(':checked');
            $('#wpcalibrate-pages-tbody input[type="checkbox"]').prop('checked', checked);
        });

        // Cancel button
        $('#wpcalibrate-btn-cancel').on('click', function () {
            if (confirm('Cancel and restart import?')) {
                location.href = location.pathname + '?page=wpcalibrate-html-bridge&wpcalibrate_tab=new_import';
            }
        });

        // Run Import Button
        $('#wpcalibrate-btn-run-import').on('click', function () {
            const formData = $('#wpcalibrate-configure-form').serialize() + '&action=wpcalibrate_html_bridge_start_import';

            $(this).prop('disabled', true).text('Starting...');

            $.post(config.ajaxUrl, formData, function (res) {
                if (res.success && res.data) {
                    goToStep(3);
                    executeBatchLoop(res.data.import_id);
                } else {
                    alert(res.data ? res.data.message : config.i18n.genericError);
                    $('#wpcalibrate-btn-run-import').prop('disabled', false).text('Run Import Now');
                }
            }).fail(function () {
                alert(config.i18n.genericError);
                $('#wpcalibrate-btn-run-import').prop('disabled', false).text('Run Import Now');
            });
        });

        function executeBatchLoop(importId) {
            function nextBatch() {
                $.post(config.ajaxUrl, {
                    action: 'wpcalibrate_html_bridge_batch',
                    import_id: importId,
                    nonce: config.nonce
                }, function (res) {
                    if (res.success && res.data) {
                        const d = res.data;
                        $('#wpcalibrate-exec-progress-fill').css('width', d.progress + '%');
                        $('#wpcalibrate-exec-status-text').text(d.message);

                        if (d.is_complete) {
                            renderReport(d);
                            goToStep(4);
                        } else {
                            setTimeout(nextBatch, 300);
                        }
                    } else {
                        $('#wpcalibrate-exec-status-text').text('Error during import: ' + (res.data ? res.data.message : 'Unknown'));
                    }
                }).fail(function (xhr) {
                    let msg = config.i18n.genericError;
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = xhr.responseJSON.data.message;
                    }
                    $('#wpcalibrate-exec-status-text').text('Error: ' + msg);
                });
            }

            nextBatch();
        }

        function renderReport(data) {
            const $rep = $('#wpcalibrate-report-summary').empty();
            $rep.append($('<p>').addClass('description').text(data.message));

            if (data.warnings && data.warnings.length) {
                const $wBox = $('<div>').addClass('wpcalibrate-warnings-panel').append('<h3>Conversion Warnings:</h3>');
                const $ul = $('<ul>');
                data.warnings.forEach(w => $ul.append($('<li>').text(w)));
                $wBox.append($ul);
                $rep.append($wBox);
            }
        }

        function goToStep(step) {
            $('.wpcalibrate-step-panel').addClass('hidden');
            $('#wpcalibrate-step-' + step).removeClass('hidden');

            $('.wpcalibrate-step').each(function () {
                const s = parseInt($(this).data('step'), 10);
                if (s <= step) {
                    $(this).addClass('active');
                } else {
                    $(this).removeClass('active');
                }
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    }

    function initHistoryActions() {
        // Rollback action
        $(document).on('click', '.wpcalibrate-btn-rollback', function () {
            const importId = $(this).data('import-id');
            if (!confirm(config.i18n.confirmRollback)) return;

            const $btn = $(this).prop('disabled', true).text(config.i18n.rollingBack);

            $.post(config.ajaxUrl, {
                action: 'wpcalibrate_html_bridge_rollback',
                import_id: importId,
                nonce: config.nonce
            }, function (res) {
                if (res.success) {
                    alert('Rollback completed.');
                    location.reload();
                } else {
                    alert(res.data ? res.data.message : config.i18n.genericError);
                    $btn.prop('disabled', false).text('Rollback');
                }
            }).fail(function () {
                alert(config.i18n.genericError);
                $btn.prop('disabled', false).text('Rollback');
            });
        });

        // Delete action
        $(document).on('click', '.wpcalibrate-btn-delete', function () {
            const importId = $(this).data('import-id');
            if (!confirm('Are you sure you want to delete this import history record?')) return;

            const $row = $(this).closest('tr');

            $.post(config.ajaxUrl, {
                action: 'wpcalibrate_html_bridge_delete_import',
                import_id: importId,
                nonce: config.nonce
            }, function (res) {
                if (res.success) {
                    $row.fadeOut(300, function () { $(this).remove(); });
                } else {
                    alert(res.data ? res.data.message : config.i18n.genericError);
                }
            });
        });
    }

    function initDemoActions() {
        const $modal = $('#wpcalibrate-preview-modal');
        const $frame = $('#wpcalibrate-preview-frame');
        const $loader = $('#preview-loader');
        const $deviceWrapper = $('#wpcalibrate-preview-device-wrapper');
        const $title = $('#preview-modal-title');
        const $tag = $('#preview-modal-tag');
        const $btnImport = $('#preview-btn-import');
        const $btnDownload = $('#preview-btn-download');

        // Open Preview Modal
        $(document).on('click', '.wpcalibrate-btn-demo-preview', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const previewUrl = $btn.attr('data-preview-url') || $btn.data('preview-url') || '';
            const templateId = $btn.attr('data-template-id') || $btn.data('template-id') || '';
            const title = $btn.attr('data-title') || $btn.data('title') || 'Template Preview';
            const tag = $btn.attr('data-tag') || $btn.data('tag') || 'Demo';
            const zipUrl = $btn.attr('data-zip-url') || $btn.data('zip-url') || '#';

            if (!previewUrl) {
                return;
            }

            $('#preview-modal-title').text(title);
            $('#preview-modal-tag').text(tag);
            $('#preview-btn-import').data('template-id', templateId).attr('data-template-id', templateId);
            $('#preview-btn-download').attr('href', zipUrl).attr('download', templateId + '.zip');
            $('#preview-btn-external').attr('href', previewUrl);

            // Reset viewport to desktop
            $('.wpcalibrate-viewport-btn').removeClass('active');
            $('.wpcalibrate-viewport-btn[data-viewport="desktop"]').addClass('active');
            $('#wpcalibrate-preview-device-wrapper')
                .removeClass('preview-device-tablet preview-device-mobile')
                .addClass('preview-device-desktop');

            // Show loader and set frame source
            const $frame = $('#wpcalibrate-preview-frame');
            const $loader = $('#preview-loader');
            $loader.show();

            $frame.off('load.preview').on('load.preview', function () {
                $loader.fadeOut(150);
            });

            $frame.attr('src', previewUrl);

            $('#wpcalibrate-preview-modal').removeClass('hidden').css('display', 'flex');
            $('body').addClass('wpcalibrate-modal-open');
        });

        // Close Preview Modal
        function closePreviewModal() {
            $('#wpcalibrate-preview-modal').addClass('hidden').css('display', 'none');
            $('#wpcalibrate-preview-frame').attr('src', 'about:blank');
            $('body').removeClass('wpcalibrate-modal-open');
        }

        $(document).on('click', '#preview-modal-close, .wpcalibrate-preview-backdrop', function (e) {
            e.preventDefault();
            closePreviewModal();
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && !$('#wpcalibrate-preview-modal').hasClass('hidden')) {
                closePreviewModal();
            }
        });

        // Device Viewport Switcher
        $(document).on('click', '.wpcalibrate-viewport-btn', function (e) {
            e.preventDefault();
            const viewport = $(this).data('viewport');

            $('.wpcalibrate-viewport-btn').removeClass('active');
            $(this).addClass('active');

            $deviceWrapper.removeClass('preview-device-desktop preview-device-tablet preview-device-mobile');
            if (viewport === 'tablet') {
                $deviceWrapper.addClass('preview-device-tablet');
            } else if (viewport === 'mobile') {
                $deviceWrapper.addClass('preview-device-mobile');
            } else {
                $deviceWrapper.addClass('preview-device-desktop');
            }
        });

        // One-Click Demo Import (from card or modal)
        $(document).on('click', '.wpcalibrate-btn-demo-import, #preview-btn-import', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const templateId = $btn.data('template-id');
            const originalHtml = $btn.html();
            const loadingText = $btn.data('loading-text') || 'Staging Demo...';

            $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none; margin:0 5px 0 0; vertical-align:middle;"></span> ' + loadingText);

            $.post(config.ajaxUrl, {
                action: 'wpcalibrate_html_bridge_demo_import',
                template_id: templateId,
                nonce: config.nonce
            }, function (res) {
                if (res.success && res.data && res.data.import_id) {
                    const baseUrl = config.adminUrl || 'admin.php';
                    const redirectUrl = baseUrl + '?page=wpcalibrate-html-bridge&wpcalibrate_tab=new_import&resume_import=' + encodeURIComponent(res.data.import_id);
                    window.location.href = redirectUrl;
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : (config.i18n ? config.i18n.genericError : 'Failed to import demo template.');
                    alert(msg);
                    $btn.prop('disabled', false).html(originalHtml);
                }
            }).fail(function (xhr) {
                let msg = config.i18n ? config.i18n.genericError : 'Server connection error.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                alert(msg);
                $btn.prop('disabled', false).html(originalHtml);
            });
        });
    }

})(jQuery);
