<?php
ob_start();
session_start();

$_SESSION["user"] = "";
$_SESSION["usertype"] = "";

date_default_timezone_set('Australia/Melbourne');
$_SESSION["date"] = date('Y-m-d');

// personal details from signup.php are needed first
$personal = $_SESSION['personal'] ?? null;
if (!is_array($personal) || empty($personal['fname'])) {
    header("Location: signup.php");
    exit;
}

include("connection.php");

$error = '';
$oldEmail = '';
$oldTele = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = strtolower(trim((string)($_POST['newemail'] ?? '')));
    $tele      = preg_replace('/\s+/', '', (string)($_POST['tele'] ?? ''));
    $password  = (string)($_POST['newpassword'] ?? '');
    $cpassword = (string)($_POST['cpassword'] ?? '');
    $oldEmail  = $email;
    $oldTele   = $tele;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($tele !== '' && !preg_match('/^0[0-9]{9}$/', $tele)) {
        $error = 'Please enter a valid 10-digit mobile number beginning with 0.';
    } elseif ($password === '') {
        $error = 'Please choose a password.';
    } elseif ($password !== $cpassword) {
        $error = 'The passwords you entered do not match. Please try again.';
    } else {
        $stmt = $database->prepare("SELECT 1 FROM webuser WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'An account with this email address already exists. Please sign in instead.';
        } else {
            $name = trim($personal['fname'] . ' ' . $personal['lname']);
            $telValue = $tele !== '' ? $tele : null;

            $stmt = $database->prepare(
                "INSERT INTO patient (pemail, pname, ppassword, paddress, pnic, pdob, ptel) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("sssssss", $email, $name, $password, $personal['address'], $personal['nic'], $personal['dob'], $telValue);
            $okPatient = $stmt->execute();

            $stmt = $database->prepare("INSERT INTO webuser (email, usertype) VALUES (?, 'p')");
            $stmt->bind_param("s", $email);
            $okUser = $okPatient && $stmt->execute();

            if ($okUser) {
                unset($_SESSION['personal']);
                session_regenerate_id(true);
                $_SESSION["user"] = $email;
                $_SESSION["usertype"] = "p";
                $_SESSION["username"] = $personal['fname'];
                header('Location: patient/index.php');
                exit;
            }
            error_log('eDoc registration failed: ' . $database->error);
            $error = 'We were unable to create your account at this time. Please try again later.';
        }
    }
}
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
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
    <style>
        .container{
            animation: transitionIn-X 0.5s;
        }
    </style>
</head>
<body>
    <center>
    <div class="container">
        <table border="0" style="width: 69%;">
            <tr>
                <td colspan="2">
                    <p class="header-text">Create your account</p>
                    <p class="sub-text">Step 2 of 2: Set up your sign-in details</p>
                </td>
            </tr>
            <tr>
                <form action="" method="POST">
                <td class="label-td" colspan="2">
                    <label for="newemail" class="form-label">Email address</label>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <input type="email" id="newemail" name="newemail" class="input-text" placeholder="name@example.com" value="<?php echo $e($oldEmail); ?>" autocomplete="email" maxlength="255" required>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <label for="tele" class="form-label">Mobile number</label>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <input type="tel" id="tele" name="tele" class="input-text" placeholder="e.g. 0412345678" value="<?php echo $e($oldTele); ?>" pattern="0[0-9]{9}" title="A 10-digit number beginning with 0" autocomplete="tel" maxlength="10">
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <label for="newpassword" class="form-label">Password</label>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <input type="password" id="newpassword" name="newpassword" class="input-text" placeholder="Create a password" autocomplete="new-password" required>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <label for="cpassword" class="form-label">Confirm password</label>
                </td>
            </tr>
            <tr>
                <td class="label-td" colspan="2">
                    <input type="password" id="cpassword" name="cpassword" class="input-text" placeholder="Re-enter your password" autocomplete="new-password" required>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <?php if ($error): ?>
                        <label class="form-label" style="color:rgb(255, 62, 62);text-align:center;"><?php echo $e($error); ?></label>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>
                    <a href="signup.php" class="non-style-link"><input type="button" value="Back" class="login-btn btn-primary-soft btn"></a>
                </td>
                <td>
                    <input type="submit" value="Create account" class="login-btn btn-primary btn">
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
