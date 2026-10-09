<?php
// 2FA is required for doctors. Until they have set it up, the only page they can use is enable-2fa.php.
if (!empty($_SESSION['2fa_setup_required'])) {
    header("location: enable-2fa.php");
    exit;
}
