<?php
/*
 * $Id$
 *
 * Description:
 * This script is intended to be used outside of normal WebCalendar
 * use, typically as an RDF/RSS feed to a RSS client.
 *
 * You must have "Enable RSS feed" set to "Yes" in both System
 * Settings and in the specific user's Preferences.
 *
 * Simply use the URL of this file as the feed address in the client.
 * For public user access:
 * http://xxxxx/aaa/rss.php
 * For any other user (where "joe" is the user login):
 * http:/xxxxxx/aaa/rss.php?user=joe
 *
 * By default (if you do not edit this file), events
 * will be loaded for either:
 *   - the next 30 days
 *   - the next 10 events
 *
 * Input parameters:
 * You can override settings by changing the URL parameters:
 *   - days: number of days ahead to look for events
 *   - cat_id: specify a category id to filter on
 *   - repeats: output all events including all repeat instances
 *       repeats=0 do not output repeating events (default)
 *       repeats=1 outputs repeating events 
 *       repeats=2 outputs repeating events but suppresses display of
 *           2nd & subsequent occurences of daily events
 *   - user: login name of calendar to display (instead of public
 *     user).  You must have the
 *     following System Settings configured for this:
 *       Allow viewing other user's calendars: Yes
 *       Public access can view others: Yes
 *
 * Security:
 * $RSS_ENABLED must be set true
 * $USER_RSS_ENABLED must be set true unless this is for the public user
 * Unless the setting for $allow_all_access is modified below, we do
 *   not include Confidential events in the RSS feed.
 * We do not include unapproved events in the RSS feed.
 */

$debug=FALSE;

include_once 'includes/init.php';

load_global_settings ();

$WebCalendar->setLanguage();


if ( empty ( $RSS_ENABLED ) || $RSS_ENABLED != 'Y' ) {
  header ( "Content-Type: text/plain" );
  etranslate("You are not authorized");
  exit;
}
/*
 *
 * Configurable settings for this file.  You may change the settings
 * below to change the default settings.
 * These settings will likely move into the System Settings in the
 * web admin interface in a future release.
 *
 */


// Default time window of events to load
// Can override with "rss.php?days=60"
$numDays = 30;

// Max number of events to display
// Can override with "rss.php?max=20"
$maxEvents = 10;

// Login of calendar user to use
// '__public__' is the login name for the public user
$username = '__public__';

// Allow non-public events to be fed to RSS
// This will only be used if $username is not __public__
$allow_all_access = "N";

// Allow the URL to override the user setting such as
// "rss.php?user=craig"
$allow_user_override = true;

// Load layers
$load_layers = false;

// Load just a specified category (by its id)
// Leave blank to not filter on category (unless specified in URL)
// Can override in URL with "rss.php?cat_id=4"
$cat_id = '';

// Load all repeating events
// Can override with "rss.php?repeats=1"
$allow_repeats = false;

// Load show only first occurence within the given time span of daily repeating events
// Can override with "rss.php?repeats=2"
$show_daily_events_only_once = false;

// End configurable settings...

// Set for use elsewhere as a global
$login = $username;

if ( $allow_user_override ) {
  $u = getValue ( "user", "[A-Za-z0-9_\.=@,\-]+", true );
  if ( ! empty ( $u ) ) {
    $username = $u;
    $login = $u;
    // We also set $login since some functions assume that it is set.
  }
}

load_user_preferences ();

user_load_variables ( $login, "rss_" );
$creator = ( $username == '__public__' ) ? 'Public' : $rss_fullname;

if ( $username != '__public__' && ( empty ( $USER_RSS_ENABLED ) || 
  $USER_RSS_ENABLED != 'Y' ) ) {
  header ( "Content-Type: text/plain" );
  etranslate("You are not authorized");
  exit;
}

$cat_id = '';
if ( $CATEGORIES_ENABLED == 'Y' ) {
  $x = getIntValue ( "cat_id", true );
  if ( ! empty ( $x ) ) {
    $cat_id = $x;
  }
}

