<?php
if (!defined('ABSPATH')) {
    exit;
}

// Fallback totals when the template is invoked without controller context.
if (!isset($total_catches)) {
    global $wpdb;
    $catches_table = $wpdb->prefix . 'fiskedagbok_catches';
    $archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
    $catches_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $catches_table");
    $archive_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $archive_table");
    $total_catches = $catches_count + $archive_count;
}
?>
<div class="wrap">
    <h1>🌊 Tidevannsdata fra Kartverket</h1>

    <div class="notice notice-info">
        <p>
            <strong>ℹ️ Kartverket-data:</strong> Systemet henter nå tidevannsserier direkte fra nærmeste permanente Kartverket-stasjon
            (standard: <strong>Trondheim – TRD</strong>). Hvis Kartverket midlertidig ikke leverer data, brukes fortsatt en
            <strong>astrometrisk reservekurve</strong> som fallback.
        </p>
        <p>
            <strong>Observerte målinger:</strong> Markeres som 📊 i grensesnittet<br>
            <strong>Prognoser:</strong> Markeres som 🔮<br>
            <strong>Fallback:</strong> «Orkdal havn (estimert)» når API-et mangler data
        </p>
    </div>

    <?php if (!empty($refresh_message)) : ?>
        <div class="notice notice-<?php echo esc_attr($refresh_message['status']); ?>">
            <p><?php echo esc_html($refresh_message['message']); ?></p>
        </div>
    <?php endif; ?>

    <div style="margin: 20px 0; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=fiskedagbok-tidewater&refresh_tidewater=1'), 'fiskedagbok_tidewater_refresh')); ?>" class="button button-primary">
            ✓ Oppdater siste 7 dager
        </a>
        <button id="batch-import-btn" class="button button-secondary" data-mode="missing">
            📥 Hent data for fangster som mangler
        </button>
        <button id="refresh-all-btn" class="button button-secondary">
            🔄 Oppfrisk ALLE fangster
        </button>
        <label for="batch-limit" style="margin-left: auto; font-weight: 600;">
            Batchstørrelse:
            <select id="batch-limit" style="margin-left: 6px;">
                <option value="50">50 (standard)</option>
                <option value="100">100</option>
                <option value="150">150</option>
                <option value="200">200 (maks)</option>
            </select>
        </label>
        <div id="batch-import-status" style="margin-top: 10px; display: none;"></div>
    </div>

    <div class="notice notice-warning" style="margin-bottom: 20px; padding: 15px; border-left: 5px solid #ff9800;">
        <p style="font-size: 16px; margin: 0;">
            <strong>⚠️ BATCH IMPORT FOR ALLE FANGSTER</strong>
        </p>
        <p style="margin: 10px 0 0 0; font-size: 14px;">
            Denne siden behandler <strong style="color: #ff9800;">ALLE <?php echo number_format_i18n($total_catches); ?> fangstene i databasen</strong> - ikke bare dine egne!
        </p>
        <p style="margin: 10px 0 0 0; font-size: 14px; color: #666;">
            Du har <?php echo number_format_i18n($catches_with_data); ?> fangster med tidevannsdata av totalt <?php echo number_format_i18n($total_catches); ?>
            (<?php echo round(($catches_with_data / max($total_catches, 1)) * 100, 1); ?>%)
        </p>
    </div>

    <div class="postbox" style="margin-top: 20px;">
        <div class="postbox-header">
            <h2 class="hndle">📊 Statistikk</h2>
        </div>
        <div class="inside">
            <table class="widefat" style="margin-bottom: 10px;">
                <tbody>
                    <tr>
                        <td><strong>Totalt antall fangster i systemet:</strong></td>
                        <td style="font-weight: bold; color: #0073aa;"><?php echo number_format_i18n($total_catches); ?></td>
                    </tr>
                    <tr style="background-color: #f5f5f5;">
                        <td><strong>Fangster med tidevannsdata:</strong></td>
                        <td><?php echo number_format_i18n($catches_with_data); ?> (<?php echo round(($catches_with_data / max($total_catches, 1)) * 100, 1); ?>%)</td>
                    </tr>
                    <tr>
                        <td><strong>Totalt antall datapunkter:</strong></td>
                        <td><?php echo number_format_i18n($total_records); ?></td>
                    </tr>
                    <tr style="background-color: #f5f5f5;">
                        <td><strong>Dagens fangster med data:</strong></td>
                        <td><?php echo number_format_i18n($todays_catches); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.postbox {
    padding: 10px;
    border-bottom: 1px solid #ccc;
}
.hndle {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}
.postbox .inside {
    padding: 12px;
}
</style>

