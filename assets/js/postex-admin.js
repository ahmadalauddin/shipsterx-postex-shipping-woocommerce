/**
 * PostEx Admin Scripts
 */

// Manual sync functionality
function postexManualSync() {
    const button = document.getElementById('manual_sync');
    const originalText = button.innerHTML;
    
    button.innerHTML = '⏳ Syncing...';
    button.disabled = true;
    
    fetch(postex_ajax.ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'postex_manual_sync',
            nonce: postex_ajax.manual_sync_nonce
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Sync failed: ' + (data.data || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Sync failed: Network error');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

// Validation function for download selection
function postexValidateSelection() {
    const selected = document.querySelectorAll('.tracking-checkbox:checked').length;
    if (selected === 0) {
        alert('Please select at least one order to download PDFs.');
        return false;
    }

    if (selected > 10) {
        alert('You can download a maximum of 10 PDFs at once. Please select fewer orders.');
        return false;
    }

    return confirm(`Download PDFs for ${selected} selected order(s)?`);
}

// Bulk actions for airway bills
document.addEventListener('DOMContentLoaded', function() {
    // Select all functionality
    const selectAll = document.getElementById('cb-select-all');
    const checkboxes = document.querySelectorAll('.tracking-checkbox');
    const selectedCount = document.getElementById('selected-count');
    
    function updateSelectedCount() {
        const selected = document.querySelectorAll('.tracking-checkbox:checked').length;
        if (selectedCount) {
            selectedCount.textContent = selected > 0 ? `(${selected} selected)` : '';
        }
    }
    
    if (selectAll && checkboxes.length > 0) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    // Individual checkbox change
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    // Initial count
    updateSelectedCount();
    
    // Download selected functionality
    const downloadButton = document.getElementById('download-selected');
    if (downloadButton) {
        downloadButton.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.tracking-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one order to download.');
                return false;
            }
        });
    }
    
    // City filter functionality
    const filterSubmit = document.getElementById('filter-submit');
    if (filterSubmit) {
        filterSubmit.addEventListener('click', function() {
            const status = document.getElementById('filter-status').value;
            const url = new URL(window.location);
            url.searchParams.set('filter', status);
            url.searchParams.delete('paged');
            window.location = url;
        });
    }
});