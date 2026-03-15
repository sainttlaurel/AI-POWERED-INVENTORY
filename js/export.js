// Export and Print Functions

function printProducts() {
    try {
        console.log('Print function called');
        
        // Get current page title and filters
        const pageTitle = document.querySelector('h1.h2');
        const activeFilters = document.querySelector('.mt-2 small');
        
        if (!pageTitle) {
            console.error('Page title not found');
            alert('Error: Could not find page title for printing');
            return;
        }
        
        console.log('Page title found:', pageTitle.textContent);
        
        // Hide elements that shouldn't be printed
        const elementsToHide = document.querySelectorAll('.btn, .modal, .chatbot-widget, .sidebar, .navbar, .alert, .d-flex.gap-1, .mt-2');
        console.log('Elements to hide:', elementsToHide.length);
        
        elementsToHide.forEach(el => el.style.display = 'none');
        
        // Create print header
        const printHeader = document.createElement('div');
        printHeader.innerHTML = `
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px;">
                <h1 style="margin: 0; color: #333;">${pageTitle.textContent} Report</h1>
                <p style="margin: 5px 0; color: #666;">Generated on: ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
                ${activeFilters ? '<p style="margin: 5px 0; color: #666; font-size: 14px;">' + activeFilters.textContent + '</p>' : ''}
            </div>
        `;
        
        // Insert header at the beginning of main content
        const main = document.querySelector('main');
        if (!main) {
            console.error('Main element not found');
            alert('Error: Could not find main content area');
            return;
        }
        
        main.insertBefore(printHeader, main.firstChild);
        
        // Add print styles
        const printStyles = document.createElement('style');
        printStyles.textContent = `
            @media print {
                body { font-size: 12px; }
                .table { font-size: 11px; }
                .table th, .table td { padding: 4px 6px; }
                .badge { background-color: #f8f9fa !important; color: #000 !important; border: 1px solid #ccc; }
                .btn { display: none !important; }
                .form-control, .form-select { display: none !important; }
            }
        `;
        document.head.appendChild(printStyles);
        
        console.log('About to print...');
        window.print();
        
        // Restore elements after printing
        setTimeout(() => {
            console.log('Restoring elements...');
            elementsToHide.forEach(el => el.style.display = '');
            if (main.contains(printHeader)) {
                main.removeChild(printHeader);
            }
            if (document.head.contains(printStyles)) {
                document.head.removeChild(printStyles);
            }
            console.log('Print cleanup completed');
        }, 1000);
        
    } catch (error) {
        console.error('Print error:', error);
        alert('Print error: ' + error.message);
    }
}

