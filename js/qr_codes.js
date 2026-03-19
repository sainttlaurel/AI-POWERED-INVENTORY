// QR Code stuff - just basic functionality
class QRCodeManager {
    constructor() {
        this.video = null;
        this.canvas = null;
        this.context = null;
        this.scanning = false;
        this.stream = null;
        
        this.init();
    }
    
    init() {
        // make QR codes for products
        this.generateProductQRCodes();
        
        // setup camera
        this.initCameraScanner();
        
        // setup file upload
        this.initFileScanner();
        
        // setup search
        this.initProductSearch();
    }
    
    // Generate QR codes for products
    generateProductQRCodes() {
        document.querySelectorAll('.qr-code-container').forEach(container => {
            const productId = container.dataset.productId;
            const canvas = container.querySelector('canvas');
            
            if (canvas && productId) {
                const qrData = {
                    type: 'product',
                    id: productId,
                    url: `${window.location.origin}/INVENTORY/product_detail.php?id=${productId}`,
                    timestamp: Date.now()
                };
                
                QRCode.toCanvas(canvas, JSON.stringify(qrData), {
                    width: 80,
                    height: 80,
                    margin: 1,
                    color: {
                        dark: '#1c1c1e',
                        light: '#ffffff'
                    }
                }, (error) => {
                    if (error) {
                        console.error('QR error:', error);
                        canvas.style.display = 'none';
                    }
                });
            }
        });
    }
    
    // Setup camera scanner
    initCameraScanner() {
        this.video = document.getElementById('qrVideo');
        this.canvas = document.getElementById('qrCanvas');
        
        if (this.canvas) {
            this.context = this.canvas.getContext('2d');
        }
        
        const startBtn = document.getElementById('startScan');
        const stopBtn = document.getElementById('stopScan');
        
        if (startBtn) {
            startBtn.addEventListener('click', () => this.startCamera());
        }
        
        if (stopBtn) {
            stopBtn.addEventListener('click', () => this.stopCamera());
        }
    }
    