<script>
jQuery(function($) {
    var batchCounter = 0;
    var $batchLimitSelect = $('#batch-limit');

    // Persist chosen batch size per admin to avoid repeated selection.
    var storedLimit = parseInt(window.localStorage.getItem('fiskedagbokBatchLimit') || '50', 10);
    if ([50, 100, 150, 200].indexOf(storedLimit) === -1) {
        storedLimit = 50;
    }
    $batchLimitSelect.val(String(storedLimit));

    function getBatchLimit() {
        var parsed = parseInt($batchLimitSelect.val(), 10);
        if (isNaN(parsed) || parsed < 50) {
            parsed = 50;
        }
        return parsed;
    }

    function handleBatchImport(refreshAll) {
        var $status = $('#batch-import-status');
        var $btn = refreshAll ? $('#refresh-all-btn') : $('#batch-import-btn');
        var jobToken = null;
        var hasProgress = false;
        var batchSize = getBatchLimit();

        window.localStorage.setItem('fiskedagbokBatchLimit', String(batchSize));

        batchCounter = 0;
        $status.html('<p style="color: #0073aa;">⏳ Starter batch import (' + batchSize + ' fangster per runde)...</p>').show();
        $btn.prop('disabled', true);
        $('#batch-import-btn, #refresh-all-btn').not($btn).prop('disabled', true);
        $batchLimitSelect.prop('disabled', true);

        function renderStatus(data) {
            var html = '<div style="background: #f5f5f5; padding: 12px; border-left: 4px solid #0073aa; margin-bottom: 10px;">';
            html += '<p style="margin: 0; color: #0073aa;"><strong>Batch #' + batchCounter + ':</strong> ' + data.message + ' (Status: ' + data.status + ')</p>';

            if (refreshAll && typeof data.total === 'number' && typeof data.remaining === 'number') {
                var done = Math.max(0, data.total - data.remaining);
                var percent = data.total > 0 ? Math.round((done / data.total) * 100) : 0;
                var bar = '<div style="margin-top: 8px; background: #e2e8f0; height: 10px; position: relative;">' +
                    '<div style="background: #3b82f6; height: 100%; width: ' + percent + '%;"></div>' +
                    '</div>';
                html += '<p style="margin: 4px 0 0 0; color: #333;">Totalt behandlet: ' + done.toLocaleString() + ' av ' + data.total.toLocaleString() + ' (' + percent + '%)</p>' + bar;
            }

            html += '</div>';
            html += $('#batch-import-status').html();
            $status.html(html);
        }

        function runBatch() {
            batchCounter++;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'batch_import_tidewater',
                    nonce: '<?php echo wp_create_nonce('fiskedagbok_admin_nonce'); ?>',
                    limit: batchSize,
                    refresh_all: refreshAll ? 'true' : 'false',
                    job_token: refreshAll && jobToken ? jobToken : ''
                },
                timeout: 30000,
                success: function(response) {
                    if (response.success) {
                        var data = response.data || {};

                        if (refreshAll && data.job_token) {
                            jobToken = data.job_token;
                        }

                        hasProgress = true;
                        renderStatus(data);

                        if (data.status === 'partial' && data.remaining > 0) {
                            setTimeout(runBatch, 500);
                        } else {
                            var completeHtml = '<div style="background: #d4edda; padding: 12px; border-left: 4px solid #28a745;">';
                            completeHtml += '<p style="margin: 0; color: #155724;"><strong>✓ Ferdig!</strong> Alle fangstene har nå tidevannsdata. (Status: ' + data.status + ', Remaining: ' + data.remaining + ')</p>';

                            if (refreshAll && typeof data.total === 'number' && typeof data.remaining === 'number') {
                                var done = Math.max(0, data.total - data.remaining);
                                completeHtml += '<p style="margin: 4px 0 0 0; color: #155724;">Endelig status: ' + done.toLocaleString() + ' av ' + data.total.toLocaleString() + ' behandlet.</p>';
                                jobToken = null;
                            }

                            completeHtml += '</div>';
                            completeHtml += $('#batch-import-status').html();
                            $status.html(completeHtml);
                            $('#batch-import-btn, #refresh-all-btn').prop('disabled', false);
                            $batchLimitSelect.prop('disabled', false);
                        }
                    } else {
                        var errorMessage = response.data || 'Ukjent feil';
                        $status.prepend('<div style="background: #f8d7da; padding: 12px; border-left: 4px solid #dc3545;">' +
                            '<p style="margin: 0; color: #721c24;"><strong>✗ Feil:</strong> ' + errorMessage + '</p>' +
                        '</div>');
                        $('#batch-import-btn, #refresh-all-btn').prop('disabled', false);
                        $batchLimitSelect.prop('disabled', false);
                        jobToken = null;
                    }
                },
                error: function(jqXHR, textStatus) {
                    if (refreshAll && jqXHR.status === 410) {
                        jobToken = null;
                        hasProgress = false;
                    }

                    var responseSnippet = jqXHR.responseText ? jqXHR.responseText.substring(0, 100) : '';
                    var html = '<div style="background: #f8d7da; padding: 12px; border-left: 4px solid #dc3545;">';
                    html += '<p style="margin: 0; color: #721c24;"><strong>✗ Feil:</strong> Kommunikasjonsfeil (' + textStatus + '). Prøver igjen...</p>';
                    if (responseSnippet) {
                        html += '<p style="margin: 5px 0 0 0; font-size: 11px; color: #666;">Respons: ' + responseSnippet + '</p>';
                    }
                    html += '</div>';
                    html += $('#batch-import-status').html();
                    $status.html(html);

                    if (!hasProgress) {
                        $('#batch-import-btn, #refresh-all-btn').prop('disabled', false);
                        $batchLimitSelect.prop('disabled', false);
                        return;
                    }

                    setTimeout(runBatch, 2000);
                }
            });
        }

        runBatch();
    }

    $('#batch-import-btn').on('click', function() {
        var withData = <?php echo intval($catches_with_data); ?>;
        var total = <?php echo intval($total_catches); ?>;
        var missing = total - withData;

        if (confirm('Dette vil hente tidevannsdata for ' + missing.toLocaleString() + ' fangster (av totalt ' + total.toLocaleString() + ').\n\nEr du sikker?')) {
            handleBatchImport(false);
        }
    });

    $('#refresh-all-btn').on('click', function() {
        var total = <?php echo intval($total_catches); ?>;

        if (confirm('Dette vil oppdatere tidevannsdata for ALLE ' + total.toLocaleString() + ' fangstene i databasen.\n\nDet vil ta en liten stund. Er du sikker?')) {
            handleBatchImport(true);
        }
    });
});
</script>
