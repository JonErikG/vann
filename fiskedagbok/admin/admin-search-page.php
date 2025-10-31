<?php
// Sikkerhet: Hindre direkte tilgang
if (!defined('ABSPATH')) {
    exit;
}

// Hent alle brukere for dropdown
$users = get_users(array(
    'orderby' => 'display_name',
    'order' => 'ASC'
));
?>

<div class="wrap">
    <h1>Søk og Klaim Fangster</h1>
    
    <div class="card">
        <h2>Søk i CSV Arkiv</h2>
        <p>Søk etter fangster i arkivet og tildel dem til riktige brukere.</p>
        
        <div class="search-controls">
            <div class="search-input-group">
                <label for="admin-search-name">Søk på fisher_name:</label>
                <input type="text" id="admin-search-name" placeholder="Skriv navn..." class="regular-text">
                <button type="button" id="admin-search-btn" class="button">Søk</button>
            </div>
            
            <div id="search-stats" class="search-stats" style="display: none;">
                <p id="search-count"></p>
            </div>
        </div>
        
        <div id="admin-search-results" class="admin-search-results" style="display: none;">
            <div class="results-header">
                <h3>Søkeresultater</h3>
                <div class="bulk-actions">
                    <select id="bulk-user-select">
                        <option value="">Velg bruker for bulk-tildeling</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="bulk-claim-btn" class="button">Tildel valgte</button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped" id="search-results-table">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="select-all-catches"></th>
                        <th>Dato</th>
                        <th>Fisher Name</th>
                        <th>Fisketype</th>
                        <th>Vekt</th>
                        <th>Lengde</th>
                        <th>Elv</th>
                        <th>Status</th>
                        <th>Handlinger</th>
                    </tr>
                </thead>
                <tbody id="search-results-body">
                    <!-- Søkeresultater vil bli lagt til her -->
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card">
        <h2>Statistikk</h2>
        <div id="admin-stats">
            <?php
            global $wpdb;
            $archive_table = $wpdb->prefix . 'fiskedagbok_csv_archive';
            
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $archive_table");
            $claimed = $wpdb->get_var("SELECT COUNT(*) FROM $archive_table WHERE claimed = 1");
            $unique_fishers = $wpdb->get_var("SELECT COUNT(DISTINCT fisher_name) FROM $archive_table");
            ?>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <strong><?php echo $total; ?></strong>
                    <span>Totalt i arkiv</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo $claimed; ?></strong>
                    <span>Clamet</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo $total - $claimed; ?></strong>
                    <span>Tilgjengelig</span>
                </div>
                <div class="stat-item">
                    <strong><?php echo $unique_fishers; ?></strong>
                    <span>Unike fiskere</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h2>Vanlige fiskernavn</h2>
        <?php
        $common_names = $wpdb->get_results(
            "SELECT fisher_name, COUNT(*) as count, 
                    SUM(CASE WHEN claimed = 1 THEN 1 ELSE 0 END) as claimed_count
             FROM $archive_table 
             GROUP BY fisher_name 
             HAVING count > 1
             ORDER BY count DESC 
             LIMIT 20"
        );
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Fisher Name</th>
                    <th>Totale fangster</th>
                    <th>Clamet</th>
                    <th>Tilgjengelig</th>
                    <th>Handling</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($common_names as $name_stat): ?>
                <tr>
                    <td><strong><?php echo esc_html($name_stat->fisher_name); ?></strong></td>
                    <td><?php echo $name_stat->count; ?></td>
                    <td><?php echo $name_stat->claimed_count; ?></td>
                    <td><?php echo $name_stat->count - $name_stat->claimed_count; ?></td>
                    <td>
                        <button class="button quick-search-btn" data-name="<?php echo esc_attr($name_stat->fisher_name); ?>">
                            Søk
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Claim Modal -->
<div id="claim-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Tildel fangst til bruker</h3>
        <div id="claim-catch-details"></div>
        
        <form id="claim-form">
            <input type="hidden" id="claim-catch-id">
            <table class="form-table">
                <tr>
                    <th>Velg bruker:</th>
                    <td>
                        <select id="claim-user-select" required>
                            <option value="">Velg bruker</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button-primary" value="Tildel fangst">
                <button type="button" class="button cancel-claim">Avbryt</button>
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var searchTimeout;
    var originalBulkClaimText = $('#bulk-claim-btn').text();
    
    // Søk når bruker skriver
    $('#admin-search-name').on('input', function() {
        clearTimeout(searchTimeout);
        var searchTerm = $(this).val().trim();
        
        if (searchTerm.length >= 2) {
            searchTimeout = setTimeout(function() {
                searchArchive(searchTerm);
            }, 500);
        } else {
            $('#admin-search-results').hide();
        }
    });
    
    // Søk knapp
    $('#admin-search-btn').click(function() {
        var searchTerm = $('#admin-search-name').val().trim();
        if (searchTerm.length >= 2) {
            searchArchive(searchTerm);
        }
    });
    
    // Quick search buttons
    $('.quick-search-btn').click(function() {
        var name = $(this).data('name');
        $('#admin-search-name').val(name);
        searchArchive(name);
    });
    
    function searchArchive(searchName) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'admin_search_archive',
                search_name: searchName,
                nonce: '<?php echo wp_create_nonce('fiskedagbok_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displaySearchResults(response.data);
                    $('#search-count').text('Fant ' + response.data.length + ' fangster');
                    $('#search-stats').show();
                    $('#admin-search-results').show();
                } else {
                    alert('Feil: ' + response.data);
                    $('#admin-search-results').hide();
                }
            },
            error: function() {
                alert('Søkefeil');
                $('#admin-search-results').hide();
            }
        });
    }
    
    function displaySearchResults(catches) {
        var tbody = $('#search-results-body');
        tbody.empty();
        
        catches.forEach(function(catch_data) {
            var status = catch_data.claimed == 1 ? 
                '<span class="claimed">Clamet av ' + (catch_data.claimed_by_name || 'ukjent') + '</span>' : 
                '<span class="available">Tilgjengelig</span>';
            
            var actions = catch_data.claimed == 1 ? 
                '<em>Allerede clamet</em>' : 
                '<button class="button-primary claim-btn" data-catch-id="' + catch_data.id + '">Tildel</button>';
            
            var checkbox = catch_data.claimed == 1 ? '' : 
                '<input type="checkbox" class="catch-checkbox" value="' + catch_data.id + '">';
            
            var row = '<tr>' +
                '<td>' + checkbox + '</td>' +
                '<td>' + catch_data.date + '</td>' +
                '<td><strong>' + catch_data.fisher_name + '</strong></td>' +
                '<td>' + catch_data.fish_type + '</td>' +
                '<td>' + (catch_data.weight_kg || '-') + '</td>' +
                '<td>' + (catch_data.length_cm || '-') + '</td>' +
                '<td>' + (catch_data.river_name || '-') + '</td>' +
                '<td>' + status + '</td>' +
                '<td>' + actions + '</td>' +
            '</tr>';
            
            tbody.append(row);
        });
        
        bindResultEvents();
    }

    function refreshCurrentSearch() {
        var currentSearch = $.trim($('#admin-search-name').val());
        if (currentSearch && currentSearch.length >= 2) {
            searchArchive(currentSearch);
        }
    }
    
    function bindResultEvents() {
        // Claim buttons
        $('.claim-btn').off('click').on('click', function() {
            var catchId = $(this).data('catch-id');
            showClaimModal(catchId);
        });
        
        // Select all checkbox
        $('#select-all-catches').off('change').on('change', function() {
            $('.catch-checkbox').prop('checked', $(this).prop('checked'));
        });
    }
    
    function showClaimModal(catchId) {
        $('#claim-catch-id').val(catchId);
        $('#claim-modal').show();
    }
    
    // Modal events
    $('.close, .cancel-claim').click(function() {
        $('#claim-modal').hide();
    });
    
    // Claim form
    $('#claim-form').submit(function(e) {
        e.preventDefault();
        
        var catchId = $('#claim-catch-id').val();
        var userId = $('#claim-user-select').val();
        
        if (!userId) {
            alert('Velg en bruker');
            return;
        }
        
        claimCatchToUser(catchId, userId);
    });
    
    // Bulk claim
    $('#bulk-claim-btn').click(function() {
        var selectedCatches = $('.catch-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        var userId = $('#bulk-user-select').val();
        
        if (selectedCatches.length === 0) {
            alert('Velg fangster å tildele');
            return;
        }
        
        if (!userId) {
            alert('Velg bruker for bulk-tildeling');
            return;
        }
        
        if (confirm('Tildele ' + selectedCatches.length + ' fangster til valgte bruker?')) {
            bulkClaimCatches(selectedCatches, userId);
        }
    });
    
    function claimCatchToUser(catchId, userId, options) {
        options = options || {};

        var request = $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'admin_claim_to_user',
                catch_id: catchId,
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('fiskedagbok_admin_nonce'); ?>'
            }
        });

        if (!options.silent) {
            request.done(function(response) {
                if (response && response.success) {
                    $('#claim-modal').hide();
                    alert('Fangst tildelt: ' + response.data);
                    refreshCurrentSearch();
                } else if (response) {
                    alert('Feil: ' + response.data);
                }
            }).fail(function() {
                alert('Tildelingsfeil');
            });
        }

        return request;
    }
    
    function setBulkClaimUiState(isProcessing) {
        var disabled = !!isProcessing;
        $('#bulk-claim-btn').prop('disabled', disabled);
        $('#bulk-user-select').prop('disabled', disabled);

        if (!disabled) {
            $('#bulk-claim-btn').text(originalBulkClaimText);
        }
    }

    function updateBulkClaimProgress(current, total) {
        $('#bulk-claim-btn').text('Tildeler ' + current + '/' + total + ' ...');
    }

    function bulkClaimCatches(catchIds, userId) {
        var queue = catchIds.slice();
        var total = queue.length;
        var successCount = 0;
        var failures = [];
        var processed = 0;

        setBulkClaimUiState(true);
        updateBulkClaimProgress(0, total);

        function finalizeBulkClaim() {
            setBulkClaimUiState(false);

            var message = 'Tildeling fullført: ' + successCount + ' av ' + total + ' fangster.';
            if (failures.length) {
                var failureDetails = failures.map(function(item) {
                    return '#' + item.id + ' (' + item.message + ')';
                }).join(', ');
                message += '\nFeil på ' + failures.length + ': ' + failureDetails;
            }

            alert(message);
            refreshCurrentSearch();
        }

        function processNext() {
            if (!queue.length) {
                finalizeBulkClaim();
                return;
            }

            var catchId = queue.shift();
            updateBulkClaimProgress(processed + 1, total);

            claimCatchToUser(catchId, userId, { silent: true })
                .done(function(response) {
                    if (response && response.success) {
                        successCount++;
                    } else if (response) {
                        failures.push({ id: catchId, message: response.data || 'Ukjent feil' });
                    }
                })
                .fail(function(jqXHR, textStatus) {
                    failures.push({ id: catchId, message: textStatus || 'Tildelingsfeil' });
                })
                .always(function() {
                    processed++;
                    // Gi serveren en pust i bakken mellom kall for å unngå lasttopper
                    setTimeout(processNext, 150);
                });
        }

        processNext();
    }
});
</script>

<style>
.search-controls {
    margin-bottom: 20px;
}

.search-input-group {
    display: flex;
    gap: 10px;
    align-items: end;
    margin-bottom: 10px;
}

.search-input-group label {
    margin-bottom: 5px;
    font-weight: bold;
}

.search-stats {
    background: #f0f6fc;
    padding: 10px;
    border-radius: 4px;
    border-left: 4px solid #0073aa;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.bulk-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}

.stat-item strong {
    display: block;
    font-size: 24px;
    color: #0073aa;
}

.claimed {
    color: #46b450;
    font-weight: bold;
}

.available {
    color: #0073aa;
    font-weight: bold;
}

.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 20px;
    border-radius: 4px;
    width: 80%;
    max-width: 500px;
    position: relative;
}

.close {
    position: absolute;
    right: 15px;
    top: 15px;
    font-size: 24px;
    cursor: pointer;
}
</style>