if ( $load_layers ) {
  load_user_layers ( $username );
}

//load_user_categories ();

// Calculate date range
$date = getIntValue ( "date", true );
if ( empty ( $date ) || strlen ( $date ) != 8 ) {
  // If no date specified, start with today
  $date = date ( "Ymd" );
}
$thisyear = substr ( $date, 0, 4 );
$thismonth = substr ( $date, 4, 2 );
$thisday = substr ( $date, 6, 2 );

$startTime = mktime ( 0, 0, 0, $thismonth, $thisday, $thisyear );

$x = getIntValue ( "days", true );
if ( ! empty ( $x ) ) {
  $numDays = $x;
}
// Don't let a malicious user specify more than 365 days
if ( $numDays > 365 ) {
  $numDays = 365;
}
$x = getIntValue ( "max", true );
if ( ! empty ( $x ) ) {
  $maxEvents = $x;
}
// Don't let a malicious user specify more than 100 events
if ( $maxEvents > 100 ) {
  $maxEvents = 100;
}

$x = getIntValue ( "repeats", true );
if ( ! empty ( $x ) ) {
  $allow_repeats = $x;
  if ( $x==2 ) {
    $show_daily_events_only_once = $true;
  }
}

$endTime = mktime ( 0, 0, 0, $thismonth, $thisday + $numDays -1,
  $thisyear );
$endDate = date ( "Ymd", $endTime );


/* Pre-Load the repeated events for quicker access */
if (  $allow_repeats == true )
  $repeated_events = read_repeated_events ( $username, $cat_id, $endTime );

/* Pre-load the non-repeating events for quicker access */
$events = read_events ( $username, $startTime, $endTime, $cat_id );

$charset = ( ! empty ( $LANGUAGE )?translate("charset"): "iso-8859-1" );
// This should work ok with RSS, may need to hardcode fallback value
$lang = languageToAbbrev ( ( $LANGUAGE == "Browser-defined" || 
  $LANGUAGE == "none" )? $lang : $LANGUAGE );
  
//header('Content-type: application/rss+xml');
header('Content-type: text/xml');
echo '<?xml version="1.0" encoding="' . $charset . '"?>';
?>

<rdf:RDF
  xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
  xmlns:admin="http://webns.net/mvcb/"
  xmlns:content="http://purl.org/rss/1.0/modules/content/"
  xmlns:cc="http://web.resource.org/cc/"
  xmlns="http://purl.org/rss/1.0/">
  
<channel rdf:about="<?php echo $SERVER_URL . "rss.php"; ?>">
<title><![CDATA[<?php etranslate ( $APPLICATION_NAME ); ?>]]></title>
<link><?php echo $SERVER_URL; ?></link>
<description><![CDATA[<?php etranslate ( $APPLICATION_NAME ); ?>]]></description>
<dc:language><?php echo $lang; ?></dc:language>
<dc:creator><![CDATA[<?php echo $creator; ?>]]></dc:creator>
<?php //proper format is 2002-10-02T10:00:00-05:00
$gmtoffset = substr_replace ( date ( "O" ), ":" . substr ( date ( "O" ), -2), -2, 2 );
?>
<dc:date><?php echo date ( 'Y-m-d' ) . 'T' . date ( 'H:i:s' ). $gmtoffset; ?></dc:date>
<admin:generatorAgent rdf:resource="http://www.k5n.us/webcalendar.php?v=<?php echo $PROGRAM_VERSION; ?>" />

<?php
$numEvents = 0;
$reventIds = array();

