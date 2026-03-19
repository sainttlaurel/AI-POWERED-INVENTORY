// Products page JavaScript functions

function filterProducts() {
    const searchInput = document.getElementById('search');
    const categoryFilter = document.getElementById('category_filter');
    const stockFilter = document.getElementById('stock_filter');
    
    if (!searchInput || !categoryFilter || !stockFilter) {
        console.error('Filter elements not found');
        return;
    }
    
    const searchTerm = searchInput.value.toLowerCase();
    const selectedCategory = categoryFilter.value;
    const selectedStock = stockFilter.value;
    
    const rows = document.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length < 3) return; // Skip empty state rows
        
        const productName = cells[2].textContent.toLowerCase(); // Product name column
        const category = cells[3].textContent; // Category column
        const stockBadge = cells[9].querySelector('.badge'); // Stock column (corrected index)
        const stockValue = stockBadge ? parseInt(stockBadge.textContent) : 0;
        
        let showRow = true;
        
        // Search filter
        if (searchTerm && !productName.includes(searchTerm)) {
            showRow = false;
        }
        
        // Category filter
        if (selectedCategory && selectedCategory !== '' && category !== selectedCategory) {
            showRow = false;
        }
        
        // Stock filter
        if (selectedStock) {
            switch (selectedStock) {
                case 'good':
                    if (stockValue <= 10) showRow = false;
                    break;
                case 'low':
                    if (stockValue > 10 || stockValue <= 0) showRow = false;
                    break;
                case 'out':
                    if (stockValue > 0) showRow = false;
                    break;
            }
        }
        
        row.style.display = showRow ? '' : 'none';
        if (showRow) visibleCount++;
    });
    
    // Update results count
    const resultsCount = document.getElementById('resultsCount');
    if (resultsCount) {
        resultsCount.textContent = `Showing ${visibleCount} products`;
    }
}

function bulkUpdateStock() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]:checked');
    
    if (checkboxes.length === 0) {
        alert('Please select products to update');
        return;
    }
    
    const newStock = prompt('Enter new stock quantity:');
    if (newStock === null || newStock === '') return;
    
    const stockValue = parseInt(newStock);
    if (isNaN(stockValue) || stockValue < 0) {
        alert('Please enter a valid stock quantity');
        return;
    }
    
    if (!confirm(`Update stock to ${stockValue} for ${checkboxes.length} selected products?`)) {
        return;
    }
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'bulk_update_stock');
    formData.append('new_stock', stockValue);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    checkboxes.forEach(checkbox => {
        formData.append('selected_products[]', checkbox.value);
    });
    
    // Submit form
    fetch('products.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('success') || data.includes('updated')) {
            location.reload();
        } else {
            alert('Error updating stock. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating stock. Please try again.');
    });
}

function bulkDelete() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]:checked');
    
    if (checkboxes.length === 0) {
        alert('Please select products to delete');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete ${checkboxes.length} selected products? This action cannot be undone.`)) {
        return;
    }
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'bulk_delete');
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    checkboxes.forEach(checkbox => {
        formData.append('selected_products[]', checkbox.value);
    });
    
    // Submit form
    fetch('products.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('success') || data.includes('deleted')) {
            location.reload();
        } else {
            alert('Error deleting products. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting products. Please try again.');
    });
}

function selectAllProducts() {
    const selectAllCheckbox = document.getElementById('select-all');
    const productCheckboxes = document.querySelectorAll('input[name="selected_products[]"]');
    
    productCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]:checked');
    const bulkActions = document.getElementById('bulk-actions');
    
    if (bulkActions) {
        bulkActions.style.display = checkboxes.length > 0 ? 'inline-block' : 'none';
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for filters
    const searchInput = document.getElementById('search');
    const categoryFilter = document.getElementById('category_filter');
    const stockFilter = document.getElementById('stock_filter');
    
    if (searchInput) {
        searchInput.addEventListener('input', filterProducts);
    }
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterProducts);
    }
    
    if (stockFilter) {
        stockFilter.addEventListener('change', filterProducts);
    }
    
    // Add event listeners for checkboxes
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', selectAllProducts);
    }
    
    const productCheckboxes = document.querySelectorAll('input[name="selected_products[]"]');
    productCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });
    
    console.log('Products.js loaded successfully');
});