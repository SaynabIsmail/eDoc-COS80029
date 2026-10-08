<?php
// the patient list is for doctors/admins only, so send patients back to their dashboard
header("location: index.php");
exit;