function exportProductsCSV() {
    try {
        console.log('CSV export function called');
        
        const table = document.querySelector('.table');
        if (!table) {
            console.error('Table not found');
            alert('Error: Could not find products table');
            return;
        }
        
        console.log('Table found');
        
        let csv = [];
        
        // Add title and date
        csv.push('Products Report');
        csv.push('Generated on: ' + new Date().toLocaleDateString());
        csv.push('');
        
        // Headers
        const headers = ['Product Name', 'Category', 'Supplier', 'Price', 'Stock', 'Reorder Level', 'Barcode'];
        csv.push(headers.join(','));
        
        // Data rows
        const rows = table.querySelectorAll('tbody tr');
        console.log('Found rows:', rows.length);
        
        let dataRowCount = 0;
        
        rows.forEach((row, index) => {
            const cols = row.querySelectorAll('td');
            console.log(`Row ${index}: ${cols.length} columns`);
            
            // Skip empty state row (has colspan="10")
            if (cols.length >= 9 && !cols[0].hasAttribute('colspan')) {
                const data = [
                    cols[2].innerText.trim(), // Product Name (skip checkbox and image)
                    cols[3].innerText.trim(), // Category
                    cols[4].innerText.trim(), // Supplier
                    cols[5].innerText.trim().replace('₱', ''), // Price (remove currency symbol)
                    cols[6].innerText.trim(), // Stock (badge text)
                    cols[7].innerText.trim(), // Reorder Level
                    cols[8].innerText.trim()  // Barcode
                ];
                
                console.log(`Adding row data:`, data);
                csv.push(data.map(d => '"' + d.replace(/"/g, '""') + '"').join(','));
                dataRowCount++;
            } else {
                console.log(`Skipping row ${index} - not enough columns or has colspan`);
            }
        });
        
        // Add summary
        csv.push('');
        csv.push('Total Products: ' + dataRowCount);
        
        if (dataRowCount === 0) {
            csv.push('No products found with current filters');
        }
        
        console.log('CSV content prepared, rows:', dataRowCount);
        
        // Download
        downloadCSV(csv.join('\n'), 'products_report_' + new Date().toISOString().split('T')[0] + '.csv');
        
    } catch (error) {
        console.error('CSV export error:', error);
        alert('CSV export error: ' + error.message);
    }
}

function printInventory() {
    // Get current page title
    const pageTitle = document.querySelector('h1.h2').textContent;
    
    // Hide elements that shouldn't be printed
    const elementsToHide = document.querySelectorAll('.btn, .modal, .chatbot-widget, .sidebar, .navbar, .alert');
    elementsToHide.forEach(el => el.style.display = 'none');
    
    // Create print header
    const printHeader = document.createElement('div');
    printHeader.innerHTML = `
        <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px;">
            <h1 style="margin: 0; color: #333;">${pageTitle} Report</h1>
            <p style="margin: 5px 0; color: #666;">Generated on: ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
            <p style="margin: 5px 0; color: #666; font-size: 14px;">Last 50 inventory transactions</p>
        </div>
    `;
    
    // Insert header at the beginning of main content
    const main = document.querySelector('main');
    main.insertBefore(printHeader, main.firstChild);
    
    // Add print styles
    const printStyles = document.createElement('style');
    printStyles.textContent = `
        @media print {
            body { font-size: 12px; }
            .table { font-size: 11px; }
            .table th, .table td { padding: 4px 6px; }
            .badge { background-color: #f8f9fa !important; color: #000 !important; border: 1px solid #ccc; }
            .btn { display: none !important; }
            .card-header { background-color: #f8f9fa !important; color: #000 !important; }
        }
    `;
    document.head.appendChild(printStyles);
    
    window.print();
    
    // Restore elements after printing
    setTimeout(() => {
        elementsToHide.forEach(el => el.style.display = '');
        main.removeChild(printHeader);
        document.head.removeChild(printStyles);
    }, 1000);
}

function exportInventoryCSV() {
    const table = document.querySelector('.table');
    let csv = [];
    
    csv.push('Inventory Logs Report');
    csv.push('Generated on: ' + new Date().toLocaleDateString());
    csv.push('');
    
    const headers = ['Product', 'Action', 'Quantity', 'User', 'Notes', 'Date'];
    csv.push(headers.join(','));
    
    const rows = table.querySelectorAll('tbody tr');
    let dataRowCount = 0;
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td');
        if (cols.length >= 6) {
            const data = [
                cols[0].innerText.trim(), // Product
                cols[1].innerText.trim().replace(/\s+/g, ' '), // Action (clean up badge text)
                cols[2].innerText.trim(), // Quantity
                cols[3].innerText.trim(), // User
                cols[4].innerText.trim(), // Notes
                cols[5].innerText.trim()  // Date
            ];
            csv.push(data.map(d => '"' + d.replace(/"/g, '""') + '"').join(','));
            dataRowCount++;
        }
    });
    
    csv.push('');
    csv.push('Total Transactions: ' + dataRowCount);
    
    if (dataRowCount === 0) {
        csv.push('No inventory transactions found');
    }
    
    downloadCSV(csv.join('\n'), 'inventory_logs_' + new Date().toISOString().split('T')[0] + '.csv');
}

function downloadCSV(content, filename) {
    try {
        console.log('Starting CSV download:', filename);
        console.log('Content length:', content.length);
        
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.style.display = 'none';
        document.body.appendChild(a);
        
        console.log('Triggering download...');
        a.click();
        
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        
        console.log('Download completed successfully');
        
        // Show success message
        showNotification('Export completed successfully!', 'success');
        
    } catch (error) {
        console.error('Download error:', error);
        alert('Download error: ' + error.message);
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification-toast`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Add CSS for animations
const animationStyles = document.createElement('style');
animationStyles.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(animationStyles);

// Log that the script has loaded
console.log('Export.js loaded successfully at', new Date().toLocaleTimeString());
console.log('Available functions: printProducts, exportProductsCSV, printInventory, exportInventoryCSV');