<?php
// debug_post.php - HAPUS FILE INI SETELAH SELESAI DEBUG
echo "<pre>";
echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "\$_POST:\n"; print_r($_POST);
echo "</pre>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_redirect'])) {
    header("Location: " . $_SERVER['SCRIPT_NAME']);
    exit;
}
?>
<form method="POST" action="">
    <input type="hidden" name="test_redirect" value="1">
    <button type="submit">Test Redirect ke SCRIPT_NAME</button>
</form>
