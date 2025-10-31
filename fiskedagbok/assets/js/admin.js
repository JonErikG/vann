// Fiskedagbok Admin JavaScript

jQuery(document).ready(function($) {
    
    // Confirm delete actions
    $('.delete-catch').on('click', function(e) {
        if (!confirm('Er du sikker på at du vil slette denne fangsten? Dette kan ikke angres.')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Import form handling
    $('#import-form').on('submit', function(e) {
        var fileInput = $(this).find('input[type="file"]');
        
        if (!fileInput.val()) {
            e.preventDefault();
            alert('Vennligst velg en CSV-fil å importere.');
            return false;
        }
        
        var fileName = fileInput.val();
        var fileExt = fileName.split('.').pop().toLowerCase();
        
        if (fileExt !== 'csv') {
            e.preventDefault();
            alert('Kun CSV-filer er tillatt.');
            return false;
        }
        
        // Show loading state
        $(this).find('input[type="submit"]').val('Importerer...').prop('disabled', true);
        
        // Optional: Show progress indicator
        if ($('#import-progress').length === 0) {
            $(this).after('<div id="import-progress" style="margin-top: 15px;"><p>Importerer data, vennligst vent...</p></div>');
        }
    });
    
    // Statistics and data visualization
    function loadStatistics() {
        if ($('#fiskedagbok-stats').length > 0) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_fiskedagbok_stats',
                    nonce: $('#fiskedagbok-nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        updateStatsDisplay(response.data);
                    }
                }
            });
        }
    }
    
    function updateStatsDisplay(stats) {
        $('#total-catches').text(stats.total_catches || 0);
        $('#total-users').text(stats.total_users || 0);
        $('#most-common-fish').text(stats.most_common_fish || 'N/A');
        $('#average-weight').text(stats.average_weight ? stats.average_weight + ' kg' : 'N/A');
    }
    
    // Table enhancements
    function enhanceDataTable() {
        // Add sorting functionality to table headers
        $('.wp-list-table th').on('click', function() {
            var table = $(this).closest('table');
            var columnIndex = $(this).index();
            var rows = table.find('tbody tr').toArray();
            var isAscending = !$(this).hasClass('sorted-asc');
            
            // Remove existing sort classes
            table.find('th').removeClass('sorted-asc sorted-desc');
            
            // Sort rows
            rows.sort(function(a, b) {
                var aText = $(a).find('td').eq(columnIndex).text().trim();
                var bText = $(b).find('td').eq(columnIndex).text().trim();
                
                // Try to parse as numbers for numeric sorting
                var aNum = parseFloat(aText);
                var bNum = parseFloat(bText);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return isAscending ? aNum - bNum : bNum - aNum;
                }
                
                // Try to parse as dates
                var aDate = Date.parse(aText);
                var bDate = Date.parse(bText);
                
                if (!isNaN(aDate) && !isNaN(bDate)) {
                    return isAscending ? aDate - bDate : bDate - aDate;
                }
                
                // Default string comparison
                return isAscending ? 
                    aText.localeCompare(bText) : 
                    bText.localeCompare(aText);
            });
            
            // Update table
            table.find('tbody').html(rows);
            
            // Add sort class
            $(this).addClass(isAscending ? 'sorted-asc' : 'sorted-desc');
        });
    }
    
    // Export functionality
    function initializeExport() {
        $('#export-csv').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var originalText = button.text();
            
            button.text('Eksporterer...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'export_fiskedagbok_csv',
                    nonce: $('#fiskedagbok-nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        // Create download link
                        var link = document.createElement('a');
                        link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(response.data);
                        link.download = 'fiskedagbok-export-' + new Date().toISOString().split('T')[0] + '.csv';
                        link.click();
                    } else {
                        alert('Feil ved eksport: ' + response.data);
                    }
                },
                error: function() {
                    alert('Det oppstod en feil ved eksport.');
                },
                complete: function() {
                    button.text(originalText).prop('disabled', false);
                }
            });
        });
    }
    
    // Bulk actions
    function initializeBulkActions() {
        $('#doaction, #doaction2').on('click', function(e) {
            var action = $(this).siblings('select').val();
            var checkedItems = $('tbody input[type="checkbox"]:checked');
            
            if (action === 'delete' && checkedItems.length > 0) {
                if (!confirm('Er du sikker på at du vil slette ' + checkedItems.length + ' valgte fangster?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Select all checkbox
        $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
            var isChecked = $(this).prop('checked');
            $('tbody input[type="checkbox"]').prop('checked', isChecked);
        });
    }
    
    // Search and filter functionality
    function initializeFilters() {
        var searchTimeout;
        
        $('#catch-search').on('input', function() {
            clearTimeout(searchTimeout);
            var searchTerm = $(this).val().toLowerCase();
            
            searchTimeout = setTimeout(function() {
                filterTable(searchTerm);
            }, 300);
        });
        
        function filterTable(searchTerm) {
            $('tbody tr').each(function() {
                var row = $(this);
                var text = row.text().toLowerCase();
                
                if (text.indexOf(searchTerm) === -1) {
                    row.hide();
                } else {
                    row.show();
                }
            });
        }
    }
    
    // Initialize all admin features
    function initializeAdmin() {
        loadStatistics();
        enhanceDataTable();
        initializeExport();
        initializeBulkActions();
        initializeFilters();
    }
    
    // Initialize when DOM is ready
    initializeAdmin();
    
    // Refresh stats every 30 seconds
    setInterval(loadStatistics, 30000);
});