echo "\n<items>\n<rdf:Seq>\n";
for ( $i = $startTime; date ( "Ymd", $i ) <= date ( "Ymd", $endTime ) &&
  $numEvents < $maxEvents; $i += ONE_DAY ) {
  $eventIds = array();
  $d = date ( "Ymd", $i );
  $entries = get_entries ( $d, false );
  $rentries = get_repeating_entries ( $username, $d, false );
  if ($debug) echo "\n\ni=$i d=$d \n\n";
  if ($debug) echo "\n\ncountentries==". count($entries) . " " . count ($rentries) . "\n\n";
  if ( count ( $entries ) > 0 || count ( $rentries ) > 0 ) {
    for ( $j = 0; $j < count ( $entries ) && $numEvents < $maxEvents; $j++ ) {
      // Prevent non-Public events from feeding
      if ( $entries[$j]->getAccess() == "P" || $allow_all_access == "Y" ) {
        $eventIds[] = $entries[$j]->getID();
        echo "<rdf:li rdf:resource=\"" . $SERVER_URL . "view_entry.php?id=" . 
          $entries[$j]->getID() . "&amp;friendly=1&amp;rssuser=$login&amp;date=" . 
          $d . "\" />\n";
        $numEvents++;
      }
    }
    for ( $j = 0; $j < count ( $rentries ) && $numEvents < $maxEvents; $j++ ) {

          //to allow repeated daily entries to be suppressed
          //step below is necessary because 1st occurence of repeating 
          //events shows up in $entries AND $rentries & we suppress display
          //of it in $rentries
       if ( in_array($rentries[$j]->getID(),$eventIds)  && 
             $rentries[$j]->getrepeatType()=="daily" ) {
               $reventIds[]=$rentries[$j]->getID(); 
          }


      // Prevent non-Public events from feeding
      // Prevent a repeating event from displaying if the original event
      // has alreay been displayed
       //echo $rentries[$j]->getID() . "<p>";
      if ( ! in_array($rentries[$j]->getID(),$eventIds ) && 
          ( ! $show_daily_events_only_once || ! in_array($rentries[$j]->getID(),$reventIds )) && 
        ( $rentries[$j]->getAccess() == "P" || $allow_all_access == "Y" ) ) {
        echo "<rdf:li rdf:resource=\"" . $SERVER_URL . "view_entry.php?id=" . 
          $rentries[$j]->getID() . "&amp;friendly=1&amp;rssuser=$login&amp;date=" . 
            $d . "\" />\n";

          //show repeating events only once
          if ( $rentries[$j]->getrepeatType()=="daily" ) 
                  $reventIds[]=$rentries[$j]->getID(); 

        $numEvents++;
      }
    }
  }
}
echo "</rdf:Seq>\n</items>\n</channel>\n\n";
?>
<image rdf:about="http://www.k5n.us/k5n_small.gif">
<title><![CDATA[<?php etranslate ( $APPLICATION_NAME ); ?>]]></title>
<link><?php echo $PROGRAM_URL; ?></link>
<url>http://www.k5n.us/k5n_small.gif</url>
</image>
<?php
$numEvents = 0;
$reventIds = array();

