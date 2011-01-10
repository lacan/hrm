<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Database.inc");
require_once("./inc/hrm_config.inc");
require_once("./inc/Mail.inc");
require_once("./inc/Util.inc");
require_once("./inc/Validator.inc");

global $hrm_url;
global $email_sender;
global $email_admin;
global $image_host;
global $image_folder;
global $image_source;
global $userManager;

session_start();

// Make sure that we don't even show this page if the user
// management is disabled!
if ( $authenticateAgainst != "MYSQL" ) {
  header("Location: " . "home.php"); exit();
}

/*
 *
 * SANITIZE INPUT
 *   We check the relevant contents of $_POST for validity and store them in
 *   a new array $clean that we will use in the rest of the code.
 *
 *   After this step, only the $clean array and no longer the $_POST array
 *   should be used!
 *
 */

  // Here we store the cleaned variables
  $clean = array(
    "username" => "",
    "email"    => '',
    "group"    => "" );

  // Email
  if ( isset( $_POST["username"] ) ) {
    if ( Validator::isUserNameValid( $_POST["username"] ) ) {
      $clean["username"] = $_POST["username"];       
    }
  }
  
  // Email
  if ( isset( $_POST["email"] ) ) {
    if ( Validator::isEmailValid( $_POST["email"] ) ) {
      $clean["email"] = $_POST["email"];       
    }
  }
  
  // Group name
  if ( isset( $_POST["group"] ) ) {
    if ( Validator::isGroupNameValid( $_POST["group"] ) ) {
      $clean["group"] = $_POST["group"];       
    }
  }
  
/*
 *
 * END OF SANITIZE INPUT
 *
 */

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

// Check if a user is logged on. If not, go to the login page and store the seed
// if there is one.
if ( !isset( $_SESSION[ 'user' ] ) ) {
  if ( isset( $_GET[ 'seed' ] ) ) {
    $req = $_SERVER['REQUEST_URI'];
    $_SESSION['request'] = $req;
  }
  header("Location: " . "login.php"); exit();  
}

// Make sure that the user is the admin
if ( !$_SESSION[ 'user' ]->isAdmin( ) ) {
  header("Location: " . "login.php"); exit();  
}

// Now we have a valid admin user logon, we can continue
$db = new DatabaseConnection();

if (isset($_GET['seed'])) {
  if ( !$_SESSION[ 'user' ]->existsUserRequestWithSeed($_GET['seed']) ) {
    header("Location: " . "login.php"); exit();
  }
}

