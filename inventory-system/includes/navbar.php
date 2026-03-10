<nav class="navbar navbar-light fixed-top">
    <div class="container-fluid">
        <button class="navbar-toggler d-md-none" type="button" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-boxes"></i> Inventory System
        </a>
        <div class="d-flex align-items-center">
            <span class="me-3" style="color: #64748b;">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                <span class="badge bg-primary ms-2"><?php echo strtoupper($_SESSION['role']); ?></span>
            </span>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>
