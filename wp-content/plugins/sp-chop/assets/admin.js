jQuery(document).ready(function($) {
    // Collapsible sections
    $('.choc-chop-collapsible').on('click', function() {
        $(this).toggleClass('active');
        $(this).next('.choc-chop-collapsible-content').slideToggle(300);
    });

    // Helper functions
    function showMessage(message, type) {
        const messageDiv = $('#choc-chop-message');
        messageDiv.removeClass('success error warning').addClass(type);
        messageDiv.html('<p>' + message + '</p>').fadeIn();

        setTimeout(function() {
            messageDiv.fadeOut();
        }, 5000);
    }

    function setLoading(button, loading) {
        const spinner = button.siblings('.spinner');
        if (loading) {
            button.prop('disabled', true);
            button.data('original-text', button.text());
            button.text('Processing...');
            spinner.addClass('is-active');
        } else {
            button.prop('disabled', false);
            button.text(button.data('original-text'));
            spinner.removeClass('is-active');
        }
    }

    // Settings tab switching
    $('.settings-tab').on('click', function() {
        const tab = $(this).data('tab');

        $('.settings-tab').removeClass('active');
        $(this).addClass('active');

        $('.settings-tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });

    // Test email connection
    $('#test-email-connection').on('click', function() {
        const button = $(this);
        const resultDiv = $('#email-test-result');

        setLoading(button, true);
        resultDiv.html('').hide();

        $.ajax({
            url: chocChopAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'choc_chop_check_emails',
                nonce: chocChopAdmin.nonce,
                test_only: 'true'
            },
            success: function(response) {
                setLoading(button, false);
                if (response.success) {
                    resultDiv.removeClass('error').addClass('success');
                    resultDiv.html('<p style="color: #4caf50; margin-top: 10px;">' + response.data.message + '</p>').fadeIn();
                } else {
                    resultDiv.removeClass('success').addClass('error');
                    resultDiv.html('<p style="color: #f44336; margin-top: 10px;">' + response.data.message + '</p>').fadeIn();
                }
            },
            error: function(xhr) {
                setLoading(button, false);
                resultDiv.removeClass('success').addClass('error');
                var msg = 'An error occurred. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                } else if (xhr.status === 403) {
                    msg = 'Security check failed. Please reload the page and try again.';
                }
                resultDiv.html('<p style="color: #f44336; margin-top: 10px;">' + msg + '</p>').fadeIn();
            }
        });
    });

    // Show OAuth result messages from URL params
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('gmail_auth') === 'success') {
        showMessage('Gmail autorizado correctamente.', 'success');
        // Clean URL
        window.history.replaceState({}, '', window.location.pathname + '?page=choc-chop-settings');
    } else if (urlParams.get('gmail_auth') === 'error') {
        var errorCode = urlParams.get('gmail_error') || 'unknown';
        var errorMessages = {
            'invalid_state': 'Error de seguridad. Intenta de nuevo.',
            'google_denied': 'Permiso denegado en Google.',
            'no_code': 'No se recibió código de autorización.',
            'missing_credentials': 'Client ID o Client Secret no configurados.',
            'token_exchange': 'Error al intercambiar el código por tokens.',
            'no_token': 'No se recibió token de acceso.',
            'unknown': 'Error desconocido en la autorización.'
        };
        showMessage(errorMessages[errorCode] || errorMessages['unknown'], 'error');
        window.history.replaceState({}, '', window.location.pathname + '?page=choc-chop-settings');
    }

    // Disconnect Gmail
    $('#disconnect-gmail').on('click', function() {
        if (!confirm('¿Desconectar Gmail? Se revocarán los tokens de acceso.')) {
            return;
        }

        var button = $(this);
        setLoading(button, true);

        $.ajax({
            url: chocChopAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'choc_chop_disconnect_gmail',
                nonce: chocChopAdmin.nonce
            },
            success: function(response) {
                setLoading(button, false);
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                setLoading(button, false);
                showMessage('Error al desconectar. Intenta de nuevo.', 'error');
            }
        });
    });

    // System Cards — Tarjetas tab interactions
    // Toggle card expand/collapse
    $(document).on('click', '.choc-chop-card-item .card-header', function() {
        var cardItem = $(this).closest('.choc-chop-card-item');
        var cardBody = cardItem.find('.card-body');
        var toggle = $(this).find('.card-toggle');

        cardBody.slideToggle(200);
        toggle.toggleClass('dashicons-arrow-right dashicons-arrow-down');
    });

    // Update card title display when name changes
    $(document).on('input', '.card-name-input', function() {
        var cardItem = $(this).closest('.choc-chop-card-item');
        cardItem.find('.card-title-display').text($(this).val());
    });

    // Delete custom card
    $(document).on('click', '.card-delete-btn', function() {
        if (!confirm('¿Eliminar esta tarjeta?')) {
            return;
        }
        $(this).closest('.choc-chop-card-item').slideUp(200, function() {
            $(this).remove();
            // Re-index remaining cards
            $('#choc-chop-cards-editor .choc-chop-card-item').each(function(i) {
                $(this).attr('data-index', i);
                $(this).find('input, textarea, select').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/choc_chop_cards\[\d+\]/, 'choc_chop_cards[' + i + ']'));
                    }
                });
            });
        });
    });

    // Add new card
    $('#add-system-card').on('click', function() {
        var editor = $('#choc-chop-cards-editor');
        var index = editor.find('.choc-chop-card-item').length;

        var template = '<div class="choc-chop-card-item" data-index="' + index + '">' +
            '<div class="card-header" role="button" tabindex="0">' +
            '<span class="card-toggle dashicons dashicons-arrow-down"></span>' +
            '<strong class="card-title-display">New Card</strong>' +
            '<span class="card-word-range"></span>' +
            '</div>' +
            '<div class="card-body">' +
            '<table class="form-table">' +
            '<tr><th>Slug</th><td><input type="text" name="choc_chop_cards[' + index + '][slug]" value="" class="regular-text" placeholder="mi-tarjeta"></td></tr>' +
            '<tr><th>Name</th><td><input type="text" name="choc_chop_cards[' + index + '][name]" value="" class="regular-text card-name-input" placeholder="Mi Tarjeta"></td></tr>' +
            '<tr><th>System Prompt</th><td><textarea name="choc_chop_cards[' + index + '][system_prompt]" rows="8" class="large-text"></textarea><p class="description">Variables: {site_name}, {voice_context}, {word_min}, {word_max}</p></td></tr>' +
            '<tr><th>Word Range</th><td><input type="number" name="choc_chop_cards[' + index + '][word_min]" value="200" min="0" class="small-text"> — <input type="number" name="choc_chop_cards[' + index + '][word_max]" value="600" min="0" class="small-text"> words</td></tr>' +
            '<tr><th>sp_formato</th><td><input type="text" name="choc_chop_cards[' + index + '][sp_formato]" value="" class="regular-text" placeholder="slug"></td></tr>' +
            '<tr><th>Options</th><td><label><input type="checkbox" name="choc_chop_cards[' + index + '][is_active]" value="1" checked> Active</label> &nbsp;&nbsp;<label><input type="radio" name="choc_chop_default_card" value="' + index + '"> Default card</label> &nbsp;&nbsp;<button type="button" class="button button-link-delete card-delete-btn">Delete Card</button></td></tr>' +
            '</table></div></div>';

        editor.append(template);

        // Scroll to the new card
        $('html, body').animate({
            scrollTop: editor.find('.choc-chop-card-item:last').offset().top - 100
        }, 300);
    });

    // Regenerate voice profile
    $('#regenerate-voice').on('click', function() {
        const button = $(this);
        const profileTextarea = $('#voice-profile-display');

        if (!confirm('Are you sure you want to regenerate the voice profile? This will analyze your recent posts and may take a moment.')) {
            return;
        }

        setLoading(button, true);

        $.ajax({
            url: chocChopAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'choc_chop_regenerate_voice',
                nonce: chocChopAdmin.nonce
            },
            success: function(response) {
                setLoading(button, false);
                if (response.success) {
                    profileTextarea.val(response.data.profile);
                    alert(response.data.message);
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                setLoading(button, false);
                alert('An error occurred. Please try again.');
            }
        });
    });

    // ========== Recetas de sitio ==========

    // Toggle recipe editor row
    $(document).on('click', '.sp-chop-edit-recipe', function() {
        var row = $(this).closest('tr');
        var editorRow = row.next('.sp-chop-recipe-editor');
        editorRow.toggle();
    });

    // Cancel recipe edit
    $(document).on('click', '.sp-chop-cancel-recipe', function() {
        $(this).closest('.sp-chop-recipe-editor').hide();
    });

    // Save recipe
    $(document).on('click', '.sp-chop-save-recipe', function() {
        var editorRow = $(this).closest('.sp-chop-recipe-editor');
        var dataRow = editorRow.prev('tr');
        var domain = dataRow.data('domain');
        var button = $(this);

        button.prop('disabled', true).text('Guardando…');

        $.ajax({
            url: chocChopAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'sp_chop_save_recipe',
                nonce: chocChopAdmin.nonce,
                domain: domain,
                content_selector: editorRow.find('.recipe-content-selector').val(),
                strip_selectors: editorRow.find('.recipe-strip-selectors').val(),
                strip_text: editorRow.find('.recipe-strip-text').val(),
                manual_override: editorRow.find('.recipe-manual-override').is(':checked') ? 1 : 0
            },
            success: function(response) {
                button.prop('disabled', false).text('Guardar');
                if (response.success) {
                    editorRow.hide();
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                button.prop('disabled', false).text('Guardar');
                alert('Error al guardar la receta.');
            }
        });
    });

    // Delete recipe
    $(document).on('click', '.sp-chop-delete-recipe', function() {
        var row = $(this).closest('tr');
        var domain = row.data('domain');

        if (!confirm('¿Eliminar la receta para ' + domain + '?')) return;

        $.ajax({
            url: chocChopAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'sp_chop_delete_recipe',
                nonce: chocChopAdmin.nonce,
                domain: domain
            },
            success: function(response) {
                if (response.success) {
                    row.next('.sp-chop-recipe-editor').remove();
                    row.fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('Error al eliminar la receta.');
            }
        });
    });

    // Add new recipe
    $('#sp-chop-add-recipe').on('click', function() {
        var domain = $('#new-recipe-domain').val().trim();
        var selector = $('#new-recipe-selector').val().trim();

        if (!domain) {
            alert('Ingresa un dominio.');
            return;
        }

        var button = $(this);
        button.prop('disabled', true).text('Guardando…');

        $.ajax({
            url: chocChopAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'sp_chop_save_recipe',
                nonce: chocChopAdmin.nonce,
                domain: domain,
                content_selector: selector,
                strip_selectors: '',
                strip_text: '',
                manual_override: 1
            },
            success: function(response) {
                button.prop('disabled', false).text('Agregar');
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                button.prop('disabled', false).text('Agregar');
                alert('Error al agregar la receta.');
            }
        });
    });
});
