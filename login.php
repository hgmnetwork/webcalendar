<?php

include "includes/config.php";
include "includes/php-dbi.php";
include "includes/functions.php";
include "includes/$user_inc";
include "includes/connect.php";

load_global_settings ();

if ( ! empty ( $last_login ) )
  $login = "";

if ( empty ( $webcalendar_login ) )
  $webcalendar_login = "";
if ( $remember_last_login == "Y" && empty ( $login ) ) {
  $last_login = $login = $webcalendar_login;
}

load_user_preferences ();

include "includes/translate.php";

// see if a return path was set
if ( ! empty ( $return_path ) ) {
  $url = $return_path;
} else {
  $url = "index.php";
}

// calculate path for cookie
if ( empty ( $PHP_SELF ) )
  $PHP_SELF = $_SERVER["PHP_SELF"];
$cookie_path = str_replace ( "login.php", "", $PHP_SELF );
//echo "Cookie path: $cookie_path\n";

if ( $single_user == "Y" ) {
  // No login for single-user mode
  do_redirect ( "index.php" );
} else if ( $use_http_auth ) {
  // There is no login page when using HTTP authorization
  do_redirect ( "index.php" );
} else {
  if ( ! empty ( $login ) && ! empty ( $password ) ) {
    $login = trim ( $login );
    if ( user_valid_login ( $login, $password ) ) {
      user_load_variables ( $login, "" );
      // set login to expire in 365 days
      srand((double) microtime() * 1000000);
      $salt = chr( rand(ord('A'), ord('z'))) . chr( rand(ord('A'), ord('z')));
      $encoded_login = encode_string ( $login . "|" . crypt($password, $salt) );

      if ( $remember == "yes" )
        SetCookie ( "webcalendar_session", $encoded_login,
          time() + ( 24 * 3600 * 365 ), $cookie_path );
      else
        SetCookie ( "webcalendar_session", $encoded_login, 0, $cookie_path );
      // The cookie "webcalendar_login" is provided as a convenience to
      // other apps that may wish to find out what the last calendar
      // login was, so they can use week_ssi.php as a server-side include.
      // As such, it's not a security risk to have it un-encoded since it
      // is not used to allow logins within this app.  It is used to
      // load user preferences on the login page (before anyone has
      // logged in) if $remember_last_login is set to "Y" (in admin.php).
      if ( $remember == "yes" )
        SetCookie ( "webcalendar_login", $login,
          time() + ( 24 * 3600 * 365 ), $cookie_path );
      else
        SetCookie ( "webcalendar_login", $login, 0, $cookie_path );
      do_redirect ( $url );
    }
  }
  // delete current user
  SetCookie ( "webcalendar_session", "", 0, $cookie_path );
  // In older versions the cookie path had no trailing slash and NS 4.78
  // thinks "path/" and "path" are different, so the line above does not
  // delete the "old" cookie. This prohibits the login. So we delete the
  // cookie with the trailing slash removed
  if (substr($cookie_path, -1) == '/')
    SetCookie ( "webcalendar_session", "", 0, substr($cookie_path, 0, -1)  );
}

?>
<html>
<head>
<title><?php etranslate($application_name)?></title>
<script language="JavaScript">
// error check login/password
function valid_form ( form ) {
  if ( form.login.value.length == 0 || form.password.value.length == 0 ) {
    alert ( "<?php etranslate("You must enter a login and password")?>." );
    return false;
  }
  return true;
}
function myOnLoad() {
  <?php if ( $plugins_enabled ) { ?>
  if (self != top)  {
    window.open("login.php","_top","");
    return;
  }
  <?php } ?>
  document.forms[0].login.focus();
  <?php
    if ( ! empty ( $login ) ) echo "document.forms[0].login.select();";
  ?>
}
</script>
<?php include "includes/styles.php"; ?>
<?php
// Print custom header (since we do not call print_header function)
if ( ! empty ( $CUSTOM_SCRIPT ) && $CUSTOM_SCRIPT == 'Y' ) {
  $res = dbi_query (
    "SELECT cal_template_text FROM webcal_report_template " .
    "WHERE cal_template_type = 'S' and cal_report_id = 0" );
  if ( $res ) {
    if ( $row = dbi_fetch_row ( $res ) ) {
      echo $row[0];
    }
    dbi_free_result ( $res );
  }
}
?>

</head>
<body bgcolor="<?php echo $BGCOLOR;?>"
ONLOAD="myOnLoad();" CLASS="defaulttext">

<?php
// Print custom header (since we do not call print_header function)
if ( ! empty ( $CUSTOM_HEADER ) && $CUSTOM_HEADER == 'Y' ) {
  $res = dbi_query (
    "SELECT cal_template_text FROM webcal_report_template " .
    "WHERE cal_template_type = 'H' and cal_report_id = 0" );
  if ( $res ) {
    if ( $row = dbi_fetch_row ( $res ) ) {
      echo $row[0];
    }
    dbi_free_result ( $res );
  }
}
?>

<h2><font color="<?php echo $H2COLOR?>"><?php etranslate($application_name)?></font></h2>

<?php
if ( ! empty ( $error ) ) {
  print "<font color=\"#FF0000\"><b>" . translate("Error") .
    ":</b> $error</font><br />\n";
}
?>
<form name="login_form" action="login.php" method="POST" onsubmit="return valid_form(this)">

<?php
if ( ! empty ( $return_path ) )
  echo "<input type=\"hidden\" name=\"return_path\" value=\"" .
    htmlentities ( $return_path ) . "\" />\n";
?>

<table border="0">
<tr><td><b><?php etranslate("Username")?>:</b></td>
  <td><input name="login" size="10" value="<?php if ( ! empty ( $last_login ) ) echo $last_login;?>" tabindex="1" /></td></tr>
<tr><td><b><?php etranslate("Password")?>:</b></td>
  <td><input name="password" type="password" size="10" tabindex="2" /></td></tr>
<tr><td colspan="2"><input type="checkbox" name="remember" value="yes" <?php if ( ! empty ( $remember ) && $remember == "yes" ) echo "checked"; ?> /> <?php etranslate("Save login via cookies so I don't have to login next time")?></td></tr>
<tr><td colspan="2"><input type="submit" value="<?php etranslate("Login")?>" tabindex="3" /></td></tr>
</table>

</form>

<br /><br />
<?php if ( $public_access == "Y" ) { ?>
  <a class="navlinks" href="index.php"><?php etranslate("Access public calendar")?></a><br />
<?php } ?>

<?php
if ( $demo_mode == "Y" ) {
  // This is used on the sourceforge demo page
  echo "Demo login: user = \"demo\", password = \"demo\" <br />";
}
?>
<br /><br /><br />
<font size="-1">
<?php etranslate("cookies-note")?>
<br />
<hr /><p>
<a href="<?php echo $PROGRAM_URL ?>" CLASS="aboutinfo"><?php echo $PROGRAM_NAME?></a>
</font>
<?php
// Print custom trailer (since we do not call print_trailer function)
if ( ! empty ( $CUSTOM_TRAILER ) && $CUSTOM_TRAILER == 'Y' ) {
  $res = dbi_query (
    "SELECT cal_template_text FROM webcal_report_template " .
    "WHERE cal_template_type = 'T' and cal_report_id = 0" );
  if ( $res ) {
    if ( $row = dbi_fetch_row ( $res ) ) {
      echo $row[0];
    }
    dbi_free_result ( $res );
  }
}
?>
</body>
</html>
