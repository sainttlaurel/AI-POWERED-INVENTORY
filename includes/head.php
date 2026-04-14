<?php
/**
 * includes/head.php — Shared <head> block
 * Usage: set $page_title before including this file.
 *        set $extra_css (array) for page-specific stylesheets.
 *        set $extra_head (string) for any inline <style> block.
 */
$page_title   = $page_title   ?? 'InvenAI — Inventory System';
$extra_css    = $extra_css    ?? [];
$extra_head   = $extra_head   ?? '';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="AI-Powered Inventory Management System">
<title><?php echo htmlspecialchars($page_title); ?></title>

<!-- Inter font (Google) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5.3 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<!-- Global Design System -->
<link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">

<?php foreach ($extra_css as $css): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
<?php endforeach; ?>

<?php if ($extra_head): ?>
<?php echo $extra_head; ?>
<?php endif; ?>