    // Start camera
    async startCamera() {
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });
            
            this.video.srcObject = this.stream;
            this.video.play();
            
            document.getElementById('startScan').style.display = 'none';
            document.getElementById('stopScan').style.display = 'inline-block';
            
            this.scanning = true;
            this.scanQRCode();
            
        } catch (error) {
            console.error('Camera error:', error);
            this.showAlert('Camera not available. Use file upload instead.', 'warning');
        }
    }
    
    // Stop camera scanning
    stopCamera() {
        this.scanning = false;
        
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
        }
        
        if (this.video) {
            this.video.srcObject = null;
        }
        
        document.getElementById('startScan').style.display = 'inline-block';
        document.getElementById('stopScan').style.display = 'none';
    }
    
    // Scan QR code from video stream
    scanQRCode() {
        if (!this.scanning || !this.video || !this.canvas || !this.context) {
            return;
        }
        
        if (this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
            this.canvas.width = this.video.videoWidth;
            this.canvas.height = this.video.videoHeight;
            
            this.context.drawImage(this.video, 0, 0, this.canvas.width, this.canvas.height);
            
            const imageData = this.context.getImageData(0, 0, this.canvas.width, this.canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);
            
            if (code) {
                this.handleQRCodeDetected(code.data);
                this.stopCamera();
                return;
            }
        }
        
        // Continue scanning
        requestAnimationFrame(() => this.scanQRCode());
    }
    
    // Initialize file upload QR scanner
    initFileScanner() {
        const fileInput = document.getElementById('qrFileInput');
        
        if (fileInput) {
            fileInput.addEventListener('change', (event) => {
                const file = event.target.files[0];
                if (file) {
                    this.scanQRFromFile(file);
                }
            });
        }
    }
    
    // Scan QR code from uploaded file
    scanQRFromFile(file) {
        const reader = new FileReader();
        
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                
                canvas.width = img.width;
                canvas.height = img.height;
                context.drawImage(img, 0, 0);
                
                const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height);
                
                if (code) {
                    this.handleQRCodeDetected(code.data);
                } else {
                    this.showAlert('No QR code found in the uploaded image.', 'warning');
                }
            };
            img.src = e.target.result;
        };
        
        reader.readAsDataURL(file);
    }
    
    // Handle detected QR code
    handleQRCodeDetected(qrData) {
        try {
            // Try to parse as JSON first
            let data;
            try {
                data = JSON.parse(qrData);
            } catch {
                // If not JSON, treat as plain text
                data = { type: 'text', content: qrData };
            }
            
            const resultDiv = document.getElementById('qrScanResult');
            const contentDiv = document.getElementById('qrResultContent');
            
            if (data.type === 'product' && data.id) {
                // Product QR code
                contentDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-8">
                            <h6>Product Found</h6>
                            <p><strong>Product ID:</strong> ${data.id}</p>
                            ${data.name ? `<p><strong>Name:</strong> ${data.name}</p>` : ''}
                            ${data.barcode ? `<p><strong>Barcode:</strong> ${data.barcode}</p>` : ''}
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary btn-sm" onclick="viewProduct(${data.id})">
                                <i class="bi bi-eye"></i> View Product
                            </button>
                            <button class="btn btn-success btn-sm mt-1" onclick="quickStockUpdate(${data.id})">
                                <i class="bi bi-plus-minus"></i> Update Stock
                            </button>
                        </div>
                    </div>
                `;
                
                // Also update the main scan result area
                this.updateScanResult(data);
                
            } else {
                // Generic QR code
                contentDiv.innerHTML = `
                    <h6>QR Code Content</h6>
                    <p><code>${qrData}</code></p>
                    ${data.url ? `<a href="${data.url}" target="_blank" class="btn btn-primary btn-sm">Open Link</a>` : ''}
                `;
            }
            
            resultDiv.style.display = 'block';
            
        } catch (error) {
            console.error('QR code processing error:', error);
            this.showAlert('Error processing QR code data.', 'danger');
        }
    }
    
    // Update main scan result area
    updateScanResult(data) {
        const scanResult = document.getElementById('scanResult');
        const scanResultContent = document.getElementById('scanResultContent');
        
        if (data.type === 'product' && data.id) {
            // Fetch product details
            fetch(`api/get_product.php?id=${data.id}`)
                .then(response => response.json())
                .then(product => {
                    if (product.success) {
                        const p = product.data;
                        scanResultContent.innerHTML = `
                            <div class="row">
                                <div class="col-md-3">
                                    ${p.image ? `<img src="uploads/${p.image}" class="img-fluid rounded" alt="${p.product_name}">` : '<div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 100px;"><i class="bi bi-image text-muted"></i></div>'}
                                </div>
                                <div class="col-md-6">
                                    <h5>${p.product_name}</h5>
                                    <p class="mb-1"><strong>Stock:</strong> <span class="badge ${p.stock_quantity <= p.reorder_level ? 'bg-warning' : 'bg-success'}">${p.stock_quantity} units</span></p>
                                    <p class="mb-1"><strong>Price:</strong> ₱${parseFloat(p.price).toLocaleString()}</p>
                                    ${p.barcode ? `<p class="mb-1"><strong>Barcode:</strong> ${p.barcode}</p>` : ''}
                                    <p class="mb-0"><strong>Category:</strong> ${p.category_name || 'Uncategorized'}</p>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-primary btn-sm" onclick="viewProduct(${p.id})">
                                            <i class="bi bi-eye"></i> View Details
                                        </button>
                                        <button class="btn btn-success btn-sm" onclick="quickStockUpdate(${p.id})">
                                            <i class="bi bi-plus-minus"></i> Update Stock
                                        </button>
                                        <button class="btn btn-info btn-sm" onclick="window.location.href='products.php?highlight=${p.id}'">
                                            <i class="bi bi-list"></i> Go to Products
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        scanResult.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error fetching product details:', error);
                });
        }
    }
    
    // Initialize product search
    initProductSearch() {
        const searchInput = document.getElementById('productSearch');
        const resultsDiv = document.getElementById('searchResults');
        
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();
                
                if (query.length < 2) {
                    resultsDiv.innerHTML = '';
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    this.searchProducts(query, resultsDiv);
                }, 300);
            });
        }
    }
    
    // Search products
    async searchProducts(query, resultsDiv) {
        try {
            const response = await fetch(`api/search_products.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success && data.products.length > 0) {
                resultsDiv.innerHTML = data.products.map(product => `
                    <div class="border rounded p-2 mb-2 product-search-result" style="cursor: pointer;" onclick="selectProduct(${product.id})">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <strong>${product.product_name}</strong>
                                ${product.barcode ? `<br><small class="text-muted">Barcode: ${product.barcode}</small>` : ''}
                            </div>
                            <span class="badge ${product.stock_quantity <= product.reorder_level ? 'bg-warning' : 'bg-success'}">
                                ${product.stock_quantity} units
                            </span>
                        </div>
                    </div>
                `).join('');
            } else {
                resultsDiv.innerHTML = '<p class="text-muted">No products found.</p>';
            }
        } catch (error) {
            console.error('Search error:', error);
            resultsDiv.innerHTML = '<p class="text-danger">Search error occurred.</p>';
        }
    }
    
    // Show alert message
    showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('main');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
        }
    }
}

// Global functions
function viewProduct(productId) {
    window.location.href = `product_detail.php?id=${productId}`;
}

function selectProduct(productId) {
    // Simulate QR code detection for selected product
    const qrData = {
        type: 'product',
        id: productId
    };
    
    qrManager.handleQRCodeDetected(JSON.stringify(qrData));
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('scanQRModal'));
    if (modal) {
        modal.hide();
    }
}

function quickStockUpdate(productId) {
    // Create quick stock update modal
    const modalHtml = `
        <div class="modal fade" id="quickStockModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-minus"></i> Quick Stock Update</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="quickStockForm">
                            <input type="hidden" name="product_id" value="${productId}">
                            <div class="mb-3">
                                <label class="form-label">Action</label>
                                <select name="action" class="form-select" required>
                                    <option value="">Select action...</option>
                                    <option value="stock_in">Stock In (+)</option>
                                    <option value="stock_out">Stock Out (-)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="quantity" class="form-control" min="1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea name="notes" class="form-control" rows="2"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="submitQuickStock()">Update Stock</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('quickStockModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('quickStockModal'));
    modal.show();
}

function submitQuickStock() {
    const form = document.getElementById('quickStockForm');
    const formData = new FormData(form);
    
    fetch('api/quick_stock_update.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('quickStockModal'));
            modal.hide();
            
            // Show success message
            qrManager.showAlert('Stock updated successfully!', 'success');
            
            // Refresh scan result if visible
            const scanResult = document.getElementById('scanResult');
            if (scanResult.style.display !== 'none') {
                // Re-trigger scan result update
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }
        } else {
            qrManager.showAlert(data.message || 'Error updating stock', 'danger');
        }
    })
    .catch(error => {
        console.error('Stock update error:', error);
        qrManager.showAlert('Error updating stock', 'danger');
    });
}

