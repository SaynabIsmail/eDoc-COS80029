<?php
ob_start();
session_start();

// make sure nobody is logged in while registering
$_SESSION["user"] = "";
$_SESSION["usertype"] = "";

date_default_timezone_set('Australia/Melbourne');
$_SESSION["date"] = date('Y-m-d');

$old = $_SESSION['personal'] ?? ['fname' => '', 'lname' => '', 'address' => '', 'nic' => '', 'dob' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'fname'   => trim((string)($_POST['fname'] ?? '')),
        'lname'   => trim((string)($_POST['lname'] ?? '')),
        'address' => trim((string)($_POST['address'] ?? '')),
        'nic'     => trim((string)($_POST['nic'] ?? '')),
        'dob'     => trim((string)($_POST['dob'] ?? '')),
    ];
    $old = $data;
    $dob = DateTime::createFromFormat('Y-m-d', $data['dob']);

    if (in_array('', $data, true)) {
        $error = 'Please complete all fields before continuing.';
    } elseif (!$dob || $dob->format('Y-m-d') !== $data['dob'] || $dob > new DateTime('today')) {
        $error = 'Please enter a valid date of birth.';
    } else {
        $_SESSION["personal"] = $data;
        header("Location: create-account.php");
        exit;
    }
}

function old_value(array $old, string $key): string
{
    return htmlspecialchars((string)($old[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/animations.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/signup.css">
    <title>Create an Account | eDoc</title>
</head>
<body>
    <center>
    <div class="container">
        <table border="0">
            <tr>
                <td colspan="2">
                    <p class="header-text">Create your account</p>
                    <p class="sub-text">Step 1 of 2: Enter your personal details</p>
                </td>
            </tr>
            <tr>
                <form action="" method="POST">
                <td class="label-td" colspan="2">
                    <label for="fname" class="form-label">Full name</label>
                </td>
            </tr>
            <tr>
                <td class="label-td">
                    <input type="text" id="fname" name="fname" class="input-text" placeholder="First name" value="<?php echo old_value($old, 'fname'); ?>" autocomplete="given-name" required>
                </td>
                <td class="label-td">
                    <input type="text" name="lname" class="input-text" placeholder="Last name" value="<?php echo old_value($old, 'lname'); ?>" autocomplete="family-name" required>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <label for="address" class="form-label">Residential address</label>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <input type="text" id="address" name="address" class="input-text" placeholder="Street address, suburb, state and postcode" value="<?php echo old_value($old, 'address'); ?>" autocomplete="street-address" required>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <label for="nic" class="form-label">National identity number (NIC)</label>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <input type="text" id="nic" name="nic" class="input-text" placeholder="NIC number" value="<?php echo old_value($old, 'nic'); ?>" required>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <label for="dob" class="form-label">Date of birth</label>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <input type="date" id="dob" name="dob" class="input-text" value="<?php echo old_value($old, 'dob'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <?php if ($error): ?>
                        <label class="form-label" style="color:rgb(255, 62, 62);text-align:center;"><?php echo htmlspecialchars($error); ?></label>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="reset" value="Clear" class="login-btn btn-primary-soft btn">
                </td>
                <td>
                    <input type="submit" value="Continue" class="login-btn btn-primary btn">
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <br>
                    <label class="sub-text" style="font-weight: 280;">Already have an account&#63; </label>
                    <a href="login.php" class="hover-link1 non-style-link">Sign in</a>
                    <br><br><br>
                </td>
            </tr>
                </form>
            </tr>
        </table>
    </div>
    </center>
</body>
</html>
