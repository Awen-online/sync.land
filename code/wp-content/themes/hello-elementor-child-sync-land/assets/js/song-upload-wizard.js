(function ($) {
    'use strict';

    var ARTIST_ID = '';
    var DRAFT_KEY = '';
    var currentStep = 1;
    var totalSteps = 4;
    var initialized = false;

    /* =========================================================================
       Step Navigation
       ========================================================================= */

    function showStep(n) {
        currentStep = n;
        $('.wizard-step').removeClass('active');
        $('#wizard-step-' + n).addClass('active');

        // Update progress bar
        $('.wizard-progress li').each(function () {
            var step = parseInt($(this).data('step'), 10);
            $(this).removeClass('active completed');
            if (step === n) $(this).addClass('active');
            else if (step < n) $(this).addClass('completed');
        });

        // Build review when entering step 4
        if (n === 4) buildReview();

        // Refresh live UI state for the new step
        updateTagCounters();
        updateNextButtonState();

        // Scroll to top of form
        $('html, body').animate({
            scrollTop: $('#songs-upload-form').offset().top - 20
        }, 300);
    }

    function nextStep() {
        if (!validateStep(currentStep)) return;
        if (currentStep < totalSteps) {
            showStep(currentStep + 1);
            saveDraft(true);
        }
    }

    function prevStep() {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    }

    /* =========================================================================
       Inline error rendering (replaces alert() — alerts are silent on mobile)
       ========================================================================= */

    function showStepError(step, items) {
        var $box = $('#wizard-step-' + step + '-error');
        if (!$box.length) return;
        if (!items || items.length === 0) {
            $box.removeClass('is-visible').empty();
            return;
        }
        var html = '<strong>Before continuing, please fix the following:</strong><ul>';
        items.forEach(function (it) {
            if (it.jumpTo) {
                html += '<li><a class="error-jump" data-jump="' + it.jumpTo + '">' + escapeHtml(it.text) + '</a></li>';
            } else {
                html += '<li>' + escapeHtml(it.text) + '</li>';
            }
        });
        html += '</ul>';
        $box.html(html).addClass('is-visible');
        $('html, body').animate({ scrollTop: $box.offset().top - 80 }, 250);
    }

    function clearStepError(step) {
        $('#wizard-step-' + step + '-error').removeClass('is-visible').empty();
    }

    /* =========================================================================
       Error collectors — return array of {text, jumpTo?} per step
       ========================================================================= */

    function getStep1Errors() {
        var errors = [];
        var $step = $('#wizard-step-1');
        $step.find('input[required], select[required], textarea[required]').each(function () {
            if (this.checkValidity()) return;
            var $field = $(this).closest('.form-field, .album-art-field');
            var label = $field.find('label').first().contents().filter(function () {
                return this.nodeType === 3;
            }).first().text().trim() || $field.find('label').first().text().trim() || this.name;
            label = label.replace(/\s*\*\s*$/, '').trim();
            var msg = this.validationMessage || 'is required';
            errors.push({ text: label + ' — ' + msg });
        });
        return errors;
    }

    function getStep2Errors() {
        var errors = [];
        var ccby = $('#ccby-toggle').is(':checked');
        var commercial = $('#commercial-licensing-toggle').is(':checked');
        if (!ccby && !commercial) {
            errors.push({ text: 'Choose at least one license type (CC-BY 4.0 or Commercial Sync).' });
        }
        return errors;
    }

    function getStep3Errors() {
        var errors = [];
        var songCount = $('#songs-upload .song').length;
        if (songCount === 0) {
            errors.push({ text: 'Please select the number of tracks first.' });
            return errors;
        }
        $('#songs-upload .song').each(function (idx) {
            var $song = $(this);
            var trackNum = idx + 1;
            var trackId = 'song-track-' + trackNum;
            $song.attr('id', trackId);

            var missing = [];
            if (!$song.find('input[name="title' + trackNum + '"]').val()) missing.push('title');
            if (!$song.find('input[name="explicit' + trackNum + '"]:checked').length) missing.push('explicit answer');
            if (!$song.find('input[name="instrumental' + trackNum + '"]:checked').length) missing.push('instrumental answer');
            if (!$song.find('input.awslink').val()) missing.push('audio file');
            var moods = $song.find('.mood-checkbox:checked').length;
            var genres = $song.find('.genre-checkbox:checked').length;
            if (moods < 1) missing.push('mood (tap 1–3 pills)');
            if (genres < 1) missing.push('genre (tap 1–3 pills)');

            if (missing.length) {
                errors.push({
                    text: 'Track ' + trackNum + ' needs: ' + missing.join(', ') + '.',
                    jumpTo: trackId
                });
            }
        });
        return errors;
    }

    function getStep4Errors() {
        var errors = [];
        if (!$('input[name="rightsholder"]').is(':checked')) {
            errors.push({ text: 'Please confirm you are the rights-holder or authorized.' });
        }
        if (!$('input[name="termsandcopyright"]').is(':checked')) {
            errors.push({ text: 'Please agree to the Terms of Use and Copyright Policy.' });
        }
        return errors;
    }

    /* =========================================================================
       Live counters: update "Mood — N selected" badges and is-satisfied state
       ========================================================================= */

    function updateTagCounters() {
        $('#songs-upload .song').each(function () {
            var $song = $(this);
            var moodCount = $song.find('.mood-checkbox:checked').length;
            var genreCount = $song.find('.genre-checkbox:checked').length;
            var $moodBadge = $song.find('[data-tag-count="mood"]');
            var $genreBadge = $song.find('[data-tag-count="genre"]');
            $moodBadge.find('.tag-count-value').text(moodCount);
            $genreBadge.find('.tag-count-value').text(genreCount);
            $moodBadge.toggleClass('is-satisfied', moodCount >= 1);
            $genreBadge.toggleClass('is-satisfied', genreCount >= 1);
        });
    }

    /* =========================================================================
       Next/Submit button enabled state for the current step
       ========================================================================= */

    function updateNextButtonState() {
        var errs = [];
        if (currentStep === 1) errs = getStep1Errors();
        else if (currentStep === 2) errs = getStep2Errors();
        else if (currentStep === 3) errs = getStep3Errors();
        else if (currentStep === 4) errs = getStep4Errors();

        var $step = $('#wizard-step-' + currentStep);
        $step.find('.wizard-btn-next, .wizard-btn-submit').prop('disabled', errs.length > 0);

        // If errors box is already visible, refresh its content live as user edits
        var $box = $('#wizard-step-' + currentStep + '-error');
        if ($box.hasClass('is-visible')) {
            if (errs.length === 0) clearStepError(currentStep);
            else $box.find('ul').remove();
            // re-render without scroll (only on validate click)
            if (errs.length) {
                var html = '<strong>Before continuing, please fix the following:</strong><ul>';
                errs.forEach(function (it) {
                    if (it.jumpTo) {
                        html += '<li><a class="error-jump" data-jump="' + it.jumpTo + '">' + escapeHtml(it.text) + '</a></li>';
                    } else {
                        html += '<li>' + escapeHtml(it.text) + '</li>';
                    }
                });
                html += '</ul>';
                $box.html(html);
            }
        }
    }

    /* =========================================================================
       Per-Step Validation
       ========================================================================= */

    function validateStep(step) {
        var errs = [];
        if (step === 1) errs = getStep1Errors();
        else if (step === 2) errs = getStep2Errors();
        else if (step === 3) errs = getStep3Errors();
        else if (step === 4) errs = getStep4Errors();

        if (errs.length) {
            showStepError(step, errs);
            // Flash red border on offending tracks
            errs.forEach(function (e) {
                if (e.jumpTo) {
                    var $el = $('#' + e.jumpTo);
                    $el.css('border-color', '#dc3545');
                    setTimeout(function () { $el.css('border-color', ''); }, 5000);
                }
            });
            return false;
        }
        clearStepError(step);

        if (step === 2) {
            $('.licensing-warning').removeClass('visible');
        }
        return true;
    }

    /* =========================================================================
       Album Art Preview
       ========================================================================= */

    function handleAlbumArt(input) {
        if (!input.files || !input.files[0]) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            var img = new Image();
            img.src = e.target.result;
            img.onload = function () {
                var $preview = $('.album-art-preview');
                $preview.find('img').attr('src', img.src);
                $preview.addClass('has-image');

                var minS = 1000, maxS = 5000;
                if (img.width < minS || img.height < minS || img.width > maxS || img.height > maxS) {
                    input.setCustomValidity(
                        'Image must be ' + minS + '-' + maxS + 'px. Yours is ' + img.width + 'x' + img.height + '.'
                    );
                    input.reportValidity();
                } else if (img.width !== img.height) {
                    input.setCustomValidity('Image must be square.');
                    input.reportValidity();
                } else {
                    input.setCustomValidity('');
                }
            };
        };
        reader.readAsDataURL(input.files[0]);
    }

    /* =========================================================================
       Song Block Generation
       ========================================================================= */

    function generateSongBlocks(count) {
        var $container = $('#songs-upload');
        var templateHTML = $('.song-template').html();
        $container.empty();

        for (var i = 1; i <= count; i++) {
            var $block = $('<div class="song"></div>').html(templateHTML);

            // Set track number
            $block.find('.tracknumber-value').text(i);
            $block.find('input[name="tracknumber"]').val(i);

            // Rename inputs with track index
            $block.find('input, select, textarea').each(function () {
                var name = $(this).attr('name');
                if (!name) return;
                name = name.replace(/__IDX__/g, i);
                $(this).attr('name', name);
            });

            $container.append($block);
        }

        // Update hidden track count
        $('input.numberoftracks').val(count);

        initializeSongUploads();
        initializeCheckboxLimits();
        updateTagCounters();
        updateNextButtonState();
    }

    /* =========================================================================
       Checkbox Limits (Mood / Genre — max 3)
       ========================================================================= */

    function initializeCheckboxLimits() {
        $('#songs-upload .song').each(function () {
            var $song = $(this);

            setupLimit($song, '.mood-checkbox', 3);
            setupLimit($song, '.genre-checkbox', 3);
        });
    }

    function setupLimit($song, selector, max) {
        var $boxes = $song.find(selector);
        $boxes.off('change.limit').on('change.limit', function () {
            var checked = $boxes.filter(':checked').length;
            if (checked >= max) {
                $boxes.not(':checked').prop('disabled', true)
                    .closest('.tag-pill').addClass('is-disabled');
            } else {
                $boxes.prop('disabled', false)
                    .closest('.tag-pill').removeClass('is-disabled');
            }
            updateTagCounters();
            updateNextButtonState();
        });
    }

    /* =========================================================================
       File Uploads (simpleUpload.js)
       ========================================================================= */

    function initializeSongUploads() {
        $('#songs-upload').off('click', '.uploadbtn').on('click', '.uploadbtn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn = $(this);
            var $song = $btn.closest('.song');
            var $file = $song.find('input[type=file].file-input');

            if (!$file[0] || !$file[0].files || !$file[0].files[0]) {
                alert('Please select a file first.');
                return;
            }

            var fileName = $file[0].files[0].name;
            $btn.prop('disabled', true).text('Uploading...');

            $file.simpleUpload('https://www.soil.sync.land/s3-upload.php', {
                allowedExts: ['mp3', 'wav'],
                expect: 'json',
                start: function () {
                    $song.find('.filename').html(
                        '<strong>' + fileName + '</strong> <span class="upload-status uploading">Uploading</span>'
                    );
                    $song.find('.progress').text('0%');
                    $song.find('.progressBar').removeClass('success error').css('width', '0%');
                    $file.prop('disabled', true);
                },
                progress: function (pct) {
                    var r = Math.round(pct);
                    if (r >= 100) r = 99;
                    $song.find('.progress').text(r + '%');
                    $song.find('.progressBar').css('width', pct + '%');
                },
                success: function (data) {
                    if (data && data.success) {
                        $song.find('.progress').text('Complete!');
                        $song.find('.progressBar').addClass('success').css('width', '100%');
                        $song.find('.filename').html(
                            '<strong>' + fileName + '</strong> <span class="upload-status success">Uploaded</span>'
                        );
                        $song.find('input.awslink').val(data.url);
                        $btn.remove();
                        $file.remove();
                        $song.find('.mp3Label').remove();
                        // Save draft immediately so S3 URL isn't lost
                        saveDraft(true);
                        draftStatus('Draft saved', true);
                    } else {
                        var msg = (data && data.error) ? data.error : 'Upload failed';
                        $song.find('.progress').text('Failed: ' + msg);
                        $song.find('.progressBar').addClass('error').css('width', '100%');
                        $song.find('.filename').html(
                            '<strong>' + fileName + '</strong> <span class="upload-status error">Failed</span>'
                        );
                        $btn.prop('disabled', false).text('Retry Upload');
                        $file.prop('disabled', false);
                    }
                },
                error: function (err) {
                    var msg = err.message || 'Network error';
                    $song.find('.progress').text('Error: ' + msg);
                    $song.find('.progressBar').addClass('error').css('width', '100%');
                    $btn.prop('disabled', false).text('Retry Upload');
                    $file.prop('disabled', false);
                }
            });
        });
    }

    /* =========================================================================
       Licensing Toggle
       ========================================================================= */

    function initLicensing() {
        var $ccby = $('#ccby-toggle');
        var $commercial = $('#commercial-licensing-toggle');

        function update() {
            var cc = $ccby.is(':checked');
            var cm = $commercial.is(':checked');

            // Toggle card active state
            $ccby.closest('.license-option').toggleClass('is-active', cc);
            $commercial.closest('.license-option').toggleClass('is-active', cm);

            // Warning
            $('.licensing-warning').toggleClass('visible', !cc && !cm);

            // Pricing field
            $('.commercial-pricing').toggleClass('visible', cm);
        }

        $ccby.on('change', update);
        $commercial.on('change', update);
        update(); // initial state
    }

    /* =========================================================================
       Review Builder
       ========================================================================= */

    function buildReview() {
        var $r = $('#review-content');
        $r.empty();

        // Album info
        var albumTitle = $('input[name="album-title"]').val() || '(untitled)';
        var albumDesc = $('textarea[name="albumdescription"]').val() || '';
        var releaseDate = $('input[name="releasedate"]').val() || '';
        var contentID = $('input[name="youtube-contentID"]:checked').val() || '';

        // Distros
        var distros = [];
        $('input[name="distros[]"]:checked').each(function () {
            distros.push($(this).val());
        });

        // Album art preview src
        var artSrc = $('.album-art-preview img').attr('src') || '';

        var albumHTML = '<div class="review-section"><h3>Release Info</h3>';
        if (artSrc && artSrc !== '#') {
            albumHTML += '<div style="margin-bottom:12px"><img class="review-album-art" src="' + artSrc + '" alt=""></div>';
        }
        albumHTML += reviewRow('Title', albumTitle);
        if (albumDesc) albumHTML += reviewRow('Description', truncate(albumDesc, 100));
        if (releaseDate) albumHTML += reviewRow('Release Date', releaseDate);
        if (distros.length) albumHTML += reviewRow('Distribution', distros.map(capitalize).join(', '));
        if (contentID) albumHTML += reviewRow('YouTube Content ID', contentID);
        albumHTML += '</div>';

        // Licensing
        var ccby = $('#ccby-toggle').is(':checked');
        var commercial = $('#commercial-licensing-toggle').is(':checked');
        var price = $('input[name="commercial_price"]').val() || '49.00';

        var licHTML = '<div class="review-section"><h3>Licensing</h3>';
        if (ccby) licHTML += reviewRow('CC-BY 4.0', 'Enabled (Free, MP3)');
        if (commercial) licHTML += reviewRow('Commercial Sync', 'Enabled at $' + price + '/song');
        if (!ccby && !commercial) licHTML += reviewRow('Licensing', '<span style="color:#fc8181">None selected</span>');
        licHTML += '</div>';

        // Songs
        var songsHTML = '<div class="review-section"><h3>Songs (' + $('#songs-upload .song').length + ')</h3>';
        $('#songs-upload .song').each(function (idx) {
            var $s = $(this);
            var num = idx + 1;
            var title = $s.find('input[name="title' + num + '"]').val() || '(untitled)';
            var aws = $s.find('input.awslink').val();
            var bpm = $s.find('input[name="bpm' + num + '"]').val();

            var statusClass = aws ? 'uploaded' : 'missing';
            var statusText = aws ? 'Uploaded' : 'Missing';
            var meta = bpm ? bpm + ' BPM' : '';

            songsHTML += '<div class="review-song-item">' +
                '<span class="review-song-num">' + num + '</span>' +
                '<span class="review-song-title">' + escapeHtml(title) + '</span>' +
                (meta ? '<span class="review-song-meta">' + meta + '</span>' : '') +
                '<span class="review-song-status ' + statusClass + '">' + statusText + '</span>' +
                '</div>';
        });
        songsHTML += '</div>';

        $r.html(albumHTML + licHTML + songsHTML);
    }

    function reviewRow(label, value) {
        return '<div class="review-row"><span class="review-label">' + label + '</span><span class="review-value">' + value + '</span></div>';
    }

    function truncate(str, len) {
        return str.length > len ? escapeHtml(str.substring(0, len)) + '...' : escapeHtml(str);
    }

    function capitalize(s) {
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* =========================================================================
       Draft Auto-Save (localStorage)
       ========================================================================= */

    function draftStatus(msg, saved) {
        var el = document.getElementById('fml-save-status');
        if (!el) return;
        el.textContent = msg;
        el.classList.toggle('is-saved', !!saved);
    }

    function collectDraft() {
        var data = {};
        $('#songs-upload-form').find('input, select, textarea').each(function () {
            if (!this.name) return;
            if (this.type === 'file' || this.type === 'submit' || this.type === 'button') return;
            // Save awslink hidden inputs (uploaded S3 URLs) but skip other hidden fields
            if (this.type === 'hidden' && !$(this).hasClass('awslink')) return;
            if (this.type === 'checkbox' || this.type === 'radio') {
                if (this.checked) {
                    if (!data[this.name]) data[this.name] = [];
                    data[this.name].push(this.value);
                }
            } else {
                data[this.name] = this.value;
            }
        });
        data._currentStep = currentStep;
        data._songsNumber = $('#songs-number').val();

        // Save uploaded file names so we can show them on restore
        var uploads = {};
        $('#songs-upload .song').each(function (idx) {
            var url = $(this).find('input.awslink').val();
            if (url) {
                // Extract filename from S3 URL
                var name = url.split('/').pop();
                try { name = decodeURIComponent(name); } catch(e) {}
                uploads[idx + 1] = name;
            }
        });
        if (Object.keys(uploads).length) {
            data._uploadedFiles = uploads;
        }

        return data;
    }

    function saveDraft(silent) {
        try {
            var payload = { saved_at: Date.now(), fields: collectDraft() };
            localStorage.setItem(DRAFT_KEY, JSON.stringify(payload));
            if (!silent) draftStatus('Draft saved', true);
        } catch (e) { console.warn('Draft save failed', e); }
    }

    function restoreDraft() {
        try {
            var raw = localStorage.getItem(DRAFT_KEY);
            if (!raw) return;
            var payload = JSON.parse(raw);
            if (!payload || !payload.fields) return;
            var fields = payload.fields;

            // Restore songs first if saved
            if (fields._songsNumber) {
                $('#songs-number').val(fields._songsNumber);
                generateSongBlocks(parseInt(fields._songsNumber, 10));
            }

            // Restore all regular fields
            Object.keys(fields).forEach(function (name) {
                if (name.charAt(0) === '_') return; // skip internal keys
                var value = fields[name];
                var $els = $('#songs-upload-form').find('[name="' + name + '"]');
                if (!$els.length) return;
                $els.each(function () {
                    if (this.type === 'checkbox' || this.type === 'radio') {
                        this.checked = Array.isArray(value) && value.indexOf(this.value) !== -1;
                    } else {
                        this.value = value;
                    }
                });
            });

            // Restore uploaded file UI — show "Uploaded" state for songs with S3 URLs
            var uploadedFiles = fields._uploadedFiles || {};
            $('#songs-upload .song').each(function (idx) {
                var $song = $(this);
                var trackNum = idx + 1;
                var awsUrl = $song.find('input.awslink').val();
                if (awsUrl) {
                    var fileName = uploadedFiles[trackNum] || awsUrl.split('/').pop();
                    try { fileName = decodeURIComponent(fileName); } catch(e) {}
                    // Update UI to show uploaded state
                    $song.find('.filename').html(
                        '<strong>' + escapeHtml(fileName) + '</strong> <span class="upload-status success">Uploaded</span>'
                    );
                    $song.find('.progressBar').addClass('success').css('width', '100%');
                    $song.find('.progress').text('Complete!');
                    // Remove upload controls — file is already on S3
                    $song.find('.uploadbtn').remove();
                    $song.find('input[type=file].file-input').remove();
                    $song.find('.mp3Label').remove();
                }
            });

            // Restore licensing toggle states
            initLicensing();

            // Re-apply checkbox limits (mood/genre may be at max)
            initializeCheckboxLimits();

            // Restore step
            if (fields._currentStep && fields._currentStep > 1) {
                showStep(parseInt(fields._currentStep, 10));
            }

            var when = new Date(payload.saved_at || Date.now());
            draftStatus('Restored draft from ' + when.toLocaleString(), true);
        } catch (e) { console.warn('Draft restore failed', e); }
    }

    function clearDraft() {
        try {
            localStorage.removeItem(DRAFT_KEY);
            draftStatus('Draft cleared', false);
        } catch (e) {}
    }

    /* =========================================================================
       Init
       ========================================================================= */

    function initWizard() {
        var $form = $('#songs-upload-form');
        if (!$form.length) return;

        // Read artist ID from form data attribute
        ARTIST_ID = $form.data('artist-id') || '';
        DRAFT_KEY = 'fml_album_draft_' + ARTIST_ID;
        currentStep = 1;

        // Only bind delegated events once (they survive PJAX swaps)
        if (!initialized) {
            $(document).on('click', '.wizard-btn-next', nextStep);
            $(document).on('click', '.wizard-btn-back', prevStep);
            $(document).on('change', '#albumart', function () { handleAlbumArt(this); });
            $(document).on('change', '#songs-number', function () {
                var count = parseInt($(this).val(), 10);
                if (count > 0) generateSongBlocks(count);
            });
            $(document).on('input change', '#songs-upload-form', function () {
                draftStatus('Unsaved changes...', false);
                updateTagCounters();
                updateNextButtonState();
                clearTimeout(window._fmlDraftTimer);
                window._fmlDraftTimer = setTimeout(function () {
                    saveDraft(true);
                    draftStatus('Draft saved', true);
                }, 800);
            });

            // Jump-to-track links inside inline error blocks
            $(document).on('click', '.error-jump', function (e) {
                e.preventDefault();
                var id = $(this).data('jump');
                var $target = $('#' + id);
                if ($target.length) {
                    $('html, body').animate({ scrollTop: $target.offset().top - 70 }, 300);
                    $target.css('border-color', '#ffd166');
                    setTimeout(function () { $target.css('border-color', ''); }, 2500);
                }
            });
            $(document).on('click', '#fml-save-draft', function (e) {
                e.preventDefault();
                saveDraft(false);
            });
            $(document).on('click', '#fml-clear-draft', function (e) {
                e.preventDefault();
                if (confirm('Clear saved draft? This cannot be undone.')) clearDraft();
            });
            $(document).on('submit', '#songs-upload-form', function () {
                clearDraft();
            });
            initialized = true;
        }

        // Per-page-load init
        initLicensing();
        showStep(1);
        restoreDraft();
    }

    // Init on DOM ready
    $(document).ready(initWizard);

    // Re-init after PJAX navigation
    $(document).on('pjax:complete', initWizard);
    document.addEventListener('pjax:load', initWizard);

})(jQuery);
