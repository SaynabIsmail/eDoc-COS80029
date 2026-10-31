<?php ob_start(); ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <link rel="stylesheet" href="../css/admin.css">
        
    <title>My Appointments | eDoc</title>
    <style>
        .popup{
            animation: transitionIn-Y-bottom 0.5s;
        }
        .sub-table{
            animation: transitionIn-Y-bottom 0.5s;
        }
</style>
</head>
<body>
    <?php

    //learn from w3schools.com

    session_start();

    if(isset($_SESSION["user"])){
        if(($_SESSION["user"])=="" or $_SESSION['usertype']!='p'){
            header("location: ../login.php"); exit;
        }else{
            $useremail=$_SESSION["user"];
        }

    }else{
        header("location: ../login.php"); exit;
    }
    

    //import database
    include("../connection.php");
    $sqlmain= "select * from patient where pemail=?";
    $stmt = $database->prepare($sqlmain);
    $stmt->bind_param("s",$useremail);
    $stmt->execute();
    $userrow = $stmt->get_result();
    $userfetch=$userrow->fetch_assoc();
    $userid= $userfetch["pid"];
    $username=$userfetch["pname"];


    //echo $userid;
    //echo $username;


    //TODO
    $sqlmain= "select appointment.appoid,schedule.scheduleid,schedule.title,doctor.docname,patient.pname,schedule.scheduledate,schedule.scheduletime,appointment.apponum,appointment.appodate from schedule inner join appointment on schedule.scheduleid=appointment.scheduleid inner join patient on patient.pid=appointment.pid inner join doctor on schedule.docid=doctor.docid  where  patient.pid=$userid ";

    if($_POST){
        //print_r($_POST);
        


        
        if(!empty($_POST["sheduledate"]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST["sheduledate"])){
            $sheduledate=$_POST["sheduledate"];
            $sqlmain.=" and schedule.scheduledate='$sheduledate' ";
        };

    

        //echo $sqlmain;

    }

    $sqlmain.="order by appointment.appodate  asc";
    $result= $database->query($sqlmain);

    // calendar sync status
    require_once __DIR__ . '/../lib/auth.php';
    $calConn  = calendar_connection($database, $useremail);   // provider used for new bookings
    $calSynced = calendar_synced_map($database, $useremail);  // appoid => provider
    $calReady = calendar_ready($database);
    ?>
    <style>
        .cal-banner{ width:93%; box-sizing:border-box; border:1px solid #cfe3fb; background:#f5f9ff; border-radius:8px; padding:14px 20px; margin:18px 0 4px; text-align:left; display:flex; justify-content:space-between; align-items:center; gap:14px; flex-wrap:wrap; }
        .cal-banner.off{ background:#fffaf0; border-color:#f3e2bd; }
        .cal-banner .cal-actions a, .cal-banner .cal-actions button{ margin-left:6px; }
        .cal-badge{ font-size:13px; color:#0a76d8; margin-top:6px; }
        .cal-badge.off{ color:rgb(119,119,119); }
        .appt-actions{ display:flex; gap:6px; }
        .appt-actions a{ flex:1; }
    </style>
    <div class="container">
        <div class="menu">
        <table class="menu-container" border="0">
                <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td width="30%" style="padding-left:20px" >
                                    <img src="../img/user.png" alt="" width="100%" style="border-radius:50%">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title"><?php echo htmlspecialchars((string)$username) ?></p>
                                    <p class="profile-subtitle"><?php echo substr((string)$useremail,0,22)  ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="../logout.php" ><input type="button" value="Sign out" class="logout-btn btn-primary-soft btn"></a>
                                </td>
                            </tr>
                    </table>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-home" >
                        <a href="index.php" class="non-style-link-menu "><div><p class="menu-text">Dashboard</p></a></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="doctors.php" class="non-style-link-menu"><div><p class="menu-text">Find a Doctor</p></a></div>
                    </td>
                </tr>
                
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-session">
                        <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Available Sessions</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-appoinment  menu-active menu-icon-appoinment-active">
                        <a href="appointment.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">My Appointments</p></a></div>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-settings">
                        <a href="settings.php" class="non-style-link-menu"><div><p class="menu-text">Settings</p></a></div>
                    </td>
                </tr>
                
            </table>
        </div>
        <div class="dash-body">
            <table border="0" width="100%" style=" border-spacing: 0;margin:0;padding:0;margin-top:25px; ">
                <tr >
                    <td width="13%" >
                    <a href="appointment.php" ><button  class="login-btn btn-primary-soft btn btn-icon-back"  style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a>
                    </td>
                    <td>
                        <p style="font-size: 23px;padding-left:12px;font-weight: 600;">My Appointments</p>
                                           
                    </td>
                    <td width="15%">
                        <p style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;text-align: right;">
                            Today's date
                        </p>
                        <p class="heading-sub12" style="padding: 0;margin: 0;">
                            <?php 

                        date_default_timezone_set('Australia/Melbourne');

                        $today = date('Y-m-d');
                        echo $today;

                        
                        ?>
                        </p>
                    </td>
                    <td width="10%">
                        <button  class="btn-label"  style="display: flex;justify-content: center;align-items: center;"><img src="../img/calendar.svg" width="100%"></button>
                    </td>


                </tr>
               
                <!-- <tr>
                    <td colspan="4" >
                        <div style="display: flex;margin-top: 40px;">
                        <div class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49);margin-top: 5px;">Schedule a new session</div>
                        <a href="?action=add-session&id=none&error=0" class="non-style-link"><button  class="login-btn btn-primary btn button-icon"  style="margin-left:25px;background-image: url('../img/icons/add.svg');">Add session</font></button>
                        </a>
                        </div>
                    </td>
                </tr> -->
                <tr>
                    <td colspan="4" style="padding-top:10px;width: 100%;" >
                    
                        <center>
                        <?php if ($calReady && $calConn): ?>
                            <div class="cal-banner">
                                <div><b>Calendar sync is active.</b> Your appointments are kept up to date in your
                                    <?php echo e(provider_label($calConn['provider'])); ?> (<?php echo e($calConn['provider_email']); ?>).
                                    Any new booking, change or cancellation is reflected automatically on all devices signed in to that account.</div>
                                <div class="cal-actions">
                                    <form method="POST" action="../calendar-connect.php" style="display:inline">
                                        <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                                        <input type="hidden" name="disconnect" value="<?php echo e($calConn['provider']); ?>">
                                        <button class="btn-primary-soft btn" style="padding:8px 14px">Disconnect calendar</button>
                                    </form>
                                </div>
                            </div>
                        <?php elseif ($calReady): ?>
                            <div class="cal-banner off">
                                <div><b>Keep your calendar up to date automatically.</b> Connect your calendar and every appointment you book, reschedule or cancel will be updated in it for you.</div>
                                <div class="cal-actions">
                                    <a href="../calendar-connect.php?provider=google" class="non-style-link"><button class="btn-primary btn" style="padding:8px 14px">Connect Google Calendar</button></a>
                                    <a href="../calendar-connect.php?provider=microsoft" class="non-style-link"><button class="btn-primary btn" style="padding:8px 14px">Connect Outlook</button></a>
                                    <a href="<?php echo e(feed_url($useremail, true)); ?>" class="non-style-link" title="Subscribe from Apple Calendar or another calendar app"><button class="btn-primary-soft btn" style="padding:8px 14px">Apple Calendar &amp; others</button></a>
                                </div>
                            </div>
                        <?php endif; ?>
                        </center>
                        <p class="heading-main12" style="margin-left: 45px;font-size:18px;color:rgb(49, 49, 49)">My appointments (<?php echo $result->num_rows; ?>)</p>
                    </td>
                    
                </tr>
                <tr>
                    <td colspan="4" style="padding-top:0px;width: 100%;" >
                        <center>
                        <table class="filter-container" border="0" >
                        <tr>
                           <td width="10%">

                           </td> 
                        <td width="5%" style="text-align: center;">
                        Date
                        </td>
                        <td width="30%">
                        <form action="" method="post">
                            
                            <input type="date" name="sheduledate" id="date" class="input-text filter-container-items" style="margin: 0;width: 95%;">

                        </td>
                        
                    <td width="12%">
                        <input type="submit"  name="filter" value=" Filter" class=" btn-primary-soft btn button-icon btn-filter"  style="padding: 15px; margin :0;width:100%">
                        </form>
                    </td>

                    </tr>
                            </table>

                        </center>
                    </td>
                    
                </tr>
                
               
                  
                <tr>
                   <td colspan="4">
                       <center>
                        <div class="abc scroll">
                        <table width="93%" class="sub-table scrolldown" border="0" style="border:none">
                        
                        <tbody>
                        
                            <?php

                                
                                

                                if($result->num_rows==0){
                                    echo '<tr>
                                    <td colspan="7">
                                    <br><br><br><br>
                                    <center>
                                    <img src="../img/notfound.svg" width="25%">
                                    
                                    <br>
                                    <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">No matching records were found.</p>
                                    <a class="non-style-link" href="appointment.php"><button  class="login-btn btn-primary-soft btn"  style="display: flex;justify-content: center;align-items: center;margin-left:20px;">&nbsp; View all appointments &nbsp;</font></button>
                                    </a>
                                    </center>
                                    <br><br><br><br>
                                    </td>
                                    </tr>';
                                    
                                }
                                else{

                                    for ( $x=0; $x<($result->num_rows);$x++){
                                        echo "<tr>";
                                        for($q=0;$q<3;$q++){
                                            $row=$result->fetch_assoc();
                                            if (!isset($row)){
                                            break;
                                            };
                                            $scheduleid=$row["scheduleid"];
                                            $title=$row["title"];
                                            $docname=$row["docname"];
                                            $scheduledate=$row["scheduledate"];
                                            $scheduletime=$row["scheduletime"];
                                            $apponum=$row["apponum"];
                                            $appodate=$row["appodate"];
                                            $appoid=$row["appoid"];
    
                                            if($scheduleid==""){
                                                break;
                                            }
    
                                            echo '
                                            <td style="width: 25%;">
                                                    <div  class="dashboard-items search-items"  >
                                                    
                                                        <div style="width:100%;">
                                                        <div class="h3-search">
                                                                    Booked on '.substr((string)$appodate,0,30).'<br>
                                                                    Reference: OC-000-'.$appoid.'
                                                                </div>
                                                                <div class="h1-search">
                                                                    '.substr((string)$title,0,21).'<br>
                                                                </div>
                                                                <div class="h3-search">
                                                                    Appointment number<div class="h1-search">'.sprintf('%02d',(int)$apponum).'</div>
                                                                </div>
                                                                <div class="h3-search">
                                                                    '.substr((string)$docname,0,30).'
                                                                </div>
                                                                
                                                                
                                                                <div class="h4-search">
                                                                    Date: '.$scheduledate.'<br>Starts at <b>'.substr((string)$scheduletime,0,5).'</b>
                                                                </div>
                                                                <br>
                                                                <div class="cal-badge'.(isset($calSynced[(int)$appoid]) ? '' : ' off').'">'.(isset($calSynced[(int)$appoid]) ? '&#10003; Added to your '.e(provider_label($calSynced[(int)$appoid])) : 'Not synced to a calendar').'</div>
                                                                <div class="appt-actions" style="margin-top:8px">
                                                                    <a href="reschedule.php?id='.$appoid.'" class="non-style-link"><button  class="login-btn btn-primary btn"  style="padding-top:11px;padding-bottom:11px;width:100%"><font class="tn-in-text">Reschedule</font></button></a>
                                                                    <a href="calendar-ics.php?id='.$appoid.'" class="non-style-link" title="Download a calendar file (.ics) for Apple Calendar, Outlook and others"><button  class="login-btn btn-primary-soft btn"  style="padding-top:11px;padding-bottom:11px;width:100%"><font class="tn-in-text">Add to calendar</font></button></a>
                                                                </div>
                                                                <a href="?action=drop&id='.$appoid.'&title='.urlencode($title).'&doc='.urlencode($docname).'" ><button  class="login-btn btn-primary-soft btn "  style="padding-top:11px;padding-bottom:11px;width:100%;margin-top:6px"><font class="tn-in-text">Cancel appointment</font></button></a>
                                                        </div>
                                                                
                                                    </div>
                                                </td>';
    
                                        }
                                        echo "</tr>";
                           
                                // for ( $x=0; $x<$result->num_rows;$x++){
                                //     $row=$result->fetch_assoc();
                                //     $appoid=$row["appoid"];
                                //     $scheduleid=$row["scheduleid"];
                                //     $title=$row["title"];
                                //     $docname=$row["docname"];
                                //     $scheduledate=$row["scheduledate"];
                                //     $scheduletime=$row["scheduletime"];
                                //     $pname=$row["pname"];
                                //     
                                //     
                                //     echo '<tr >
                                //         <td style="font-weight:600;"> &nbsp;'.
                                        
                                //         substr((string)$pname,0,25)
                                //         .'</td >
                                //         <td style="text-align:center;font-size:23px;font-weight:500; color: var(--btnnicetext);">
                                //         '.$apponum.'
                                        
                                //         </td>
                                //         <td>
                                //         '.substr((string)$title,0,15).'
                                //         </td>
                                //         <td style="text-align:center;;">
                                //             '.substr((string)$scheduledate,0,10).' @'.substr((string)$scheduletime,0,5).'
                                //         </td>
                                        
                                //         <td style="text-align:center;">
                                //             '.$appodate.'
                                //         </td>

                                //         <td>
                                //         <div style="display:flex;justify-content: center;">
                                        
                                //         <!--<a href="?action=view&id='.$appoid.'" class="non-style-link"><button  class="btn-primary-soft btn button-icon btn-view"  style="padding-left: 40px;padding-top: 12px;padding-bottom: 12px;margin-top: 10px;"><font class="tn-in-text">View</font></button></a>
                                //        &nbsp;&nbsp;&nbsp;-->
                                //        <a href="?action=drop&id='.$appoid.'&name='.urlencode($pname).'&session='.urlencode($title).'&apponum='.$apponum.'" class="non-style-link"><button  class="btn-primary-soft btn button-icon btn-delete"  style="padding-left: 40px;padding-top: 12px;padding-bottom: 12px;margin-top: 10px;"><font class="tn-in-text">Cancel</font></button></a>
                                //        &nbsp;&nbsp;&nbsp;</div>
                                //         </td>
                                //     </tr>';
                                    
                                }
                            }
                                 
                            ?>
 
                            </tbody>

                        </table>
                        </div>
                        </center>
                   </td> 
                </tr>
                       
                        
                        
            </table>
        </div>
    </div>
    <?php
    
    if($_GET){
        $id=(int)($_GET["id"] ?? 0);
        $action=$_GET["action"] ?? '';
        $syncStatus=$_GET["sync"] ?? '';
        $syncProvider=$_GET["provider"] ?? '';
        $syncAppoid=(int)($_GET["appoid"] ?? 0);
        if ($syncStatus === 'synced') {
            $syncMsg = 'It has been added to your <b>'.e(provider_label($syncProvider)).'</b>.';
        } elseif ($syncStatus === 'error') {
            $syncMsg = 'Your booking is confirmed, but we were unable to update your '.e(provider_label($syncProvider)).' at this time. You can <a href="calendar-ics.php?id='.$syncAppoid.'">download the calendar file</a> or reconnect your calendar from this page.';
        } else {
            $syncMsg = '<a href="calendar-ics.php?id='.$syncAppoid.'">Add this appointment to your calendar</a>, or connect Google Calendar or Outlook on this page to have future appointments added automatically.';
        }
        if (in_array($action, ['rescheduled','cancelled','session-full','already-booked'], true) || isset($_GET['calendar'])) {
            if ($action === 'rescheduled') {
                $popTitle = 'Appointment rescheduled';
                $popBody = 'Your appointment has been moved. Your new appointment number is <b>'.$id.'</b>.<br><br>'.($syncStatus === 'synced'
                    ? 'The event in your <b>'.e(provider_label($syncProvider)).'</b> has been updated to the new time.'
                    : $syncMsg);
            } elseif ($action === 'cancelled') {
                $popTitle = 'Appointment cancelled';
                $popBody = 'Your appointment has been cancelled. If it was in your Google Calendar or Outlook, it has also been removed from there.';
            } elseif ($action === 'session-full') {
                $popTitle = 'Session fully booked';
                $popBody = 'Unfortunately, there are no places left in this session. Please choose another session.';
            } elseif ($action === 'already-booked') {
                $popTitle = 'Already booked';
                $popBody = 'You already have an appointment in this session. You can view, reschedule or cancel it from this page.';
            } elseif (($_GET['calendar'] ?? '') === 'connected') {
                $popTitle = 'Calendar connected';
                $popBody = 'Your appointments will now be added to your <b>'.e(provider_label($_GET['provider'] ?? '')).'</b> automatically.';
            } else {
                $popTitle = 'Calendar disconnected';
                $popBody = 'eDoc will no longer update this calendar. Events that were already added will remain in your calendar.';
            }
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                    <br><br>
                        <h2>'.$popTitle.'</h2>
                        <a class="close" href="appointment.php">&times;</a>
                        <div class="content">'.$popBody.'<br><br></div>
                        <div style="display: flex;justify-content: center;">
                        <a href="appointment.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;OK&nbsp;&nbsp;</font></button></a>
                        <br><br><br><br>
                        </div>
                    </center>
            </div>
            </div>
            ';
        }
        if($action=='booking-added'){
            
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                    <br><br>
                        <h2>Booking confirmed</h2>
                        <a class="close" href="appointment.php">&times;</a>
                        <div class="content">
                        Thank you. Your appointment number is <b>'.$id.'</b>.<br><br>'.$syncMsg.'<br><br>
                            
                        </div>
                        <div style="display: flex;justify-content: center;">
                        
                        <a href="appointment.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;OK&nbsp;&nbsp;</font></button></a>
                        <br><br><br><br>
                        </div>
                    </center>
            </div>
            </div>
            ';
        }elseif($action=='drop'){
            $title=e($_GET["title"] ?? '');
            $docname=e($_GET["doc"] ?? '');
            
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                        <h2>Please confirm</h2>
                        <a class="close" href="appointment.php">&times;</a>
                        <div class="content">
                            Are you sure you want to cancel this appointment?<br><br>
                            Session: &nbsp;<b>'.substr((string)$title,0,40).'</b><br>
                            Doctor: &nbsp;<b>'.substr((string)$docname,0,40).'</b><br><br>
                            
                        </div>
                        <div style="display: flex;justify-content: center;">
                        <a href="delete-appointment.php?id='.$id.'" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;Yes, cancel appointment&nbsp;</font></button></a>&nbsp;&nbsp;&nbsp;
                        <a href="appointment.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;Keep appointment&nbsp;&nbsp;</font></button></a>

                        </div>
                    </center>
            </div>
            </div>
            '; 
        }elseif($action=='view'){
            $sqlmain= "select * from doctor where docid=?";
            $stmt = $database->prepare($sqlmain);
            $stmt->bind_param("i",$id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row=$result->fetch_assoc();
            $name=$row["docname"];
            $email=$row["docemail"];
            $spe=$row["specialties"];
            
            $sqlmain= "select sname from specialties where id=?";
            $stmt = $database->prepare($sqlmain);
            $stmt->bind_param("s",$spe);
            $stmt->execute();
            $spcil_res = $stmt->get_result();
            $spcil_array= $spcil_res->fetch_assoc();
            $spcil_name=$spcil_array["sname"];
            $nic=$row['docnic'];
            $tele=$row['doctel'];
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                        <h2></h2>
                        <a class="close" href="doctors.php">&times;</a>
                        <div class="content">
                            eDoc<br>
                            
                        </div>
                        <div style="display: flex;justify-content: center;">
                        <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                        
                            <tr>
                                <td>
                                    <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">Details</p><br><br>
                                </td>
                            </tr>
                            
                            <tr>
                                
                                <td class="label-td" colspan="2">
                                    <label for="name" class="form-label">Full name</label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    '.$name.'<br><br>
                                </td>
                                
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="Email" class="form-label">Email address</label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                '.$email.'<br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="nic" class="form-label">NIC number</label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                '.$nic.'<br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="Tele" class="form-label">Phone number</label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                '.$tele.'<br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="spec" class="form-label">Specialty</label>
                                    
                                </td>
                            </tr>
                            <tr>
                            <td class="label-td" colspan="2">
                            '.$spcil_name.'<br><br>
                            </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="doctors.php"><input type="button" value="OK" class="login-btn btn-primary-soft btn" ></a>
                                
                                    
                                </td>
                
                            </tr>
                           

                        </table>
                        </div>
                    </center>
                    <br><br>
            </div>
            </div>
            ';  
    }
}

    ?>
    </div>

</body>
</html>