function downloadQR(productId) {
    const canvas = document.getElementById(`qr-${productId}`);
    if (canvas) {
        const link = document.createElement('a');
        link.download = `qr-code-product-${productId}.png`;
        link.href = canvas.toDataURL();
        link.click();
    }
}

function printQR(productId) {
    const canvas = document.getElementById(`qr-${productId}`);
    if (canvas) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>QR Code - Product ${productId}</title>
                    <style>
                        body { text-align: center; font-family: Arial, sans-serif; }
                        .qr-container { margin: 20px; }
                        .qr-code { border: 1px solid #ddd; }
                    </style>
                </head>
                <body>
                    <div class="qr-container">
                        <h3>Product QR Code</h3>
                        <img src="${canvas.toDataURL()}" class="qr-code" alt="QR Code">
                        <p>Product ID: ${productId}</p>
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
}

function printAllQRCodes() {
    const qrCodes = document.querySelectorAll('.qr-code-container canvas');
    if (qrCodes.length === 0) {
        alert('No QR codes to print');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    let content = `
        <html>
            <head>
                <title>All QR Codes</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .qr-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; padding: 20px; }
                    .qr-item { text-align: center; border: 1px solid #ddd; padding: 10px; }
                    .qr-code { max-width: 100px; }
                    @media print { .qr-grid { grid-template-columns: repeat(3, 1fr); } }
                </style>
            </head>
            <body>
                <h2 style="text-align: center;">Product QR Codes</h2>
                <div class="qr-grid">
    `;
    
    qrCodes.forEach((canvas, index) => {
        const container = canvas.closest('.qr-code-container');
        const productId = container.dataset.productId;
        const row = canvas.closest('tr');
        const productName = row.querySelector('td:nth-child(2) strong').textContent;
        
        content += `
            <div class="qr-item">
                <img src="${canvas.toDataURL()}" class="qr-code" alt="QR Code">
                <p><strong>${productName}</strong></p>
                <p>ID: ${productId}</p>
            </div>
        `;
    });
    
    content += `
                </div>
            </body>
        </html>
    `;
    
    printWindow.document.write(content);
    printWindow.document.close();
    printWindow.print();
}

// Initialize QR Code Manager when page loads
let qrManager;
document.addEventListener('DOMContentLoaded', function() {
    qrManager = new QRCodeManager();
});