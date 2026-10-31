<?php ob_start(); ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/animations.css">  
    <link rel="stylesheet" href="css/main.css">  
    <link rel="stylesheet" href="css/login.css">
        
    <title>Sign In | eDoc</title>

    
    
</head>
<body>
    <?php

    //learn from w3schools.com
    //Unset all the server side variables

    session_start();

    $_SESSION["user"]="";
    $_SESSION["usertype"]="";
    
    // Set the new timezone
    date_default_timezone_set('Australia/Melbourne');
    $date = date('Y-m-d');

    $_SESSION["date"]=$date;
    

    //import database
    include("connection.php");

    



    if($_POST){

        $email=$_POST['useremail'];
        $password=$_POST['userpassword'];
        
        $error='<label for="promter" class="form-label"></label>';

        $stmt = $database->prepare("select usertype from webuser where email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows==1){
            $utype=$result->fetch_assoc()['usertype'];
            if ($utype=='p'){
                //TODO
                $stmt = $database->prepare("select 1 from patient where pemail = ? and ppassword = ? and ppassword <> ''");
                $stmt->bind_param('ss', $email, $password);
                $stmt->execute();
                $checker = $stmt->get_result();
                if ($checker->num_rows==1){


                    //   Patient dashbord
                    session_regenerate_id(true);
                    $_SESSION['user']=$email;
                    $_SESSION['usertype']='p';
                    
                    header('location: patient/index.php'); exit;

                }else{
                    $error='<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">The email address or password you entered is incorrect.</label>';
                }

            }elseif($utype=='a'){
                //TODO
                $stmt = $database->prepare("select 1 from admin where aemail = ? and apassword = ?");
                $stmt->bind_param('ss', $email, $password);
                $stmt->execute();
                $checker = $stmt->get_result();
                if ($checker->num_rows==1){


                    //   Admin dashbord
                    session_regenerate_id(true);
                    $_SESSION['user']=$email;
                    $_SESSION['usertype']='a';
                    
                    header('location: admin/index.php'); exit;

                }else{
                    $error='<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">The email address or password you entered is incorrect.</label>';
                }


            }elseif($utype=='d'){
                //TODO
                $stmt = $database->prepare("select docid from doctor where docemail = ? and docpassword = ? and docpassword <> ''");
                $stmt->bind_param('ss', $email, $password);
                $stmt->execute();
                $checker = $stmt->get_result();
                if ($checker->num_rows==1){
                    // doctors go through 2FA first (verify-2fa.php sends them to set it up if they haven't yet)
                    $docrow = $checker->fetch_assoc();
                    $_SESSION['pending_2fa_docid'] = $docrow['docid'];
                    $_SESSION['pending_2fa_email'] = $email;
                    header('location: verify-2fa.php'); exit;

                }else{
                    $error='<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">The email address or password you entered is incorrect.</label>';
                }

            }
            
        }else{
            $error='<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">The email address or password you entered is incorrect.</label>';
        }






        
    }else{
        $error='<label for="promter" class="form-label">&nbsp;</label>';
    }

    ?>





    <center>
    <div class="container">
        <table border="0" style="margin: 0;padding: 0;width: 60%;">
            <tr>
                <td>
                    <p class="header-text">Welcome back</p>
                </td>
            </tr>
        <div class="form-body">
            <tr>
                <td>
                    <p class="sub-text">Sign in to your eDoc account to continue</p>
                </td>
            </tr>
            <tr>
                <form action="" method="POST" >
                <td class="label-td">
                    <label for="useremail" class="form-label">Email address</label>
                </td>
            </tr>
            <tr>
                <td class="label-td">
                    <input type="email" id="useremail" name="useremail" class="input-text" placeholder="name@example.com" autocomplete="email" required>
                </td>
            </tr>
            <tr>
                <td class="label-td">
                    <label for="userpassword" class="form-label">Password</label>
                </td>
            </tr>

            <tr>
                <td class="label-td">
                    <input type="password" id="userpassword" name="userpassword" class="input-text" placeholder="Password" autocomplete="current-password" required>
                </td>
            </tr>


            <tr>
                <td><br>
                <?php echo $error ?>
                </td>
            </tr>

            <tr>
                <td>
                    <input type="submit" value="Sign in" class="login-btn btn-primary btn">
                </td>
            </tr>
            <tr>
                <td style="padding-top: 15px; text-align: center;">
                    <a href="google-login.php" style="text-decoration: none;">
                        <button type="button" class="login-btn btn-primary-soft btn" style="width: 100%;">
                            Continue with Google
                        </button>
                    </a>
                </td>
            </tr>
            <tr>
                <td style="padding-top: 10px; text-align: center;">
                    <a href="login-microsoft.php" style="text-decoration: none;">
                        <button type="button" class="login-btn btn-primary-soft btn" style="width: 100%;">
                            Continue with Microsoft
                        </button>
                    </a>
                </td>
            </tr>
        </div>
            <tr>
                <td>
                    <br>
                    <label for="" class="sub-text" style="font-weight: 280;">Don't have an account&#63; </label>
                    <a href="signup.php" class="hover-link1 non-style-link">Create an account</a>
                    <br><br><br>
                </td>
            </tr>
                        
                        
    
                        
                    </form>
        </table>

    </div>
</center>
</body>
</html>