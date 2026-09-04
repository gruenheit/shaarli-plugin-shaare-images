(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('shaare-images-btn');
        if (!btn) {
            return;
        }

        var dialog = document.getElementById('shaare-images-dialog');
        var urlInput = document.getElementById('shaare-images-url');
        var altInput = document.getElementById('shaare-images-alt');
        var status = document.getElementById('shaare-images-status');
        var insertBtn = document.getElementById('shaare-images-insert');
        var cancelBtn = document.getElementById('shaare-images-cancel');

        var widthSmall = parseInt(btn.dataset.widthSmall, 10) || 250;
        var widthLarge = parseInt(btn.dataset.widthLarge, 10) || 600;

        var i18n;
        try {
            i18n = JSON.parse(btn.dataset.i18n);
        } catch (e) {
            i18n = {};
        }
        i18n.statusOk = i18n.statusOk || 'Image is {w}×{h} px — fits both sizes.';
        i18n.statusWarn = i18n.statusWarn
            || 'Image is only {w}×{h} px — would be upscaled and blurry for "{size}" ({width} px).';
        i18n.statusError = i18n.statusError || 'Not a valid image URL — image could not be loaded.';
        i18n.small = i18n.small || 'Small';
        i18n.large = i18n.large || 'Large';

        function fillTemplate(template, values) {
            return template.replace(/\{(\w+)\}/g, function (match, key) {
                return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : match;
            });
        }

        var checkToken = 0;

        function setStatus(text, kind) {
            status.textContent = text;
            status.className = 'shaare-images-status' + (kind ? ' shaare-images-' + kind : '');
        }

        function insertAtCursor(textarea, text) {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var before = textarea.value.substring(0, start);
            var after = textarea.value.substring(end);
            textarea.value = before + text + after;
            var pos = start + text.length;
            textarea.selectionStart = textarea.selectionEnd = pos;
            textarea.focus();
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        }

        btn.addEventListener('click', function () {
            urlInput.value = '';
            altInput.value = '';
            setStatus('', null);
            insertBtn.disabled = true;
            dialog.showModal();
            urlInput.focus();
        });

        cancelBtn.addEventListener('click', function () {
            dialog.close();
        });

        urlInput.addEventListener('input', function () {
            var url = urlInput.value.trim();
            insertBtn.disabled = true;
            setStatus('', null);

            if (!url) {
                return;
            }

            var myToken = ++checkToken;
            var probe = new Image();

            probe.onload = function () {
                if (myToken !== checkToken) {
                    return;
                }
                var w = probe.naturalWidth;
                var h = probe.naturalHeight;
                if (w < widthLarge) {
                    setStatus(
                        fillTemplate(i18n.statusWarn, { w: w, h: h, size: i18n.large, width: widthLarge }),
                        'warn'
                    );
                } else {
                    setStatus(fillTemplate(i18n.statusOk, { w: w, h: h }), 'ok');
                }
                insertBtn.disabled = false;
            };

            probe.onerror = function () {
                if (myToken !== checkToken) {
                    return;
                }
                setStatus(i18n.statusError, 'error');
                insertBtn.disabled = true;
            };

            probe.src = url;
        });

        insertBtn.addEventListener('click', function () {
            if (insertBtn.disabled) {
                return;
            }

            var url = urlInput.value.trim();
            if (!url) {
                return;
            }

            var alt = altInput.value.trim().replace(/[\]|]/g, '') || 'Image';
            var sizeInput = document.querySelector('input[name="shaare-images-size"]:checked');
            var size = sizeInput ? sizeInput.value : 'small';

            var textarea = document.querySelector('textarea[name="lf_description"]');
            if (textarea) {
                insertAtCursor(textarea, '![' + alt + '|' + size + '](' + url + ')');
            }

            dialog.close();
        });
    });
})();
