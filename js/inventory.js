// Inventory page JavaScript functions

function updateProductInfo() {
    const productSelect = document.getElementById('product_id');
    const stockDisplay = document.getElementById('current_stock');
    const priceDisplay = document.getElementById('product_price');
    
    if (!productSelect || !stockDisplay) return;
    
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    if (selectedOption && selectedOption.value) {
        const stock = selectedOption.dataset.stock || '0';
        const price = selectedOption.dataset.price || '0.00';
        
        stockDisplay.textContent = stock;
        if (priceDisplay) {
            priceDisplay.textContent = '₱' + parseFloat(price).toFixed(2);
        }
        
        // Update stock status color
        const stockValue = parseInt(stock);
        stockDisplay.className = 'fw-bold ';
        if (stockValue <= 0) {
            stockDisplay.className += 'text-danger';
        } else if (stockValue <= 10) {
            stockDisplay.className += 'text-warning';
        } else {
            stockDisplay.className += 'text-success';
        }
    } else {
        stockDisplay.textContent = '0';
        if (priceDisplay) {
            priceDisplay.textContent = '₱0.00';
        }
        stockDisplay.className = 'fw-bold text-muted';
    }
}

function addToCart() {
    const productSelect = document.getElementById('product_id');
    const quantityInput = document.getElementById('quantity');
    
    if (!productSelect || !quantityInput) {
        alert('Required elements not found');
        return;
    }
    
    const productId = productSelect.value;
    const quantity = parseInt(quantityInput.value);
    
    if (!productId) {
        alert('Please select a product');
        return;
    }
    
    if (!quantity || quantity <= 0) {
        alert('Please enter a valid quantity');
        return;
    }
    
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    const productName = selectedOption.textContent;
    const price = parseFloat(selectedOption.dataset.price || 0);
    const availableStock = parseInt(selectedOption.dataset.stock || 0);
    
    if (quantity > availableStock) {
        alert(`Only ${availableStock} units available in stock`);
        return;
    }
    
    // Check if product already in cart
    const existingRow = document.querySelector(`#cartTable tbody tr[data-product-id="${productId}"]`);
    
    if (existingRow) {
        // Update existing item
        const existingQtyCell = existingRow.querySelector('.cart-quantity');
        const existingQty = parseInt(existingQtyCell.textContent);
        const newQty = existingQty + quantity;
        
        if (newQty > availableStock) {
            alert(`Cannot add ${quantity} more. Only ${availableStock - existingQty} units available`);
            return;
        }
        
        existingQtyCell.textContent = newQty;
        const totalCell = existingRow.querySelector('.cart-total');
        totalCell.textContent = '₱' + (price * newQty).toFixed(2);
    } else {
        // Add new item to cart
        const cartTable = document.querySelector('#cartTable tbody');
        const row = document.createElement('tr');
        row.dataset.productId = productId;
        row.innerHTML = `
            <td>${productName}</td>
            <td class="cart-quantity">${quantity}</td>
            <td>₱${price.toFixed(2)}</td>
            <td class="cart-total">₱${(price * quantity).toFixed(2)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeFromCart(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        cartTable.appendChild(row);
    }
    
    // Reset form
    productSelect.value = '';
    quantityInput.value = '1';
    updateProductInfo();
    updateCartTotal();
    
    // Show cart section
    const cartSection = document.getElementById('cartSection');
    if (cartSection) {
        cartSection.style.display = 'block';
    }
}

function removeFromCart(button) {
    const row = button.closest('tr');
    row.remove();
    updateCartTotal();
    
    // Hide cart section if empty
    const cartTable = document.querySelector('#cartTable tbody');
    if (cartTable.children.length === 0) {
        const cartSection = document.getElementById('cartSection');
        if (cartSection) {
            cartSection.style.display = 'none';
        }
    }
}

function updateCartTotal() {
    const totalCells = document.querySelectorAll('#cartTable .cart-total');
    let total = 0;
    
    totalCells.forEach(cell => {
        const amount = parseFloat(cell.textContent.replace('₱', ''));
        total += amount;
    });
    
    const totalDisplay = document.getElementById('cartTotal');
    if (totalDisplay) {
        totalDisplay.textContent = '₱' + total.toFixed(2);
    }
    
    const footerTotal = document.getElementById('footerTotal');
    if (footerTotal) {
        footerTotal.textContent = total.toFixed(2);
    }
    
    // Enable/disable complete sale button
    const completeSaleBtn = document.getElementById('completeSaleBtn');
    if (completeSaleBtn) {
        completeSaleBtn.disabled = total <= 0;
    }
}

function completeSale() {
    const cartRows = document.querySelectorAll('#cartTable tbody tr');
    
    if (cartRows.length === 0) {
        alert('Cart is empty');
        return;
    }
    
    // Prepare cart data
    const cartItems = [];
    cartRows.forEach(row => {
        cartItems.push({
            product_id: row.dataset.productId,
            quantity: parseInt(row.querySelector('.cart-quantity').textContent),
            price: parseFloat(row.querySelector('.cart-total').textContent.replace('₱', '')) / parseInt(row.querySelector('.cart-quantity').textContent)
        });
    });
    
    // Set cart data in hidden input
    const cartItemsInput = document.getElementById('cartItemsInput');
    if (cartItemsInput) {
        cartItemsInput.value = JSON.stringify(cartItems);
    }
    
    // Show sale modal
    const saleModal = new bootstrap.Modal(document.getElementById('saleModal'));
    saleModal.show();
}

function clearCart() {
    const cartTable = document.querySelector('#cartTable tbody');
    cartTable.innerHTML = '';
    updateCartTotal();
    
    const cartSection = document.getElementById('cartSection');
    if (cartSection) {
        cartSection.style.display = 'none';
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Add event listener for product selection
    const productSelect = document.getElementById('product_id');
    if (productSelect) {
        productSelect.addEventListener('change', updateProductInfo);
    }
    
    // Add event listener for add to cart button
    const addToCartBtn = document.getElementById('addToCartBtn');
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', addToCart);
    }
    
    // Initialize product info
    updateProductInfo();
    
    console.log('Inventory.js loaded successfully');
});