<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireAdmin(); // only admin should access

$message = "";

// Handle form submission
if (isset($_POST['update_settings'])) {
    $taxRate = (float) $_POST['tax_rate'] / 100; // convert % to decimal
    $discountRate = (float) $_POST['discount_rate'] / 100; // convert % to decimal
    
    setSetting('tax_rate', $taxRate);
    setSetting('discount_rate', $discountRate);
    
    $message = "✅ Settings updated successfully!";
}
?>

<?php include '../includes/header.php'; ?>
<div class="container mt-4">
    <h2><i class="fas fa-gear"></i> System Settings</h2>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" class="card p-4" style="max-width:500px;">
        <h5 class="mb-3">Sales Configuration</h5>
        
        <div class="mb-3">
            <label class="form-label">Tax Rate (%)</label>
            <input type="number" step="0.01" name="tax_rate"
                   class="form-control"
                   value="<?php echo getSetting('tax_rate', 0.10) * 100; ?>"
                   min="0" max="100">
            <small class="text-muted">Default tax rate applied to all sales</small>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Discount Rate (%)</label>
            <input type="number" step="0.01" name="discount_rate"
                   class="form-control"
                   value="<?php echo getSetting('discount_rate', 0) * 100; ?>"
                   min="0" max="100">
            <small class="text-muted">Default discount rate applied to all sales</small>
        </div>
        
        <button type="submit" name="update_settings" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Settings
        </button>
    </form>
</div>
<?php include '../includes/footer.php'; ?>