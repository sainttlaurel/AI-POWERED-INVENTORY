// Export and Print Functions

function printProducts() {
    // Hide elements that shouldn't be printed
    const elementsToHide = document.querySelectorAll('.btn, .modal, .chatbot-widget, .sidebar, .navbar');
    elementsToHide.forEach(el => el.style.display = 'none');
    
    // Add print title
    const printTitle = document.createElement('h1');
    printTitle.textContent = 'Products Report - ' + new Date().toLocaleDateString();
    printTitle.style.textAlign = 'center';
    printTitle.style.marginBottom = '20px';
    document.body.insertBefore(printTitle, document.body.firstChild);
    
    window.print();
    
    // Restore elements after printing
    setTimeout(() => {
        elementsToHide.forEach(el => el.style.display = '');
        document.body.removeChild(printTitle);
    }, 1000);
}

function exportProductsCSV() {
    const table = document.querySelector('.table');
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
    rows.forEach(row => {
        const cols = row.querySelectorAll('td');
        const data = [
            cols[1].innerText, // Product Name
            cols[2].innerText, // Category
            cols[3].innerText, // Supplier
            cols[4].innerText, // Price
            cols[5].innerText.trim(), // Stock
            cols[6].innerText, // Reorder Level
            cols[7].innerText  // Barcode
        ];
        csv.push(data.map(d => '"' + d.replace(/"/g, '""') + '"').join(','));
    });
    
    // Add summary
    csv.push('');
    csv.push('Total Products: ' + rows.length);
    
    // Download
    downloadCSV(csv.join('\n'), 'products_report_' + new Date().toISOString().split('T')[0] + '.csv');
}

function printInventory() {
    const elementsToHide = document.querySelectorAll('.btn, .modal, .chatbot-widget, .sidebar, .navbar');
    elementsToHide.forEach(el => el.style.display = 'none');
    
    const printTitle = document.createElement('h1');
    printTitle.textContent = 'Inventory Logs Report - ' + new Date().toLocaleDateString();
    printTitle.style.textAlign = 'center';
    printTitle.style.marginBottom = '20px';
    document.body.insertBefore(printTitle, document.body.firstChild);
    
    window.print();
    
    setTimeout(() => {
        elementsToHide.forEach(el => el.style.display = '');
        document.body.removeChild(printTitle);
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
    rows.forEach(row => {
        const cols = row.querySelectorAll('td');
        const data = [
            cols[0].innerText, // Product
            cols[1].innerText.trim(), // Action
            cols[2].innerText, // Quantity
            cols[3].innerText, // User
            cols[4].innerText, // Notes
            cols[5].innerText  // Date
        ];
        csv.push(data.map(d => '"' + d.replace(/"/g, '""') + '"').join(','));
    });
    
    csv.push('');
    csv.push('Total Transactions: ' + rows.length);
    
    downloadCSV(csv.join('\n'), 'inventory_logs_' + new Date().toISOString().split('T')[0] + '.csv');
}

function downloadCSV(content, filename) {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    // Show success message
    showNotification('Export completed successfully!', 'success');
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
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);