if (isset($_SERVER['HTTP_REFERER']) && !strstr($_SERVER['HTTP_REFERER'], 'admin')  && !strstr($_SERVER['HTTP_REFERER'], 'account')) {
  $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

// TODO refactor
if (isset($_SESSION['admin_referer'])) {
  $_SESSION['referer'] = $_SESSION['admin_referer'];
  unset($_SESSION['admin_referer']);
}

if (isset($_SESSION['account_user']) && gettype($_SESSION['account_user']) != "object") {
  $message = $_SESSION['account_user'];
  unset($_SESSION['account_user']);
}

if (!isset($_SESSION['index'])) {
  $_SESSION['index'] = "";
}
else if (isset($_GET['index'])) {
  $_SESSION['index'] = $_GET['index'];
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

if (isset($_POST['accept'])) {
  $result = $db->updateUserStatus($clean['username'], 'a');
  // TODO refactor
  if ($result) {
    $email = $db->emailAddress($clean['username']);
    $text = "Your account has been activated:\n\n";
    $text .= "\t      Username: ".$clean['username']."\n";
    $text .= "\tE-mail address: ".$email."\n\n";
    $text .= "Login here\n";
    $text .= $hrm_url."\n\n";
    $folder = $image_folder . "/" . $clean['username'];
    $text .= "Source and destination folders for your images are located on server ".$image_host." under ".$folder.".";
    $mail = new Mail($email_sender);
    $mail->setReceiver($email);
    $mail->setSubject("Account activated");
    $mail->setMessage($text);
    $mail->send();
    shell_exec("$userManager create \"" . $clean['username']. "\"");
  }
  else $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>";
}
else if (isset($_POST['reject'])) {
  $email = $db->emailAddress($clean['username']);
  $result = $db->deleteUser( $clean['username'] );
  // TODO refactor
  if (!$result) $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>";
  $text = "Your request has been rejected. Please contact ".$email_admin." for any enquiries.\n";
  $mail = new Mail($email_sender);
  $mail->setReceiver($email);
  $mail->setSubject("Request rejected");
  $mail->setMessage($text);
  $mail->send();
}
else if (isset($_POST['annihilate']) && $_POST['annihilate'] == "yes") {
  if ( $clean['username'] != "admin") {
    $result = $db->deleteUser( $clean['username'] );
      // TODO refactor
      if ($result) {
        shell_exec("$userManager delete \"" . $_POST['username'] . "\"");
      }
      else {
        $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>";
      }
    }
    else {
      $message = "            <p class=\"warning\">Database error, please inform the person in charge</p>";
    }
  }
else if (isset($_POST['edit'])) {
  $_SESSION['account_user'] = new User();
  $_SESSION['account_user']->setName($clean['username']);
  if (isset($c) || isset($_GET['c']) || isset($_POST['c'])) {
    if (isset($_GET['c'])) $_SESSION['c'] = $_GET['c'];
    else if (isset($_POST['c'])) $_SESSION['c'] = $_POST['c'];
  }
  header("Location: " . "account.php"); exit();
}
else if (isset($_POST['enable'])) {
  $result = $db->updateUserStatus($clean['username'], 'a');
}
else if (isset($_POST['disable'])) {
  $result = $db->updateUserStatus($clean['username'], 'd');
}
else if (isset($_POST['action'])) {
  if ($_POST['action'] == "disable") {
    $result = $db->updateAllUsersStatus('d');
  }
  else if ($_POST['action'] == "enable") {
    $result = $db->updateAllUsersStatus('a');
  }
}
// TODO refactor to here

$script = "admin.js";

include("header.inc.php");

?>

    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="<?php echo getThisPageName();?>?home=home" onclick="clean()"><img src="images/home.png" alt="home" />&nbsp;Home</a></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpUserManagement')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Manage users</h3>
        
<?php

$rows = $db->query("SELECT * FROM username");
sort($rows);
$i = 0;
foreach ($rows as $row) {
  $name = $row["name"];
  $email = $row["email"];
  $group = $row["research_group"];
  $creation_date = date("j M Y, G:i", strtotime($row["creation_date"]));
  $status = $row["status"];
  if ($status != "a" && $status != "d") {
    
?>
        <form method="post" action="">
            <div >
                <fieldset>
                    <legend>pending request</legend>
                    <table>
                        <tr class="upline">
                            <td class="name"><span class="title"><?php echo $name ?></span></td>
                            <td class="group"><?php echo $group ?></td>
                            <td class="email"><a href="mailto:<?php echo $email ?>" class="normal"><?php echo $email ?></a></td>
                        </tr>
                        <tr class="bottomline">
                            <td colspan="2" class="date">
                                request date: <?php echo $creation_date."\n" ?>
                            </td>
                            <td class="operations">
                                <div>
                                    <input type="hidden" name="username" value="<?php echo $name ?>" />
                                    <input type="submit" name="accept" value="accept" />
                                    <input type="submit" name="reject" value="reject" />
                                </div>
                            </td>
                        </tr>
                    </table>
                </fieldset>
            </div>
        </form>
        <br />
<?php

    $i++;
  }
}

if (!$i) {

?>
        <p>There are no pending requests.</p>
<?php

}

?>

        <div id="listusers">
            <fieldset>
<?php

$count = $db->queryLastValue("SELECT count(*) FROM username WHERE status = 'a' OR status = 'd'");
$rows = $db->query("SELECT email FROM username WHERE status = 'a'");
$emails = array();
foreach ($rows as $row) {
  array_push($emails, $row['email']);
}
$emails = array_unique($emails);
sort($emails);

?>
                <legend>Existing users (<?php echo $count - 1 ?>)</legend>
                <p class="menu">
                    <a href="javascript:openPopup('add_user')">add new user</a> | <a href="mailto:<?php echo implode( $email_list_separator, $emails) ?>">distribution list</a>
                    <br />
                    <a href="javascript:disableUsers()">disable</a>/<a href="javascript:enableUsers()">enable</a> all users
                </p>
                <form method="post" action="" id="user_management">
                    <div><input type="hidden" name="action" /></div>
                </form>
                <table>
                    <tr>
                        <td colspan="3" class="menu">
<?php

$i = 0;
echo "                            <div class=\"line\">";
$style = " class=\"filled\"";
if ($_SESSION['index'] == "all") $style = " class=\"selected\"";
echo "[<a href=\"?index=all\"".$style.">&nbsp;all&nbsp;</a>]&nbsp;[";
while (True) {
  $c = chr(97 + $i);
  $style = "";
  $result = $db->queryLastValue("SELECT * FROM username WHERE name LIKE '".$c."%' AND name NOT LIKE 'admin' AND (status = 'a' OR status = 'd')");
  if ($_SESSION['index'] == $c) $style = " class=\"selected\"";
  else if (!$result) $style = " class=\"empty\"";
  else $style = " class=\"filled\"";
  echo "<a href=\"?index=".chr(97 + $i)."\"".$style.">&nbsp;".strtoupper($c)."&nbsp;</a>";
  if ($i == 25) {
    echo "]</div>\n";
    break;
  }
  $i++;
}

?>
                        </td>
                    </tr>
                    <tr><td>&nbsp;</td></tr>
<?php

if ($_SESSION['index'] != "") {
  $condition = "";
  if ($_SESSION['index'] != "all") $condition = " WHERE name LIKE '".$_SESSION['index']."%'";
  $rows = $db->query("SELECT * FROM username".$condition);
  sort($rows);
  $i = 0;
  foreach ($rows as $row) {
    if ($row['name'] != "admin") {
      $name = $row['name'];
      $email = $row['email'];
      $group = $row['research_group'];
      $last_access_date = date("j M Y, G:i", strtotime($row['last_access_date']));
      if ($last_access_date == "30 Nov 1999, 0:00") {
        $last_access_date = "never";
      }
      $status = $row['status'];
      if ($status == "a" || $status == "d") {
        if ($i > 0) echo "                    <tr><td colspan=\"3\" class=\"hr\">&nbsp;</td></tr>\n";

?>
                    <tr  class="upline<?php if ($status == "d") echo " disabled" ?>">
                        <td class="name"><span class="title"><?php echo $name ?></span></td>
                        <td class="group"><?php echo $group ?></td>
                        <td class="email"><a href="mailto:<?php echo $email ?>" class="normal"><?php echo $email ?></a></td>
                    </tr>
                    <tr class="bottomline<?php if ($status == "d") echo " disabled" ?>">
                        <td colspan="2" class="date">
                            last access: <?php echo $last_access_date."\n" ?>
                        </td>
                        <td class="operations">
                            <form method="post" action="">
                                <div>
                                    <input type="hidden" name="username" value="<?php echo $name ?>" />
                                    <input type="hidden" name="email" value="<?php echo $email ?>" />
                                    <input type="hidden" name="group" value="<?php echo $group ?>" />
                                    <input type="submit" name="edit" value="edit" class="submit" />
<?php

        if ($name != $_SESSION[ 'user' ]->getAdminName() ) {
          if ($status == "d") {

?>
                                    <input type="submit" name="enable" value="enable" class="submit" />
<?php

          }
          else {

?>
                                    <input type="submit" name="disable" value="disable" class="submit" />
<?php

          }

?>

                                    <input type="hidden" name="annihilate" />
                                    <input type="button" name="delete" value="delete" onclick="warn(this.form)" class="submit" />
<?php

        }

?>
                                </div>
                            </form>
                        </td>
                    </tr>
<?php

        $i++;
      }
    }
  }
  if (!$i) {

?>
                    <tr><td colspan="3" class="notice">n/a</td></tr>
<?php

  }
}

?>
                </table>
            </fieldset>
        </div>
        
    </div> <!-- content -->
    
    <div id="rightpanel">
        <div id="info">
          <h3>Quick help</h3>
            <p>You can add new users, accept or reject pending registration 
                requests, and manage existing users.</p>
        </div>
        <div id="message">
<?php

print $message;

?>
        </div>
    </div>  <!-- rightpanel -->
    
<?php

include("footer.inc.php");

?>