for ( $i = $startTime; date ( "Ymd", $i ) <= date ( "Ymd", $endTime ) &&
  $numEvents < $maxEvents; $i += ONE_DAY ) {
  $eventIds=array();
  $d = date ( "Ymd", $i );
  $entries = get_entries ( $d, false  );
  $rentries = get_repeating_entries ( $username, $d );

  if ($debug) echo "\n\ncountentries==". count($entries) . " " . count ($rentries) . "\n\n";
  if ( count ( $entries ) > 0 || count ( $rentries ) > 0 ) {
    for ( $j = 0; $j < count ( $entries ) && $numEvents < $maxEvents; $j++ ) {
      // Prevent non-Public events from feeding
      if ( $username == '__public__' || $entries[$j]->getAccess() == "P" ||
        $allow_all_access == "Y" ) {
        $eventIds[] = $entries[$j]->getID();
        $unixtime = date_to_epoch ( $entries[$j]->getDateTime() );
        $gmtoffset = substr_replace ( date ( "O", $unixtime ), ":" . 
          substr ( date ( "O", $unixtime ), -2), -2, 2 );
        echo "\n<item rdf:about=\"" . $SERVER_URL . "view_entry.php?id=" . 
          $entries[$j]->getID() . "&amp;friendly=1&amp;date=" . $d . "\">\n";
        echo "<title xml:lang=\"$lang\"><![CDATA[" . $entries[$j]->getName() . "]]></title>\n";
        echo "<link>" . $SERVER_URL . "view_entry.php?id=" . 
          $entries[$j]->getID() . "&amp;friendly=1&amp;rssuser=$login&amp;date=" . 
          $d . "</link>\n";
        echo "<description xml:lang=\"$lang\"><![CDATA[" .
          $entries[$j]->getDescription() . "]]></description>\n";
        //category not valid for RSS 1.0
        //echo "<category xml:lang=\"$lang\"><![CDATA[" . $entries[$j]->getName() .
          //"]]></category>\n";
        echo "<content:encoded xml:lang=\"$lang\"><![CDATA[" .
          $entries[$j]->getDescription() . "]]></content:encoded>\n";
        echo "<dc:creator><![CDATA[" . $creator . "]]></dc:creator>\n";
        echo "<dc:date>" . date ( 'Y-m-d', $unixtime ) .'T' . 
          date ( 'H:i:s', $unixtime ) . $gmtoffset . "</dc:date>\n";
        echo "</item>\n";
        $numEvents++;
      }
    }
    for ( $j = 0; $j < count ( $rentries ) && $numEvents < $maxEvents; $j++ ) {

          //to allow repeated daily entries to be suppressed
          //step below is necessary because 1st occurence of repeating 
          //events shows up in $entries AND $rentries & we suppress display
          //of it in $rentries
       if ( in_array($rentries[$j]->getID(),$eventIds)  && 
             $rentries[$j]->getrepeatType()=="daily" ) {
               $reventIds[]=$rentries[$j]->getID(); 
          }


      // Prevent non-Public events from feeding
      // Prevent a repeating event from displaying if the original event 
      // has alreay been displayed; prevent 2nd & later recurrence
      // of daily events from displaying if that option has been selected
      if ( ! in_array($rentries[$j]->getID(),$eventIds ) && 
         ( ! $show_daily_events_only_once || ! in_array($rentries[$j]->getID(),$reventIds )) && 
         ( $rentries[$j]->getAccess() == "P" || $allow_all_access == "Y" ) ) { 
  
          //show repeating events only once
          if ( $rentries[$j]->getrepeatType()=="daily" ) 
                  $reventIds[]=$rentries[$j]->getID(); 


        echo "\n<item rdf:about=\"" . $SERVER_URL . "view_entry.php?id=" . 
          $rentries[$j]->getID() . "&amp;friendly=1&amp;date=" . $d . "\">\n";
        $unixtime = date_to_epoch ( $entries[$j]->getDateTime() );
        $gmtoffset = substr_replace ( date ( "O", $unixtime ), ":" . 
          substr ( date ( "O", $unixtime ), -2), -2, 2 );
        echo "<title xml:lang=\"$lang\"><![CDATA[" . $rentries[$j]->getName() . "]]></title>\n";
        echo "<link>" . $SERVER_URL . "view_entry.php?id=" . 
          $rentries[$j]->getID() . "&amp;friendly=1&amp;rssuser=$login&amp;date=" . 
          $d . "</link>\n";
        echo "<description xml:lang=\"$lang\"><![CDATA[" .
          $rentries[$j]->getDescription() . "]]></description>\n";
        //category not valid for RSS 1.0
        //echo "<category><![CDATA[" .  $rentries[$j]->getName()  .
          //"]]></category>\n";
        echo "<content:encoded xml:lang=\"$lang\"><![CDATA[" .
          $rentries[$j]->getDescription() . "]]></content:encoded>\n";
        echo "<dc:creator><![CDATA[" . $creator . "]]></dc:creator>\n";
        echo "<dc:date>" . date ( 'Y-m-d', $unixtime ) .'T' . 
          date ( 'H:i:s', $unixtime ) . $gmtoffset . "</dc:date>\n";
        echo "</item>\n";   
        $numEvents++;
      }
    }
  }
}
echo "</rdf:RDF>\n";
// Clear login...just in case
$login = '';
exit;

?>
